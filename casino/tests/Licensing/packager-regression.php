<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../../tools/packaging/verify_prepack.php';
$root = realpath(__DIR__ . '/../../..');
$archive = sys_get_temp_dir() . '/promex-prepack-test-' . bin2hex(random_bytes(8)) . '.zip';
$required = ['.htaccess', 'install.php', 'install.sql', 'UPGRADE.md', 'PATCHES.md', 'VERSION',
    'casino/app/Services/PatchPackage.php', 'casino/app/Services/PatchManager.php', 'casino/app/Services/ManualBackupService.php',
    'casino/app/Http/Controllers/Web/Liteback/MaintenanceController.php', 'casino/resources/views/liteback/maintenance/index.blade.php',
    'casino/config/patches.php', 'casino/composer.json', 'casino/composer.lock',
    'casino/vendor/autoload.php', 'casino/vendor/composer/installed.php',
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
if (!str_contains(file_get_contents($root . '/.htaccess'), 'RewriteRule ^js/mock-websocket\\.js$ js/promex-legacy-bridge.js')) {
    throw new RuntimeException('Legacy mock-websocket URL does not map to the plain JavaScript bridge');
}
try {
    $zip = new ZipArchive(); $zip->open($archive, ZipArchive::CREATE);
    foreach ($required as $name) { $zip->addFile($root . '/' . $name, $name); }
    $zip->close();
    verifyPrepack($archive, $root);
    foreach (['js/mock-websocket.js', 'casino/.env', 'casino/storage/framework/license.cert',
        'casino/storage/framework/sessions/session-id', 'casino/bootstrap/cache/config.php', 'js/ws-bridge.js', 'js/ws-bridge.wasm',
        'localscripts/licensing_hub/private.key', 'old.zip', 'casino/routes/web.php.bak', 'tools/wasm/packet-codec.wat',
        'games/ClaimedProvider/index.html', 'casino/public/games/ClaimedProvider/index.html',
        'public/originals/ClaimedProvider/index.html',
        'casino/app/Games/ClaimedProvider/Server.php', 'casino/app/Games/CedarMath.php',
        'casino/app/Services/CedarLegacyGameService.php', 'CedarGames/Cedarcules/math.json',
        'clients377live/local/runtime-secrets.json'] as $forbidden) {
        $zip->open($archive); $zip->addFromString($forbidden, 'test-only'); $zip->close();
        $rejected = false;
        try { verifyPrepack($archive, $root); } catch (RuntimeException $e) { $rejected = true; }
        if (!$rejected) { throw new RuntimeException('Forbidden archive entry accepted: ' . $forbidden); }
        $zip->open($archive); $zip->deleteName($forbidden); $zip->close();
    }
    echo "PASS: package rejects provider payloads, credentials, runtime state, authority source and retired WASM\n";
} finally { if (is_file($archive)) unlink($archive); }
