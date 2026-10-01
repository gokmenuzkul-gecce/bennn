<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/resources/views/liteback/settings/index.blade.php');
$layout = file_get_contents($root . '/resources/views/liteback/layout.blade.php');
$controller = file_get_contents($root . '/app/Http/Controllers/Web/Liteback/SystemSettingsController.php');
$service = file_get_contents($root . '/app/Sports/Services/SportsOddsSyncService.php');
$registry = file_get_contents($root . '/app/Sports/Providers/SportsProviderRegistry.php');
$promexProvider = file_get_contents($root . '/app/Sports/Providers/PromexLicensedProvider.php');

$checks = [
    'sportsbook offers PROMEX and custom providers' => str_contains($view, 'name="sportsbook_api_provider"')
        && str_contains($view, 'value="promex"')
        && str_contains($view, 'value="custom"'),
    'Liteback renders page-specific scripts after jQuery' => str_contains($layout, "@yield('scripts')")
        && strpos($layout, 'jquery.min.js') < strpos($layout, "@yield('scripts')"),
    'PROMEX is the fresh-install sportsbook default' => str_contains($view, "settings('sportsbook_api_provider', 'promex')")
        && str_contains($registry, "PromexLicensedProvider::KEY"),
    'licensed value proposition is visible' => str_contains($view, 'managed API access is included at no extra API cost with an active license'),
    'controller validates and saves provider choice' => str_contains($controller, "'sportsbook_api_provider' => 'required|in:promex,custom'")
        && str_contains($controller, "'sportsbook_api_provider',"),
    'PROMEX test uses installation authentication' => str_contains($controller, "PromexInstallationService::signedHeaders('GET', \$path)")
        || str_contains($promexProvider, "PromexInstallationService::signedHeaders('GET', \$path)"),
    'connectivity result describes PRE-MATCH odds without cache language' => str_contains($controller, 'PRE-MATCH odds available')
        || str_contains($promexProvider, 'PRE-MATCH odds available'),
    'runtime sync resolves the saved provider through the registry' => str_contains($registry, "settings('sportsbook_api_provider'")
        && str_contains($registry, 'selectedKey')
        && str_contains($service, '->provider()')
        && str_contains($service, '->fetchFixtures('),
    'licensed path fails closed without entitlement' => str_contains($promexProvider, 'LicenseService::canUseCentralOdds()')
        && str_contains($promexProvider, 'requires an active sportsbook license'),
    'custom provider keeps the operator key behind the adapter' => str_contains(
        file_get_contents($root . '/app/Sports/Providers/TheOddsApiProvider.php'),
        "settings('odds_api_key'"
    ) && !str_contains($service, "settings('odds_api_key'"),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' sportsbook provider setting checks' . PHP_EOL;
