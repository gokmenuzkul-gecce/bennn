<?php
namespace VanguardLTE\Support;

use Symfony\Component\Process\Process;

final class InstallerPhpCli
{
    public static function check(string $root, ?string $configured = null): array
    {
        $sibling = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');
        $binary = $configured ?: (PHP_SAPI === 'cli' ? PHP_BINARY : (is_file($sibling) ? $sibling : 'php'));
        $failed = ['ok' => false, 'binary' => $binary, 'message' => 'PHP CLI could not start with the required runtime and extensions. Ask your host to set PROMEX_PHP_BINARY to its matching PHP CLI executable.'];
        try {
            $code = 'require $argv[1]; echo json_encode(["binary"=>PHP_BINARY,"version"=>PHP_VERSION_ID,"sapi"=>PHP_SAPI,"missing"=>array_values(array_filter(["pdo_mysql","curl","openssl","mbstring","fileinfo","zip"],fn($e)=>!extension_loaded($e)))]);';
            $process = new Process([$binary, '-r', $code, $root . '/casino/vendor/composer/platform_check.php'], $root . '/casino');
            $process->setTimeout(15)->run();
            $result = json_decode(trim($process->getOutput()), true);
            if (!$process->isSuccessful() || trim($process->getErrorOutput()) !== '' || !is_array($result)
                || ($result['sapi'] ?? '') !== 'cli' || ($result['version'] ?? 0) < 80300 || !isset($result['missing']) || $result['missing']) return $failed;
            return ['ok' => true, 'binary' => str_replace('\\', '/', $result['binary']), 'message' => 'PHP CLI and required extensions are ready for updates and database migrations.'];
        } catch (\Throwable $e) {
            return $failed;
        }
    }
}
