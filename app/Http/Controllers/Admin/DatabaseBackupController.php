<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use ZipArchive;

class DatabaseBackupController extends Controller
{
    public function download(Request $request)
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized. Only Super Admin can download database backup.');
        }

        $sqlFile = tempnam(sys_get_temp_dir(), 'backup_') . '.sql';
        $zipFile = tempnam(sys_get_temp_dir(), 'backup_') . '.zip';
        $downloadName = 'database_backup_' . date('Y-m-d_His') . '.zip';

        try {
            $this->generateSqlDump($sqlFile);

            $zip = new ZipArchive;
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                abort(500, 'Failed to create zip file: ' . $zip->getStatusString());
            }
            $zip->addFile($sqlFile, 'database_backup.sql');
            if ($zip->close() === false) {
                abort(500, 'Failed to write zip file: ' . $zip->getStatusString());
            }

            if (! file_exists($zipFile)) {
                abort(500, 'Zip file was not created.');
            }

            $zipContent = file_get_contents($zipFile);

            return Response::streamDownload(function () use ($zipContent) {
                echo $zipContent;
            }, $downloadName, [
                'Content-Type' => 'application/zip',
                'Content-Length' => strlen($zipContent),
            ]);
        } finally {
            if (file_exists($sqlFile)) {
                @unlink($sqlFile);
            }
            if (file_exists($zipFile)) {
                @unlink($zipFile);
            }
        }
    }

    private function generateSqlDump(string $sqlFile): void
    {
        $database = DB::connection()->getDatabaseName();
        $pdo = DB::connection()->getPdo();

        $sql = [];
        $sql[] = "-- Database Backup";
        $sql[] = "-- Generated: " . date('Y-m-d H:i:s');
        $sql[] = "-- Database: {$database}";
        $sql[] = "";
        $sql[] = "SET FOREIGN_KEY_CHECKS=0;";
        $sql[] = "";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Skip migration table
            if ($table === 'migrations') {
                continue;
            }

            $sql[] = "-- --------------------------------------------------";
            $sql[] = "-- Table structure for `{$table}`";
            $sql[] = "-- --------------------------------------------------";
            $sql[] = "";

            // CREATE TABLE statement
            $createStatement = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $sql[] = "DROP TABLE IF EXISTS `{$table}`;";
            $sql[] = $createStatement['Create Table'] . ";";
            $sql[] = "";

            // Data rows
            $sql[] = "-- --------------------------------------------------";
            $sql[] = "-- Data dump for `{$table}`";
            $sql[] = "-- --------------------------------------------------";
            $sql[] = "";

            $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
            $columnCount = count($columnNames);
            $placeholders = implode(', ', array_fill(0, $columnCount, '?'));

            $rowCount = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

            if ($rowCount > 0) {
                $chunkSize = 1000;
                $offset = 0;

                while ($offset < $rowCount) {
                    $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$offset}, {$chunkSize}")->fetchAll(\PDO::FETCH_ASSOC);

                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $value) {
                            $values[] = $value === null ? 'NULL' : $pdo->quote($value);
                        }
                        $valueList = implode(', ', $values);
                        $columnList = '`' . implode('`, `', $columnNames) . '`';
                        $sql[] = "INSERT INTO `{$table}` ({$columnList}) VALUES ({$valueList});";
                    }

                    $offset += $chunkSize;
                }
            }

            $sql[] = "";
        }

        $sql[] = "SET FOREIGN_KEY_CHECKS=1;";
        $sql[] = "";

        file_put_contents($sqlFile, implode("\n", $sql));
    }
}
