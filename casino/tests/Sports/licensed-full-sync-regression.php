<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Sports/Services/SportsOddsSyncService.php');
$promexProvider = file_get_contents($root . '/app/Sports/Providers/PromexLicensedProvider.php');
$registry = file_get_contents($root . '/app/Sports/Providers/SportsProviderRegistry.php');
$command = file_get_contents($root . '/app/Console/Commands/Sports/SyncAll.php');
$dashboard = file_get_contents($root . '/app/Http/Controllers/Web/Liteback/SportsDashboardController.php');
$dashboardView = file_get_contents($root . '/resources/views/liteback/sports/dashboard.blade.php');
$settingsView = file_get_contents($root . '/resources/views/liteback/sports/settings.blade.php');
$settingsController = file_get_contents($root . '/app/Http/Controllers/Web/Liteback/SportsControlController.php');
$routes = file_get_contents($root . '/routes/web.php');
$sportsView = file_get_contents($root . '/resources/views/frontend/Minimal/sports/index.blade.php');
$frontendLayout = file_get_contents($root . '/resources/views/frontend/Minimal/layouts/clean.blade.php');

$checks = [
    'full sync routes PROMEX through installation-authenticated Hub' => str_contains($registry, "PromexLicensedProvider::KEY")
        && str_contains($promexProvider, "PromexInstallationService::signedHeaders('GET', \$path)")
        && str_contains($service, '->provider()'),
    'full sync never requires a customer API key in PROMEX mode' => str_contains($command, "if (\$this->syncService->getProvider() === 'promex')")
        && str_contains($command, 'syncAll((array) $this->option(\'sports\'))'),
    'feed import updates Battle Odds and advanced sportsbook records' => str_contains($service, 'SportsMatch::updateOrCreate')
        && str_contains($service, 'saveMarketAndOutcomes'),
    'new feed leagues default enabled' => str_contains($service, "'status'             => 1"),
    'every feed run enables included leagues' => str_contains($service, '$league->status = 1;')
        && !str_contains($service, 'operator_status_override'),
    'full-sync runner accepts sport checkboxes' => str_contains($command, '{--sports=*')
        && str_contains($dashboardView, 'name="sports[]"'),
    'dashboard reports failed Artisan exit codes as failures' => str_contains($dashboard, 'if ($exitCode !== 0)'),
    'sports settings share the PROMEX/custom provider choice' => str_contains($settingsView, 'name="sportsbook_api_provider"')
        && str_contains($settingsView, 'name="odds_api_key"')
        && !str_contains($settingsView, 'name="ods_api_key"'),
    'active-odds reset is guarded and preserves stored records' => str_contains($routes, 'liteback.sports.odds.clear_active')
        && str_contains($dashboardView, "confirm('Hide every active site odd?")
        && str_contains($service, "SportsMatch::where('status', 'upcoming')")
        && str_contains($service, "update(['status' => 'hidden'])")
        && str_contains($service, "Game::whereIn('id', \$gameIds)->update(['status' => 3])")
        && !str_contains($service, "DB::table('sports_bets')->delete"),
    'feed sync reactivates odds hidden by the reset' => str_contains($service, '$game->status = 1;')
        && substr_count($service, "'locked' => 0") >= 2,
    'Battle Odds displays browser-local date and time' => str_contains($sportsView, 'data-format="full"')
        && str_contains($sportsView, "format('M d, Y · h:i A')")
        && str_contains($frontendLayout, "year: 'numeric'")
        && str_contains($frontendLayout, "hour: '2-digit'")
        && str_contains($frontendLayout, "minute: '2-digit'"),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' licensed full-sync checks' . PHP_EOL;
