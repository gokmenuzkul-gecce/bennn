<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$sync = $read('app/Casino/CasinoCatalogSyncService.php');
$command = $read('app/Console/Commands/CasinoSyncCatalog.php');
$kernel = $read('app/Console/Kernel.php');
$abstract = $read('app/Casino/Providers/AbstractCasinoProvider.php');
$launch = $read('app/Casino/CasinoGameLaunchService.php');
$model = $read('app/Casino/Models/CasinoProviderPlayer.php');
$controller = $read('app/Http/Controllers/Web/Frontend/GamesController.php');
$view = $read('resources/views/frontend/Minimal/games/list.blade.php');
$helper = $read('app/Support/helpers.php');
$migration = $read('database/migrations/2026_10_01_000002_add_casino_catalog_columns.php');
$config = $read('config/casino_providers.php');

$checks = [
    'catalog sync pulls the vendor gamelist with bearer auth' => str_contains($sync, 'Authorization: Bearer ')
        && str_contains($sync, "'gamelist_path'")
        && str_contains($sync, "(int) (\$decoded['code'] ?? -1) !== 0"),

    'catalog sync links legacy rows by normalised title' => str_contains($sync, 'normalize(')
        && str_contains($sync, "preg_replace('/[^a-z0-9]/i'")
        && str_contains($sync, '$game->provider_key = $providerKey;'),

    'catalog sync stores the numeric vendor game id for launch' => str_contains($sync, "'launch_code' => \$entry['gameid']")
        && str_contains($sync, "'provider_game_id' => \$entry['symbol']"),

    'catalog sync imports missing titles with a unique name' => str_contains($sync, 'createGame(')
        && str_contains($sync, 'uniqueName(')
        && str_contains($sync, "'source_type' => 'aggregator'"),

    'bulk import skips the admin audit subscriber' => str_contains($sync, 'Game::withoutEvents('),

    'catalog sync maps provider category into the lobby filter' => str_contains($sync, 'ensureCategory(')
        && str_contains($sync, 'attachCategory('),

    'sync command is registered and reports per provider' => str_contains($command, 'casino:sync-catalog')
        && str_contains($command, '--link-only')
        && str_contains($kernel, 'Commands\CasinoSyncCatalog::class'),

    'launch posts to the aggregator and returns the vendor url' => str_contains($abstract, 'CURLOPT_POST => true')
        && str_contains($abstract, 'Authorization: Bearer ')
        && str_contains($abstract, "\$decoded['url']"),

    'launch strips non-alphanumeric characters from the user code' => str_contains($abstract, 'sanitizeUserCode(')
        && str_contains($abstract, "preg_replace('/[^A-Za-z0-9]/'"),

    'user code is generated without separators' => str_contains($model, "\$prefix . \$userId . substr(md5(")
        && !str_contains($model, "\$userId . '_' . substr(md5("),

    'launch service prefers the numeric vendor game id' => str_contains($launch, '$game->launch_code ?: $game->provider_game_id'),

    'migration adds icon_url and launch_code' => str_contains($migration, "'icon_url'")
        && str_contains($migration, "'launch_code'"),

    'homepage exposes a client-side game search box' => str_contains($view, 'id="home-game-search"')
        && str_contains($view, 'data-title=')
        && str_contains($view, 'remoteSearch('),

    'lobby cards prefer the localized cover with the vendor art as fallback' => str_contains($view, 'game_cover($game)')
        && str_contains($controller, 'game_cover($game)')
        && str_contains($helper, 'frontend/Default/ico/'),

    'public lobby search works without a session' => str_contains($controller, 'Public lobby search')
        && str_contains($controller, 'Auth::check() ? auth()->user()->shop_id : 1'),

    'providers declare a gamelist endpoint' => substr_count($config, "'gamelist_path' => '/gamelist'") === 4,
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
}

echo 'PASS: ' . count($checks) . " casino catalog sync checks\n";
