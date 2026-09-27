<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function verifyPrepack(string $archive, string $root): void
{
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) { throw new RuntimeException('Cannot read prepack'); }
    try {
        $required = ['.htaccess', 'install.php', 'install.sql', 'UPGRADE.md', 'PATCHES.md', 'VERSION', 'LICENSE',
            'casino/app/Support/InstallerPhpCli.php',
            'casino/app/Services/WhatsAppService.php', 'casino/app/Services/DeliveryGatewaySettings.php',
            'casino/app/Http/Controllers/Web/Frontend/Auth/MultiAuthController.php',
            'casino/app/Http/Controllers/Web/Liteback/SystemSettingsController.php',
            'casino/resources/views/liteback/settings/index.blade.php',
            'casino/app/Services/AccountPhoneVerification.php', 'casino/app/Services/AccountSecurityService.php',
            'casino/app/Http/Controllers/Web/Liteback/ProfileController.php', 'casino/resources/views/liteback/profile/password.blade.php',
            'casino/app/Games/DayofDead/SlotSettings.php', 'casino/app/Games/DayofDead/Server.php',
            'casino/resources/views/frontend/games/list/DayofDead.blade.php',
            'casino/app/Services/PatchPackage.php', 'casino/app/Services/PatchManager.php', 'casino/app/Services/ManualBackupService.php',
            'casino/app/Http/Controllers/Web/Liteback/MaintenanceController.php', 'casino/resources/views/liteback/maintenance/index.blade.php',
            'casino/config/patches.php', 'casino/composer.json', 'casino/composer.lock',
            'casino/app/Support/InstallerCleanup.php', 'js/game-session.js', 'js/promex-html-game.js', 'js/promex-legacy-bridge.js',
            'casino/app/Services/LicenseService.php', 'casino/app/Services/SignedLicenseCertificate.php',
            'casino/app/Services/GameLicenseBlockedResponse.php',
            'casino/app/Http/Middleware/ProtectGameRequests.php', 'casino/app/Http/Middleware/VerifyCsrfToken.php',
            'casino/app/Http/Middleware/InjectGameHomeButton.php', 'casino/config/licensing.php',
            'casino/routes/web.php', 'casino/app/Http/Controllers/Web/Frontend/GamesController.php',
            'casino/app/Http/Controllers/Web/Frontend/ProviderCompatibilityController.php',
            'casino/app/Services/LegacyCompatibilityService.php', 'casino/config/legacy.php',
            'casino/app/Services/PromexInstallationService.php', 'casino/app/Services/PromexCedarCatalogService.php',
            'casino/app/Services/PromexCedarService.php', 'casino/app/Services/PromexCedarSettlementService.php',
            'casino/app/Services/PromexGameDeliveryService.php',
            'casino/app/Console/Commands/InjectMockWebSocket.php',
            'casino/database/migrations/2026_09_21_000001_add_game_delivery_mode.php',
            'casino/database/migrations/2026_09_21_000002_add_legacy_compatibility_fields.php',
            'casino/resources/views/frontend/games/promex-remote.blade.php',
            'casino/resources/views/frontend/license-blocked.blade.php', 'js/game-license-blocked.js'];
        foreach (['casino/app', 'casino/resources/views/frontend/games', 'casino/lang', 'casino/views'] as $tree) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $tree, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $name = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if (!str_ends_with($name, '.php')) continue;
                $bytes = $zip->getFromName($name);
                if ($bytes === false || !hash_equals(hash_file('sha256', $file->getPathname()), hash('sha256', $bytes))) throw new RuntimeException('Missing application PHP/view: ' . $name);
            }
        }
        $sql = $zip->getFromName('install.sql');
        foreach (['casino/vendor/autoload.php', 'casino/vendor/composer/installed.php', 'casino/vendor/composer/installed.json'] as $runtime) {
            if ($zip->locateName($runtime) === false) throw new RuntimeException('Missing dependency runtime: ' . $runtime);
        }
        if (!is_string($sql) || str_contains($sql, '.test') || str_contains($sql, 'BEGIN PRIVATE KEY')) throw new RuntimeException('Local-only or private data in installer SQL.');
        foreach ($required as $name) {
            $bytes = $zip->getFromName($name);
            if ($bytes === false || !hash_equals(hash_file('sha256', $root . '/' . $name), hash('sha256', $bytes))) {
                throw new RuntimeException('Missing or stale release file: ' . $name);
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if (str_ends_with($name, '/')) { continue; }
            if (str_starts_with($name, 'casino/storage/') && !str_ends_with($name, '/.gitkeep')) throw new RuntimeException('Runtime state in prepack: ' . $name);
            if (preg_match('~^(?:clients377live|CedarGames)/~', $name)) {
                throw new RuntimeException('Protected Cedar source in customer package: '.$name);
            }
            if (preg_match('~(^|/)(\.env(?:\.[^/]*)?|\.git)(/|$)|^localscripts/|^tools/|^casino/tests/|\.(?:zip|bak|old|orig|save|swp)$|^js/(?:mock-websocket|ws-bridge)\.(?:js|wasm)$|^casino/bootstrap/cache/.*\.php$|^casino/storage/framework/license\.cert(?:\.|$)|^casino/storage/framework/(cache|sessions|views)/(?!.*\.gitkeep$)|^casino/storage/app/updates/~', $name)) {
                throw new RuntimeException('Forbidden development/runtime file in prepack: ' . $name);
            }
            if (preg_match('~^casino/vendor/.+/(?:tests?|test_files|docs?)/~i', $name)
                || preg_match('~\.(?:key|p12|pfx|sqlite|db)$~i', $name)) {
                throw new RuntimeException('Forbidden vendor fixture or sensitive file type in prepack: ' . $name);
            }
            if (preg_match('~^(?:games|public/games|public/originals|casino/public/games)/~', $name)) {
                throw new RuntimeException('Operator/provider frontend payload in clean prepack: ' . $name);
            }
        }
    } finally { $zip->close(); }
    echo "PASS: prepack required-file hashes and runtime/source exclusions\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    verifyPrepack($argv[1] ?? (__DIR__ . '/../../localscripts/dist/promex-gaming-suite-v2.0-cpanel.zip'), realpath(__DIR__ . '/../..'));
}
