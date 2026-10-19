<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Full database download for Super Admin (occasional copies; the nightly off-server backup is
 * deploy/backup-offsite.sh and is the one to rely on for recovery).
 *
 * The dump is one consistent snapshot: every table is read inside a single REPEATABLE READ transaction, so a sale
 * saved while the file is being written cannot leave one table ahead of another or skip or repeat rows. Tables are
 * streamed row by row (never loaded whole into memory), written as multi-row INSERTs, and views are included.
 * The `migrations` table is included so a restored copy is up to date, and every download is recorded in the
 * activity log.
 */
class DatabaseBackupController extends Controller
{
    private const ROWS_PER_INSERT = 200;

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

    /** Write the dump to $sqlFile and return the number of tables (views are extra). */
    private function generateSqlDump(string $sqlFile): int
    {
        $database = DB::connection()->getDatabaseName();
        $pdo = DB::connection()->getPdo();
        $out = fopen($sqlFile, 'w');
        $write = fn (string $line) => fwrite($out, $line . "\n");

        $write('-- Database Backup');
        $write('-- Generated: ' . date('Y-m-d H:i:s'));
        $write("-- Database: {$database}");
        $write('-- Consistent snapshot: all tables were read at the same moment.');
        $write('');
        $write('SET NAMES utf8mb4;');
        $write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
        $write('SET FOREIGN_KEY_CHECKS=0;');
        $write('SET UNIQUE_CHECKS=0;');
        $write('');

        // One snapshot for the whole dump. (Already inside a transaction, e.g. in tests: that one is the snapshot.)
        $ownTransaction = ! $pdo->inTransaction();
        $buffered = $pdo->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        if ($ownTransaction) {
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        }

        try {
            $all = $pdo->query('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_NUM);
            $tables = array_column(array_filter($all, fn ($r) => $r[1] === 'BASE TABLE'), 0);
            $views = array_column(array_filter($all, fn ($r) => $r[1] === 'VIEW'), 0);

            foreach ($tables as $table) {
                $this->dumpTable($pdo, $out, $table);
            }
            foreach ($views as $view) {
                $create = $pdo->query("SHOW CREATE VIEW `{$view}`")->fetch(\PDO::FETCH_ASSOC);
                $write("-- View `{$view}`");
                $write("DROP VIEW IF EXISTS `{$view}`;");
                $write($create['Create View'] . ';');
                $write('');
            }
        } finally {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->exec('COMMIT');
            }
        }

        $write('SET UNIQUE_CHECKS=1;');
        $write('SET FOREIGN_KEY_CHECKS=1;');
        fclose($out);

        return count($tables);
    }

    /** Structure, then every row streamed in a single ordered pass, in multi-row INSERTs. */
    private function dumpTable(\PDO $pdo, $out, string $table): void
    {
        fwrite($out, "-- --------------------------------------------------\n-- Table `{$table}`\n-- --------------------------------------------------\n\n");

        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
        fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n");

        $columns = array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        $columnList = '`' . implode('`, `', $columns) . '`';

        // Unbuffered: rows come off the server one at a time instead of the whole table being held in memory
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $pdo->query("SELECT * FROM `{$table}`");

        $batch = [];
        while (($row = $statement->fetch(\PDO::FETCH_NUM)) !== false) {
            $batch[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote($v), $row)) . ')';
            if (count($batch) >= self::ROWS_PER_INSERT) {
                fwrite($out, "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        $statement->closeCursor();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if ($batch) {
            fwrite($out, "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        fwrite($out, "\n");
    }
}
