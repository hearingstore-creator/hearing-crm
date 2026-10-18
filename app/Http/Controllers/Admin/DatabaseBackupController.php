<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Full database download for Super Admin (occasional copies; the daily off-server backup is
 * deploy/backup-offsite.sh). The dump is written to a temp file row by row, so large databases do
 * not have to fit in memory, includes the `migrations` table so a restored copy is up to date,
 * and every download is recorded in the activity log.
 */
class DatabaseBackupController extends Controller
{
    public function download(Request $request)
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized. Only Super Admin can download database backup.');
        }

        @set_time_limit(600);
        $sqlFile = tempnam(sys_get_temp_dir(), 'backup_') . '.sql';
        $zipFile = tempnam(sys_get_temp_dir(), 'backup_') . '.zip';
        $downloadName = 'database_backup_' . date('Y-m-d_His') . '.zip';

        try {
            $tables = $this->generateSqlDump($sqlFile);

            $zip = new ZipArchive;
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                abort(500, 'Failed to create zip file.');
            }
            $zip->addFile($sqlFile, 'database_backup.sql');
            if ($zip->close() === false || ! file_exists($zipFile)) {
                abort(500, 'Failed to write zip file.');
            }
        } finally {
            if (file_exists($sqlFile)) {
                @unlink($sqlFile);
            }
        }

        // A full copy of every patient record left the system: record who took it
        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'subject_type' => 'database',
            'subject_id'   => 0,
            'action'       => 'database_backup_downloaded',
            'new_values'   => ['file' => $downloadName, 'tables' => $tables, 'bytes' => filesize($zipFile)],
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        // Streamed from disk and removed afterwards
        return response()->download($zipFile, $downloadName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /** Write the dump to $sqlFile and return the number of tables. */
    private function generateSqlDump(string $sqlFile): int
    {
        $database = DB::connection()->getDatabaseName();
        $pdo = DB::connection()->getPdo();
        $out = fopen($sqlFile, 'w');
        $write = fn (string $line) => fwrite($out, $line . "\n");

        $write("-- Database Backup");
        $write("-- Generated: " . date('Y-m-d H:i:s'));
        $write("-- Database: {$database}");
        $write("");
        $write("SET FOREIGN_KEY_CHECKS=0;");
        $write("");

        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $write("-- --------------------------------------------------");
            $write("-- Table structure for `{$table}`");
            $write("-- --------------------------------------------------");
            $write("");

            $createStatement = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $write("DROP TABLE IF EXISTS `{$table}`;");
            $write($createStatement['Create Table'] . ";");
            $write("");

            $write("-- --------------------------------------------------");
            $write("-- Data dump for `{$table}`");
            $write("-- --------------------------------------------------");
            $write("");

            $columnNames = array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC), 'Field');
            $columnList = '`' . implode('`, `', $columnNames) . '`';
            $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

            for ($offset = 0; $offset < $rowCount; $offset += 1000) {
                $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$offset}, 1000")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $values = array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote($value), $row);
                    $write("INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");");
                }
            }

            $write("");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;");
        fclose($out);

        return count($tables);
    }
}
