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

$contract = $read('app/Sports/Providers/Contracts/SportsOddsProvider.php');
$promex = $read('app/Sports/Providers/PromexLicensedProvider.php');
$custom = $read('app/Sports/Providers/TheOddsApiProvider.php');
$registry = $read('app/Sports/Providers/SportsProviderRegistry.php');
$service = $read('app/Sports/Services/SportsOddsSyncService.php');
$oddsService = $read('app/Services/OddsApiService.php');
$polymarket = $read('app/Services/PolymarketService.php');
$controller = $read('app/Http/Controllers/Web/Liteback/SportsProviderController.php');
$view = $read('resources/views/liteback/sports/providers.blade.php');
$layout = $read('resources/views/liteback/layout.blade.php');
$routes = $read('routes/web.php');
$provider = $read('app/Providers/AppServiceProvider.php');

$checks = [
    'provider contract defines the adapter surface' => str_contains($contract, 'interface SportsOddsProvider')
        && str_contains($contract, 'public function key(): string;')
        && str_contains($contract, 'public function fetchFixtures(?array $sportKeys = null): array;')
        && str_contains($contract, 'public function testConnectivity(): array;')
        && str_contains($contract, 'public function requiresLicense(): bool;'),

    'PROMEX adapter owns the licensed hub call and entitlement gate' => str_contains($promex, 'implements SportsOddsProvider')
        && str_contains($promex, "PromexInstallationService::signedHeaders('GET', \$path)")
        && str_contains($promex, 'LicenseService::canUseCentralOdds()'),

    'custom adapter owns the operator key and never needs a license' => str_contains($custom, 'implements SportsOddsProvider')
        && str_contains($custom, "settings('odds_api_key'")
        && str_contains($custom, 'return false;'),

    'registry resolves the saved provider and defaults to PROMEX' => str_contains($registry, "PromexLicensedProvider::KEY")
        && str_contains($registry, "TheOddsApiProvider::KEY")
        && str_contains($registry, "settings('sportsbook_api_provider'")
        && str_contains($registry, 'selectedKey')
        && str_contains($registry, 'function catalog()'),

    'sync service depends on the contract, not a provider name' => str_contains($service, 'SportsOddsProvider')
        && str_contains($service, '->provider()')
        && str_contains($service, '->fetchFixtures(')
        && !str_contains($service, "=== 'promex'")
        && !str_contains($service, 'fetchPromexOdds'),

    'Battle Odds importer is provider-agnostic' => str_contains($oddsService, 'SportsProviderRegistry')
        && str_contains($oddsService, '->fetchFixtures()')
        && !str_contains($oddsService, 'seedMockFixtures'),

    'no fake game data is generated anywhere' => !str_contains($oddsService, 'seedMockFixtures')
        && !str_contains($oddsService, 'mock_')
        && !str_contains($polymarket, 'getMockSearchResults')
        && !str_contains($polymarket, 'poly_mock'),

    'registry is wired into the container' => str_contains($provider, 'SportsProviderRegistry::class'),

    'admin panel lists, selects and tests providers' => str_contains($controller, 'public function index()')
        && str_contains($controller, 'public function select(Request $request)')
        && str_contains($controller, 'public function test(Request $request)')
        && str_contains($view, "route('liteback.sports.providers.select')")
        && str_contains($view, "route('liteback.sports.providers.test')")
        && str_contains($layout, "route('liteback.sports.providers')"),

    'provider admin routes are registered under liteback auth' => str_contains($routes, "liteback.sports.providers")
        && str_contains($routes, 'SportsProviderController@index')
        && str_contains($routes, 'SportsProviderController@test'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' sportsbook provider adapter checks' . PHP_EOL;
