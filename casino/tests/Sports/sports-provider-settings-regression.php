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
$service = file_get_contents($root . '/app/Services/OddsApiService.php');

$checks = [
    'sportsbook offers PROMEX and custom providers' => str_contains($view, 'name="sportsbook_api_provider"')
        && str_contains($view, 'value="promex"')
        && str_contains($view, 'value="custom"'),
    'Liteback renders page-specific scripts after jQuery' => str_contains($layout, "@yield('scripts')")
        && strpos($layout, 'jquery.min.js') < strpos($layout, "@yield('scripts')"),
    'PROMEX is the fresh-install sportsbook default' => str_contains($view, "settings('sportsbook_api_provider', 'promex')"),
    'licensed value proposition is visible' => str_contains($view, 'managed API access is included at no extra API cost with an active license'),
    'controller validates and saves provider choice' => str_contains($controller, "'sportsbook_api_provider' => 'required|in:promex,custom'")
        && str_contains($controller, "'sportsbook_api_provider',"),
    'PROMEX test uses installation authentication' => str_contains($controller, "PromexInstallationService::signedHeaders('GET', \$path)"),
    'connectivity result describes PRE-MATCH odds without cache language' => str_contains($controller, 'PRE-MATCH odds available')
        && !str_contains($controller, 'cached fixtures available'),
    'runtime sync respects saved provider choice' => str_contains($service, "settings('sportsbook_api_provider', 'promex')")
        && str_contains($service, "if (\$provider === 'promex')"),
    'licensed path fails closed without entitlement' => str_contains($service, 'LicenseService::canUseCentralOdds()')
        && str_contains($service, 'requires an active sportsbook license'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' sportsbook provider setting checks' . PHP_EOL;
