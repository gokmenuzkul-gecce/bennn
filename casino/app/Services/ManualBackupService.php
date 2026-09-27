<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class ManualBackupService
{
    public function create(): string
    {
        return (new PatchManager())->locked(function () {
            $root = dirname(base_path()); $dir = storage_path('app/manual-backups');
            File::ensureDirectoryExists($dir, 0700);
            $id = 'backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6));
            $sql = $dir . '/' . $id . '.sql'; $archive = $dir . '/' . $id . '.zip';
            try {
                $this->database($sql);
                $zip = new ZipArchive();
                if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create backup ZIP.');
                try {
                    foreach (['casino/app', 'casino/bootstrap', 'casino/config', 'casino/database/migrations', 'casino/resources', 'casino/routes', 'frontend', 'minimal', 'js', 'public'] as $prefix) {
                        if (!is_dir($root . '/' . $prefix)) continue;
                        $filter = new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root . '/' . $prefix, \FilesystemIterator::SKIP_DOTS), function ($entry) use ($root) {
                            $path = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
                            return !$entry->isLink() && !preg_match('#(^|/)(games|Games|CedarGames|vendor|node_modules|storage|uploads?|cache|tests|originals|cedar)(/|$)#', $path)
                                && !preg_match('/\.(zip|sql|log|pem|key|bak|tmp)$/i', $path);
                        });
                        foreach (new \RecursiveIteratorIterator($filter) as $file) {
                            if (!$file->isFile()) continue;
                            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                            if (PatchPackage::allowedPath($path, 'core') && !$zip->addFile($file->getPathname(), $path)) throw new RuntimeException('Cannot add backup file.');
                        }
                    }
                    foreach (['VERSION', '.htaccess', 'index.php', 'casino/artisan', 'casino/composer.json', 'casino/composer.lock'] as $path) {
                        if (is_file($root . '/' . $path)) $zip->addFile($root . '/' . $path, $path);
                    }
                    $zip->addFile($sql, 'database.sql');
                    // This is recovery metadata, not runtime cache or credentials.
                    $history = storage_path('app/patch-manager/history.json');
                    if (is_file($history)) $zip->addFile($history, 'patch-history.json');
                    $zip->addFromString('BACKUP-README.txt', "Core code and database backup. Created " . gmdate('c') . "\nExcludes vendor, games, Cedar assets, uploads, .env and runtime storage. Preserve your existing .env/APP_KEY separately; encrypted settings need the same key. Reinstall dependencies from composer.lock. Restore database and code from the same backup; restore patch-history.json to casino/storage/app/patch-manager/history.json (or remove a newer history if this backup has none). Backups and restores are operator-managed.\n");
                } catch (\Throwable $e) { $zip->close(); throw $e; }
                if (!$zip->close()) throw new RuntimeException('Backup ZIP could not be finalized.');
                return basename($archive);
            } catch (\Throwable $e) { if (is_file($archive)) unlink($archive); throw $e; }
            finally { if (is_file($sql)) unlink($sql); }
        });
    }
    private function database(string $target): void
    {
        $config = config('database.connections.' . config('database.default'));
        if (($config['driver'] ?? '') === 'sqlite') {
            throw new RuntimeException('The manual backup MVP supports MySQL/MariaDB. Use your database tool for other engines.');
        }
        if (($config['driver'] ?? '') !== 'mysql') throw new RuntimeException('MySQL/MariaDB is required.');
        $binary = (string) config('patches.mysqldump', 'mysqldump');
        if (PHP_OS_FAMILY === 'Windows' && $binary === 'mysqldump') {
            $matches = glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe') ?: []; if ($matches) $binary = end($matches);
        }
        $args = [$binary, '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces', '--hex-blob',
            '--host=' . ($config['host'] ?? '127.0.0.1'), '--port=' . ($config['port'] ?? 3306), '--user=' . ($config['username'] ?? ''), '--result-file=' . $target, $config['database']];
        $process = new Process($args, base_path(), ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout(300)->run();
        if (!$process->isSuccessful() || !is_file($target) || filesize($target) < 100) throw new RuntimeException('Database export failed. Check mysqldump availability and database permissions.');
    }
}
