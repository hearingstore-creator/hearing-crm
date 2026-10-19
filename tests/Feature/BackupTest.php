<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** The database download is a restorable consistent dump; the nightly backup reports its result and a failure alerts the owner. */
class BackupTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected string $status;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->status = sys_get_temp_dir() . '/crm-backup-status-' . uniqid() . '.json';
        config(['crm.backup_status_file' => $this->status]);
    }

    protected function tearDown(): void
    {
        @unlink($this->status);
        parent::tearDown();
    }

    protected function dump(): string
    {
        $response = $this->actingAs($this->admin)->get('/admin/database-backup');
        $response->assertOk();
        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $sql = $zip->getFromName('database_backup.sql');
        $zip->close();
        @unlink($zipPath);

        return $sql;
    }

    public function test_the_dump_restores_into_an_empty_database_with_the_same_rows(): void
    {
        $this->patient();
        $this->patient();
        $sql = $this->dump();

        $this->assertStringContainsString('Consistent snapshot', $sql);
        $this->assertStringContainsString("INSERT INTO `customers`", $sql);
        $this->assertStringContainsString("VALUES\n(", $sql, 'rows are written as multi-row inserts');

        $config = DB::connection()->getConfig();
        $scratch = 'crm_restore_check_' . uniqid();
        try {
            $pdo = new \PDO("mysql:host={$config['host']};port={$config['port']}", $config['username'], $config['password']);
            $pdo->exec("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cannot create a scratch database here: ' . $e->getMessage());
        }

        try {
            $pdo->exec("USE `{$scratch}`");
            $pdo->exec($sql);

            foreach (['customers', 'leads', 'users', 'permissions', 'menus', 'migrations'] as $table) {
                $restored = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                $this->assertSame((int) DB::table($table)->count(), $restored, "{$table} row count after restore");
            }
        } finally {
            $pdo->exec("DROP DATABASE IF EXISTS `{$scratch}`");
        }
    }

    public function test_the_download_does_not_disturb_the_open_transaction(): void
    {
        $before = DB::transactionLevel();
        $this->dump();

        $this->assertSame($before, DB::transactionLevel());
        $this->assertTrue(DB::connection()->getPdo()->inTransaction());
    }

    public function test_a_fresh_ok_status_raises_no_alert(): void
    {
        file_put_contents($this->status, json_encode(['status' => 'ok', 'at' => now()->subHours(5)->format('Y-m-d H:i:s'), 'db_bytes' => 123456, 'files' => 'ok', 'env' => 'ok']));

        $this->assertSame(0, Artisan::call('crm:backup-check'));
        $this->assertCount(0, $this->admin->fresh()->notifications);
    }

    public function test_a_failed_stale_or_missing_backup_alerts_the_owner_once_a_day(): void
    {
        // failed
        file_put_contents($this->status, json_encode(['status' => 'failed', 'at' => now()->format('Y-m-d H:i:s'), 'detail' => 'failed near line 40 (exit 1)']));
        $this->assertSame(1, Artisan::call('crm:backup-check'));
        $this->assertSame(1, $this->admin->fresh()->notifications()->count());
        $this->assertStringContainsString('failed near line 40', $this->admin->notifications()->first()->data['text']);

        // same problem again the same day: no second alert
        Artisan::call('crm:backup-check');
        $this->assertSame(1, $this->admin->fresh()->notifications()->count());

        // stale: ok but old
        file_put_contents($this->status, json_encode(['status' => 'ok', 'at' => now()->subHours(60)->format('Y-m-d H:i:s')]));
        $this->assertSame(1, Artisan::call('crm:backup-check'));
        $this->assertSame(2, $this->admin->fresh()->notifications()->count());

        // missing
        unlink($this->status);
        $this->assertSame(1, Artisan::call('crm:backup-check'));
        $this->assertSame(3, $this->admin->fresh()->notifications()->count());
    }

    public function test_the_backup_scripts_have_valid_shell_syntax_and_the_safety_checks(): void
    {
        foreach (['backup-offsite.sh', 'server-update.sh'] as $script) {
            $path = base_path('deploy/' . $script);
            $this->assertFileExists($path);
            exec('bash -n ' . escapeshellarg($path) . ' 2>&1', $out, $code);
            if ($code === 127) {
                $this->markTestSkipped('bash is not available here');
            }
            $this->assertSame(0, $code, $script . ' has a syntax error: ' . implode("\n", $out));
        }

        $nightly = file_get_contents(base_path('deploy/backup-offsite.sh'));
        $this->assertStringContainsString('FILES_DIRS', $nightly);
        $this->assertStringContainsString('BACKUP_PASSPHRASE', $nightly);
        $this->assertStringContainsString('write_status failed', $nightly);

        $deploy = file_get_contents(base_path('deploy/server-update.sh'));
        $this->assertStringContainsString('set -o pipefail', $deploy);
        $this->assertStringContainsString('gzip -t', $deploy);
    }
}
