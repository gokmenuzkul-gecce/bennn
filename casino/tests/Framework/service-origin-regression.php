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

$config = $read('config/licensing.php');
$updater = $read('app/Services/UpdaterService.php');
$example = $read('.env.example');
$installer = file_get_contents(dirname($root) . '/install.php');

$checks = [
    'production Hub endpoint remains the default' => str_contains($config, "env('PROMEX_HUB_URL'")
        && str_contains($example, 'PROMEX_HUB_URL=https://clients.377.live/api/service'),
    'Cedar public origin is environment-controlled' => str_contains($config, "env('PROMEX_CEDAR_PUBLIC_ORIGIN'")
        && str_contains($example, 'PROMEX_CEDAR_PUBLIC_ORIGIN=https://clients.377.live'),
    'updater endpoint is environment-controlled' => str_contains($config, "env('PROMEX_HUB_VERSION_URL'")
        && str_contains($updater, "config('licensing.hub_version_url'")
        && str_contains($example, 'PROMEX_HUB_VERSION_URL=https://clients.377.live/api/service/version'),
    'development signing key is restricted to local mode' => str_contains($config, "env('APP_ENV') === 'local'")
        && str_contains($config, "env('PROMEX_LOCAL_PUBLIC_KEY_FILE'")
        && str_contains($example, 'PROMEX_LOCAL_PUBLIC_KEY_FILE=')
        && str_contains($config, "env('PROMEX_LOCAL_CA_FILE'")
        && str_contains($example, 'PROMEX_LOCAL_CA_FILE='),
    'local and live credentials use isolated runtime profiles' => str_contains($config, "env('PROMEX_RUNTIME_PROFILE', 'live')")
        && str_contains($read('app/Services/PromexInstallationService.php'), "promex.installation' . \$suffix")
        && str_contains($read('app/Services/LicenseService.php'), "license' . \$suffix . '.cert")
        && str_contains($example, 'PROMEX_RUNTIME_PROFILE=live'),
    'fresh production installs explicitly restore live endpoints' => is_string($installer)
        && str_contains($installer, "'PROMEX_HUB_URL' => 'https://clients.377.live/api/service'")
        && str_contains($installer, "'PROMEX_LOCAL_PUBLIC_KEY_FILE' => ''"),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}
echo 'PASS: ' . count($checks) . ' service-origin checks' . PHP_EOL;
