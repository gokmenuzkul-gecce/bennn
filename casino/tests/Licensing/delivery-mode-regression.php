<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Foundation\Application;
use VanguardLTE\Services\CedarGameRegistry;
use VanguardLTE\Services\CedarSlotService;
use VanguardLTE\Services\PromexGameDeliveryService;

$app = new Application(dirname(__DIR__, 2));
$delivery = new PromexGameDeliveryService();
$checks = [];
$record = static function (string $name, bool $passed) use (&$checks): void { $checks[] = [$name, $passed]; };
$expect = static function (string $name, callable $callable) use ($record): void {
    try { $callable(); $record($name, false); } catch (RuntimeException) { $record($name, true); }
};
$remoteGame = (object) ['name' => 'Cedarcules', 'source_type' => CedarGameRegistry::SOURCE_TYPE, 'delivery_mode' => 'PROMEX_REMOTE', 'view' => 1];
$retiredGame = (object) ['name' => 'CedarHercules', 'source_type' => CedarGameRegistry::SOURCE_TYPE, 'delivery_mode' => 'LOCAL', 'view' => 1];
$legacy = (object) ['name' => 'LegacySlot', 'source_type' => 'default', 'delivery_mode' => 'LOCAL', 'view' => 1];
$catalog = ['Cedarcules' => ['id' => 'Cedarcules']];

$delivery->assertSelectable($remoteGame, PromexGameDeliveryService::REMOTE, $catalog);
$record('entitled Cedar title can select Promex Remote', true);
$expect('protected remote-only title cannot select local delivery',
    fn () => $delivery->assertSelectable($remoteGame, PromexGameDeliveryService::LOCAL, $catalog));
$expect('retired Cedar Hercules cannot select local delivery',
    fn () => $delivery->assertSelectable($retiredGame, PromexGameDeliveryService::LOCAL, $catalog));
$expect('non-Cedar title cannot select Promex Remote',
    fn () => $delivery->assertSelectable($legacy, PromexGameDeliveryService::REMOTE, $catalog));
$expect('unentitled Cedar title cannot select Promex Remote',
    fn () => $delivery->assertSelectable($remoteGame, PromexGameDeliveryService::REMOTE, []));
$record('effective availability intersects local toggle and Hub catalog',
    $delivery->effectivelyAvailable($remoteGame, $catalog)
    && !$delivery->effectivelyAvailable($remoteGame, [])
    && $delivery->effectivelyAvailable($legacy, []));
$remoteGame->view = 0;
$record('local visibility toggle always wins', !$delivery->effectivelyAvailable($remoteGame, $catalog));

$remotePaths = new ReflectionMethod(CedarSlotService::class, 'remotePaths');
$normalized = $remotePaths->invoke(new CedarSlotService(), [
    'runtime' => '/CedarGames/_runtime/cedar-slot.css?v=5',
    'symbol' => '/CedarGames/Cedarcules/assets/symbols/wild.png',
], 'Cedarcules');
$record('remote initialization maps only Cedar runtime and title assets into the CDN namespace',
    $normalized['runtime'] === '/cedar/runtime/cedar-slot.css?v=5'
    && $normalized['symbol'] === '/cedar/games/Cedarcules/assets/symbols/wild.png');

$controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Web/Liteback/GameController.php');
$frontend = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Web/Frontend/GamesController.php');
$view = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/frontend/games/promex-remote.blade.php');
$deliverySource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/PromexGameDeliveryService.php');
$legacySource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/LegacyCompatibilityService.php');
$gameAdmin = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/liteback/games/index.blade.php');
$gameLobby = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/frontend/Minimal/games/list.blade.php');
$litebackLayout = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/liteback/layout.blade.php');
$routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
$updater = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/UpdaterService.php');
$registry = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/CedarGameRegistry.php');
$htaccess = (string) file_get_contents(dirname(__DIR__, 3) . '/.htaccess');
$record('operator controls and launch bridge are wired without arbitrary remote URLs',
    str_contains($controller, 'updateDelivery') && str_contains($controller, 'bulkDelivery')
    && str_contains($frontend, 'PromexCedarCatalogService')
    && str_contains($frontend, '(string) $launch[\'operator_domain\']')
    && str_contains($frontend, 'Content-Security-Policy')
    && str_contains($view, "event.origin !== remoteOrigin")
    && str_contains($view, "message.launch_token !== launchToken"));
$record('licensed legacy titles support an explicit same-origin CDN proxy mode',
    str_contains($deliverySource, 'LegacyCompatibilityService::SOURCE_TYPE')
    && str_contains($deliverySource, 'LicenseService::canUseCdnGames()')
    && str_contains($legacySource, 'setRemoteDelivery')
    && str_contains($controller, 'setRemoteDelivery')
    && str_contains($gameAdmin, "['cedar_game', 'legacy_compat']")
    && str_contains($htaccess, 'promex-legacy-remote/$1.remote')
    && str_contains($htaccess, 'https://clients.377.live/games/$1/$2'));
$cedarGamesTab = strpos($gameLobby, '🚀 CEDAR Games');
$cedarCardsTab = strpos($gameLobby, '♠ CEDAR Cards');
$cedarRemakesTab = strpos($gameLobby, '🌲 CEDAR Remakes');
$record('all three CEDAR lobby tabs remain adjacent and ordered',
    $cedarGamesTab !== false && $cedarCardsTab !== false && $cedarRemakesTab !== false
    && $cedarGamesTab < $cedarCardsTab && $cedarCardsTab < $cedarRemakesTab
    && str_contains($gameLobby, "['cedar_games', 'cedar_cards', 'cedar_remakes']"));
$record('Liteback separates CEDAR management and supports catalog pull/install',
    str_contains($routes, "liteback.cedar.index")
    && str_contains($routes, "liteback.cedar.sync")
    && str_contains($litebackLayout, '<p>CEDAR</p>')
    && str_contains($gameAdmin, 'Pull & Install Games')
    && str_contains($updater, 'syncRemoteCatalog')
    && str_contains($registry, "RETIRED_GAMES = ['CedarHercules']"));

$failed = array_filter($checks, static fn (array $check): bool => !$check[1]);
foreach ($checks as [$name, $passed]) echo ($passed ? 'PASS' : 'FAIL') . ": {$name}\n";
exit($failed ? 1 : 0);
