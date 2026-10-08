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
$typeMigration = $read('database/migrations/2026_10_07_000001_add_game_type_to_games.php');
$game = $read('app/Game.php');
$httpKernel = $read('app/Http/Kernel.php');
$gzip = $read('app/Http/Middleware/GzipResponse.php');
$layout = $read('resources/views/frontend/Minimal/layouts/clean.blade.php');
$header = $read('resources/views/frontend/Minimal/partials/site-header.blade.php');
$navbar = $read('resources/views/frontend/Minimal/partials/navbar.blade.php');
$nginx = $read('deploy/nginx-casino.conf.example');
$config = $read('config/casino_providers.php');
$logoGen = $read('scripts/gen_provider_logos.py');
$imageOptimizer = $read('scripts/optimize_game_images.py');
$logoSvgs = glob($root . '/public/frontend/Default/provider-logos/*.svg') ?: [];
$logoSamples = array_map(static fn (string $p): string => (string) file_get_contents($p), $logoSvgs);

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

    'games table gains a game_type column via its own migration' => str_contains($typeMigration, "string('game_type', 24)")
        && str_contains($typeMigration, "->after('provider_game_id')"),

    'catalog sync persists the vendor game type and links type hubs' => str_contains($sync, '$game->game_type = $entry[\'type\']')
        && str_contains($sync, "'live' => \$this->ensureCategory('live_casino', 'Canlı Casino')")
        && str_contains($sync, 'attachCategory'),

    'game model exposes the type vocabulary and live predicate' => str_contains($game, 'public const GAME_TYPES')
        && str_contains($game, "'live' => 'Canlı Casino'")
        && str_contains($game, 'public function isLive(): bool'),

    'lobby badge distinguishes live tables from slots' => str_contains($view, '$isLive = $isAggregator && $game->isLive()')
        && str_contains($view, 'CANLI')
        && str_contains($view, "'bg-rose-500/25 text-rose-200 border border-rose-400/40'"),

    'lobby splits slots and live tables into their own blocks' => str_contains($view, 'id="slots-grid"')
        && str_contains($view, 'id="live-grid"')
        && str_contains($view, 'id="slots-rest-data"')
        && str_contains($view, 'id="live-rest-data"'),

    'lobby renders only the first four rows and defers the rest' => str_contains($view, '$rowsPerPage = 4')
        && str_contains($view, '$initialCards = $rowsPerPage * 7')
        && str_contains($view, '$slotPayload = $slotRest->map($cardMeta)')
        && str_contains($view, '$livePayload = $liveRest->map($cardMeta)')
        && str_contains($view, 'appendChunk'),

    'deferred cards hydrate four rows per click, never on scroll' => str_contains($view, 'var ROWS_PER_CLICK = 4')
        && str_contains($view, "buildBlock('slots', 'OYUN')")
        && str_contains($view, "buildBlock('live', 'MASA')")
        && str_contains($view, 'Daha Fazla Oyun Yükle')
        && !str_contains($view, 'rootMargin: \'600px 0px\''),

    'phone top bar exposes a wallet / account cluster' => str_contains($header, 'site-nav-mobile')
        && str_contains($header, 'site-nav-wallet-amount')
        && str_contains($layout, '.site-nav-mobile')
        && str_contains($layout, '.site-nav-wallet'),

    'phone bottom dock uses the app-dock tab bar' => str_contains($layout, '.app-dock-link')
        && str_contains($layout, '.app-dock-link.is-active')
        && str_contains($navbar, 'class="app-dock"')
        && str_contains($navbar, "app-dock-link {{"),

    'gzip middleware is registered and scoped to the built-in server' => str_contains($httpKernel, 'GzipResponse')
        && str_contains($gzip, "PHP_SAPI !== 'cli-server'")
        && str_contains($gzip, "headers->set('Content-Encoding', 'gzip')"),

    'gzip middleware leaves already-encoded responses alone' => str_contains($gzip, "has('Content-Encoding')")
        && str_contains($gzip, 'BinaryFileResponse'),

    'nginx vhost gzips the compressible types' => str_contains($nginx, 'gzip on;')
        && str_contains($nginx, 'application/javascript')
        && str_contains($nginx, 'image/svg+xml'),

    'off-screen game cards skip layout until scrolled into view' => str_contains($layout, 'content-visibility: auto')
        && str_contains($layout, 'contain-intrinsic-size'),

    'navbar live entry opens the consolidated live hub, not one studio' => str_contains($header, "'category1' => 'live_casino'")
        && !str_contains($header, "'category1' => 'evolution'"),

    'every provider logo ships the modern badge artwork' => count($logoSvgs) >= 27
        && count(array_filter($logoSamples, static fn (string $s): bool =>
            str_contains($s, 'stroke="url(#g)"') && str_contains($s, 'font-weight="800"'))) === count($logoSvgs)
        && count(array_filter($logoSamples, static fn (string $s): bool => str_contains($s, 'url(#w)'))) === 0,

    'provider logo generator keeps the brand palette and glyph set' => str_contains($logoGen, 'BRANDS = {')
        && str_contains($logoGen, 'linearGradient id="g"')
        && str_contains($logoGen, '"slot"') && str_contains($logoGen, '"crown"'),

    'cover art is normalised to the card ratio and encoded progressively' => str_contains($imageOptimizer, 'TARGET_W, TARGET_H = 300, 400')
        && str_contains($imageOptimizer, 'optimize=True, progressive=True')
        && str_contains($imageOptimizer, 'SKIP_BYTES'),

    'cover downloader stores the same normalised size' => str_contains($read('scripts/localize_game_images.php'), 'imageinterlace($dst, true)')
        && str_contains($read('scripts/localize_game_images.php'), 'imagejpeg($dst, $dest, 80)'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
}

echo 'PASS: ' . count($checks) . " casino catalog sync checks\n";
