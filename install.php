<?php
/**
 * Promex Gaming Suite v2.0.0 - Turnkey Web Installer
 * Easy 1-click database initialization, admin setup, and environment config.
 */

require_once __DIR__ . '/casino/app/Support/InstallerCleanup.php';
header('Cache-Control: no-store');
session_name('PROMEX_INSTALLER');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => (($_SERVER['HTTPS'] ?? 'off') === 'on')]);
session_start();
$_SESSION['installer_csrf'] ??= bin2hex(random_bytes(32));

$lockFile = __DIR__ . '/installed.lock';
$dumpFiles = [__DIR__ . '/install.sql', __DIR__ . '/database_backup.sql'];
$envFile = __DIR__ . '/casino/.env';
if (!file_exists($envFile) && file_exists(__DIR__ . '/.env')) {
    $envFile = __DIR__ . '/.env';
}

$message = '';
$messageType = '';
$isInstalled = file_exists($lockFile);
$cleanupResult = null;

/** Boot the packaged application only after its environment and database exist. */
function promexInstallerLaravel(string $root): array
{
    static $booted = null;
    if (is_array($booted)) {
        return $booted;
    }

    $autoload = $root . '/casino/vendor/autoload.php';
    $bootstrap = $root . '/casino/bootstrap/app.php';
    if (!is_file($autoload) || !is_file($bootstrap)) {
        throw new RuntimeException('The packaged Laravel runtime is incomplete. Re-upload the clean distribution.');
    }

    require_once $autoload;
    $previousDirectory = getcwd();
    try {
        chdir($root . '/casino');
        $app = require $bootstrap;
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        return $booted = [$app, $kernel];
    } finally {
        if (is_string($previousDirectory)) {
            chdir($previousDirectory);
        }
    }
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($isPost && (!is_string($_POST['installer_csrf'] ?? null)
    || !hash_equals($_SESSION['installer_csrf'], $_POST['installer_csrf']))) {
    http_response_code(419);
    $message = 'Your installer session expired. Reload this page and try again.';
    $messageType = 'error';
    $isPost = false;
}
if ($isPost && $isInstalled && ($_POST['action'] ?? '') === 'finish_cleanup') {
    $cleanupResult = \VanguardLTE\Support\InstallerCleanup::run(__DIR__);
}

// Ensure required storage structure exists
$requiredDirs = [
    __DIR__ . '/casino/storage/app/public',
    __DIR__ . '/casino/storage/framework/cache/data',
    __DIR__ . '/casino/storage/framework/sessions',
    __DIR__ . '/casino/storage/framework/views',
    __DIR__ . '/casino/storage/logs',
    __DIR__ . '/casino/bootstrap/cache',
];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Requirements Check
require_once __DIR__ . '/casino/vendor/autoload.php';
$cliOverride = getenv('PROMEX_PHP_BINARY') ?: null;
if (!$cliOverride && is_file($envFile)) {
    try { $cliOverride = \Dotenv\Dotenv::parse(file_get_contents($envFile))['PROMEX_PHP_BINARY'] ?? null; }
    catch (\Throwable $e) { /* Environment is rewritten after successful installation. */ }
}
$cliCheck = \VanguardLTE\Support\InstallerPhpCli::check(__DIR__, $cliOverride);
$requirements = [
    'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'cURL Extension' => extension_loaded('curl'),
    'OpenSSL Extension' => extension_loaded('openssl'),
    'MBString Extension' => extension_loaded('mbstring'),
    'Storage Directory Writable' => is_writable(__DIR__ . '/casino/storage'),
    'Bootstrap Cache Writable' => is_writable(__DIR__ . '/casino/bootstrap/cache'),
];
$allRequirementsMet = !in_array(false, $requirements, true);

if ($isPost && !$isInstalled && !$allRequirementsMet) {
    $message = 'Fix the server requirements before installing.';
    $messageType = 'error';
}
if ($isPost && !$isInstalled && $allRequirementsMet) {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? 'casino');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';
    
    $appUrl = rtrim($_POST['app_url'] ?? (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], '/');
    $appName = trim($_POST['app_name'] ?? 'Promex Gaming Suite');
    $licenseKey = trim($_POST['license_key'] ?? '');

    $adminUser = 'admin';
    $adminEmail = 'admin@admin.com';
    $adminPass = '123456';

    if (strlen($licenseKey) > 255) {
        $message = "Enter the Promex license issued for this installation.";
        $messageType = "error";
    } else {
        try {
            // 1. Locate SQL Dump
            $foundDump = null;
            foreach ($dumpFiles as $df) {
                if (file_exists($df)) {
                    $foundDump = $df;
                    break;
                }
            }
            if (!$foundDump) {
                throw new Exception("Database template (install.sql) was not found in project root!");
            }

            // 2. Test Connection
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);

            // 3. Create Database if missing
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            // 4. Import SQL File in transactional / multi-statement mode
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            $sqlCommands = file_get_contents($foundDump);
            $pdo->exec($sqlCommands);

            // 5. Setup Admin Account (Role 6 = Admin)
            $hashedPass = password_hash($adminPass, PASSWORD_BCRYPT);
            
            // Check if admin user exists (id=1 or role_id=6)
            $stmt = $pdo->prepare("SELECT id FROM `w_users` WHERE `id` = 1 OR `role_id` = 6 OR `username` = 'admin' LIMIT 1");
            $stmt->execute();
            $existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingAdmin) {
                $adminId = $existingAdmin['id'];
                $updateStmt = $pdo->prepare("UPDATE `w_users` SET `username` = ?, `email` = ?, `password` = ?, `role_id` = 6, `status` = 'Active', `balance` = 10000.00, `shop_id` = 1, `updated_at` = NOW() WHERE `id` = ?");
                $updateStmt->execute([$adminUser, $adminEmail, $hashedPass, $adminId]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO `w_users` (`username`, `email`, `password`, `role_id`, `status`, `balance`, `shop_id`, `created_at`, `updated_at`) VALUES (?, ?, ?, 6, 'Active', 10000.00, 1, NOW(), NOW())");
                $insertStmt->execute([$adminUser, $adminEmail, $hashedPass]);
                $adminId = $pdo->lastInsertId();
            }

            // Ensure Role Mapping (User -> Role 6 Admin)
            $pdo->prepare("DELETE FROM `w_role_user` WHERE `user_id` = ? OR `role_id` = 6")->execute([$adminId]);
            $pdo->prepare("INSERT INTO `w_role_user` (`role_id`, `user_id`, `created_at`, `updated_at`) VALUES (6, ?, NOW(), NOW())")->execute([$adminId]);

            // Ensure Shop Mapping (User -> Shop 1)
            $pdo->prepare("DELETE FROM `w_shops_user` WHERE `user_id` = ?")->execute([$adminId]);
            $pdo->prepare("INSERT INTO `w_shops_user` (`shop_id`, `user_id`) VALUES (1, ?)")->execute([$adminId]);

            // Update App Name in Settings
            $pdo->prepare("UPDATE `w_settings` SET `value` = ? WHERE `key` = 'app_name'")->execute([$appName]);
            $licenseExists = $pdo->query("SELECT COUNT(*) FROM `w_settings` WHERE `key` = 'license_key'")->fetchColumn();
            if ((int) $licenseExists > 0) {
                $pdo->prepare("UPDATE `w_settings` SET `value` = ? WHERE `key` = 'license_key'")->execute([$licenseKey]);
            } else {
                $pdo->prepare("INSERT INTO `w_settings` (`key`, `value`) VALUES ('license_key', ?)")->execute([$licenseKey]);
            }
            foreach (['whatsapp_delivery_provider' => 'promex', 'email_delivery_provider' => 'promex'] as $key => $value) {
                $exists = $pdo->prepare("SELECT COUNT(*) FROM `w_settings` WHERE `key` = ?");
                $exists->execute([$key]);
                if ((int) $exists->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE `w_settings` SET `value` = ? WHERE `key` = ?")->execute([$value, $key]);
                } else {
                    $pdo->prepare("INSERT INTO `w_settings` (`key`, `value`) VALUES (?, ?)")->execute([$key, $value]);
                }
            }

            // 6. Generate APP_KEY
            $generatedAppKey = 'base64:' . base64_encode(random_bytes(32));

            // 7. Write production casino/.env
            $envExample = __DIR__ . '/casino/.env.example';
            $baseEnv = file_exists($envExample) ? file_get_contents($envExample) : (file_exists($envFile) ? file_get_contents($envFile) : '');

            $replacements = [
                'APP_NAME' => $appName,
                'APP_ENV' => 'production',
                'PROMEX_PHP_BINARY' => $cliCheck['ok'] ? $cliCheck['binary'] : ($cliOverride ?: 'php'),
                'APP_KEY' => $generatedAppKey,
                'APP_DEBUG' => 'false',
                'APP_URL' => $appUrl,
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $dbHost,
                'DB_PORT' => $dbPort,
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbUser,
                'DB_PASSWORD' => $dbPass,
                'DB_PREFIX' => 'w_',
                'CACHE_DRIVER' => 'database',
                'SESSION_DRIVER' => 'database',
                'QUEUE_DRIVER' => 'database',
                'WHATSAPP_MODE' => 'promex',
                'PROMEX_HUB_URL' => 'https://clients.377.live/api/service',
                'PROMEX_CEDAR_PUBLIC_ORIGIN' => 'https://clients.377.live',
                'PROMEX_HUB_VERSION_URL' => 'https://clients.377.live/api/service/version',
                'PROMEX_RUNTIME_PROFILE' => 'live',
                'PROMEX_LOCAL_PUBLIC_KEY_FILE' => '',
                'PROMEX_LOCAL_CA_FILE' => '',
            ];

            foreach ($replacements as $k => $v) {
                if (preg_match("/^{$k}=.*/m", $baseEnv)) {
                    $baseEnv = preg_replace("/^{$k}=.*/m", "{$k}=\"{$v}\"", $baseEnv);
                } else {
                    $baseEnv .= "\n{$k}=\"{$v}\"";
                }
            }

            if (file_put_contents(__DIR__ . '/casino/.env', trim($baseEnv) . "\n", LOCK_EX) === false) {
                throw new Exception('Unable to save application configuration. Check folder permissions.');
            }
            if (file_exists(__DIR__ . '/.env')) {
                if (file_put_contents(__DIR__ . '/.env', trim($baseEnv) . "\n", LOCK_EX) === false) {
                    throw new Exception('Unable to update root application configuration.');
                }
            }

            // 8. Apply every versioned schema change before activation.
            [, $console] = promexInstallerLaravel(__DIR__);
            $migrationStatus = $console->call('migrate', ['--force' => true]);
            if ($migrationStatus !== 0) {
                throw new RuntimeException("Database migrations failed:\n" . trim($console->output()));
            }
            $pdo->prepare("UPDATE `w_users` SET `must_change_password` = 1, `preferred_login_method` = 'password' WHERE `id` = ?")
                ->execute([$adminId]);

            // 9. Verify the signed license and bind the protected-service credential.
            if ($licenseKey !== '') {
            $licenseStatus = \VanguardLTE\Services\LicenseService::getStatus(true);
            if (($licenseStatus['status'] ?? '') !== 'active') {
                throw new RuntimeException('License activation was denied: ' . ($licenseStatus['message'] ?? 'verification failed'));
            }
            \VanguardLTE\Services\PromexInstallationService::ensureActivated($licenseKey, $appName);
            }

            // 10. Create Lock File only after schema and license activation succeed.
            if (file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s') . " for " . $appUrl . "\n", LOCK_EX) === false) {
                throw new Exception('Unable to lock the installation. Check folder permissions.');
            }
            $isInstalled = true;
            $message = "Installation completed. Sign in with admin / 123456, then choose a permanent password before continuing.";
            $messageType = "success";

            // 11. Remove installation-only files after configuration and lock are safely written.
            $cleanupResult = \VanguardLTE\Support\InstallerCleanup::run(__DIR__);

        } catch (Exception $e) {
            $message = "Installation Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promex Gaming Suite v2.0.0 - 1-Click Installer</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #f8fafc; --muted: #94a3b8; --accent: #10b981; --primary: #0ea5e9; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 40px 16px; display: flex; justify-content: center; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; max-width: 680px; width: 100%; padding: 36px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.4); }
        .logo-wrap { text-align: center; margin-bottom: 24px; }
        .logo-wrap h1 { margin: 8px 0 0 0; font-size: 24px; color: var(--primary); letter-spacing: -0.5px; }
        .logo-wrap p { margin: 4px 0 0 0; color: var(--muted); font-size: 14px; }
        .badge { display: inline-block; padding: 4px 10px; background: rgba(16, 185, 129, 0.15); color: #34d399; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; }
        .section-title { font-size: 15px; font-weight: 700; color: #e2e8f0; margin: 24px 0 12px 0; padding-bottom: 6px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 13px; font-weight: 500; color: #cbd5e1; margin-bottom: 6px; }
        input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 10px 14px; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: var(--text); font-size: 14px; }
        input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2); }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn-submit { width: 100%; padding: 14px; background: var(--accent); color: white; border: none; border-radius: 6px; font-weight: 700; font-size: 16px; cursor: pointer; margin-top: 24px; transition: background 0.2s; }
        .btn-submit:hover { background: #059669; }
        .alert { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
        .alert-success { background: #064e3b; color: #a7f3d0; border: 1px solid #059669; }
        .alert-error { background: #7f1d1d; color: #fecaca; border: 1px solid #dc2626; }
        .check-item { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px dashed #334155; }
        .check-pass { color: #34d399; font-weight: 600; }
        .check-fail { color: #f87171; font-weight: 600; }
        .action-links { display: flex; gap: 12px; margin-top: 20px; }
        .btn-link { flex: 1; text-align: center; padding: 12px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-accent { background: var(--accent); color: white; }
    </style>
</head>
<body>

<div class="card">
    <div class="logo-wrap">
        <span class="badge">Laravel 13 • Turnkey Edition</span>
        <h1>Promex Gaming Suite v2.0.0</h1>
        <p>1-Click Turnkey Installation & Setup Wizard</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($isInstalled): ?>
        <div style="text-align: center; padding: 20px 0;">
            <div style="font-size: 48px; margin-bottom: 12px;">🎉</div>
            <h2 style="color: #34d399; margin: 0 0 10px 0;">Platform Installed & Ready!</h2>
            <p style="color: var(--muted); font-size: 14px; max-width: 480px; margin: 0 auto 24px auto;">
                Database has been imported, administrator credentials configured, and security keys generated.
            </p>

            <div class="action-links">
                <a href="/liteback" class="btn-link btn-accent">Open Liteback Admin Console &rarr;</a>
                <a href="/" class="btn-link btn-primary">Visit Casino Lobby &rarr;</a>
            </div>

            <?php if ($cleanupResult !== null && empty($cleanupResult['failed'])): ?>
                <p style="font-size: 13px; color: #34d399; margin-top: 24px;">
                    Final cleanup complete: installer, SQL templates and installation ZIP removed.
                </p>
            <?php else: ?>
                <p style="font-size: 13px; color: #fbbf24; margin-top: 24px;">
                    Installation is locked. Finish removing the installer and setup files.
                    <?php if (!empty($cleanupResult['failed'])): ?>
                        Could not remove: <?= htmlspecialchars(implode(', ', $cleanupResult['failed']), ENT_QUOTES, 'UTF-8') ?>.
                        Check file permissions, then retry or remove these files through your hosting file manager.
                    <?php endif; ?>
                </p>
                <?php if (is_file(__FILE__)): ?>
                    <form method="POST">
                        <input type="hidden" name="installer_csrf" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="finish_cleanup">
                        <button type="submit" class="btn-submit">Finish installation cleanup</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Server Health Checklist -->
        <div class="section-title"><span>⚙️</span> System Requirements</div>
        <div style="margin-bottom: 16px;">
            <?php foreach ($requirements as $req => $met): ?>
                <div class="check-item">
                    <span><?= htmlspecialchars($req) ?></span>
                    <span class="<?= $met ? 'check-pass' : 'check-fail' ?>"><?= $met ? '✓ OK' : '✗ Failed' ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="">
            <div class="check-item" style="display:block; margin-bottom:16px;">
                <strong>PHP CLI — optional for managed updates</strong>
                <p><?= $cliCheck['ok'] ? '✓ Ready for managed updates.' : '⚠ Managed updates are not ready. You can still install and use the application.' ?></p>
                <p>The managed updater uses PHP command-line processes for patch migrations, cache cleanup and application checks. This is separate from your website PHP. This check tests its version, packaged dependencies and extensions; it does not block installation.</p>
                <p>If CLI is unavailable, download releases and apply updates manually following their file and database instructions, or ask your host to configure PHP CLI to enable managed updates. Manual updates do not use a ZIP upload in this panel.</p>
                <p><?= htmlspecialchars($cliCheck['message'], ENT_QUOTES, 'UTF-8') ?> When verified, the executable is saved for the updater; check it again if your host changes PHP.</p>
            </div>
            <input type="hidden" name="installer_csrf" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <!-- Application Config -->
            <div class="section-title"><span>🌐</span> Site Settings</div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Platform Name</label>
                    <input type="text" name="app_name" value="<?= htmlspecialchars($_POST['app_name'] ?? 'Promex Gaming Suite') ?>" required>
                </div>
                <div class="form-group">
                    <label>App URL</label>
                    <input type="text" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? ((($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'])) ?>" required>
                </div>
            </div>

            <div class="section-title"><span>🔐</span> Promex License</div>
            <div class="form-group">
                <label>License Key</label>
                    <input type="password" name="license_key" value="<?= htmlspecialchars($_POST['license_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" maxlength="255" placeholder="Optional — activate protected services now or later">
            </div>
            <p style="color: var(--muted); font-size: 12px; margin-top: -6px;">
                The installer verifies the signed license and activates protected Cedar/API services for this domain. Legacy Compatibility remains off until you explicitly enable and attest local content in Liteback.
            </p>

            <!-- Database Config -->
            <div class="section-title"><span>🗄️</span> MySQL Database Credentials</div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>" required>
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="text" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'casino') ?>" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" placeholder="MySQL password">
                </div>
            </div>

            <!-- Admin Account -->
            <div class="section-title"><span>👤</span> First Administrator Login</div>
            <div class="alert" style="background:#172554; border:1px solid #1d4ed8; color:#dbeafe;">
                Username: <strong>admin</strong><br>
                Temporary password: <strong>123456</strong><br>
                You must replace this password immediately after the first login.
            </div>

            <button type="submit" class="btn-submit" <?= !$allRequirementsMet ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                <?= $allRequirementsMet ? '⚡ Run 1-Click Installation' : '⚠️ Fix System Requirements Above' ?>
            </button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
