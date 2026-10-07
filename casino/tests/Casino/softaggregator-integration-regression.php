<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Static regression checks for the SoftAggregator integration.
 *
 * SoftAggregator reuses Waija's operator protocol (POST {api_login,
 * api_password, method}, {error, response} reply, md5(timestamp+salt_key)
 * wallet callbacks, id_hash launch ids), so it subclasses the Waija client and
 * wallet service. These checks guard the wiring without touching the DB.
 *
 * Usage: php tests/Casino/softaggregator-integration-regression.php
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$client = $read('app/Casino/SoftAggregator/SoftAggregatorClient.php');
$wallet = $read('app/Casino/SoftAggregator/SoftAggregatorWalletService.php');
$provider = $read('app/Casino/Providers/SoftAggregatorProvider.php');
$controller = $read('app/Http/Controllers/Web/Webhooks/SoftAggregatorWebhookController.php');
$registry = $read('app/Casino/Providers/CasinoProviderRegistry.php');
$sync = $read('app/Casino/CasinoCatalogSyncService.php');
$config = $read('config/casino_providers.php');
$routes = $read('routes/web.php');
$envExample = $read('.env.example');
$waijaClient = $read('app/Casino/Waija/WaijaClient.php');
$waijaWallet = $read('app/Casino/Waija/WaijaWalletService.php');

$checks = [
    'client extends the Waija client (shared protocol)' => str_contains($client, 'extends WaijaClient'),

    'client overrides the config block key' => str_contains($client, "return 'softaggregator';")
        && str_contains($client, 'protected function configKey()'),

    'client overrides the settings key' => str_contains($client, 'casino_provider_softaggregator'),

    'client overrides the callback path' => str_contains($client, '/webhooks/softaggregator/callbacks'),

    'wallet service extends the Waija wallet service' => str_contains($wallet, 'extends WaijaWalletService'),

    'wallet service overrides the ledger provider key' => str_contains($wallet, "PROVIDER_KEY = 'softaggregator'"),

    'provider declares the softaggregator key' => str_contains($provider, "KEY = 'softaggregator'"),

    'provider label is SoftAggregator' => str_contains($provider, "return 'SoftAggregator';"),

    'provider mints a session via createPlayer then getGame' => str_contains($provider, 'createPlayer')
        && str_contains($provider, 'client()->launch'),

    'launch accepts both bare-string and {"gameurl"} replies' => str_contains($waijaClient, 'extractLaunchUrl')
        && str_contains($waijaClient, "'gameurl'")
        && str_contains($waijaClient, "'game_url'"),

    'demo launch reuses the same URL extractor' => str_contains($waijaClient, 'extractLaunchUrl($body)'),

    'provider is registered in the registry' => str_contains($registry, 'SoftAggregatorProvider::KEY'),

    'catalogue sync dispatches to fetchSoftAggregator' => str_contains($sync, 'fetchSoftAggregator')
        && str_contains($sync, 'SoftAggregatorProvider::KEY'),

    'sync maps id_hash as the launch id' => str_contains($sync, "\$row['id_hash']"),

    'config defines the softaggregator block' => str_contains($config, "'softaggregator' => ["),

    'config defaults the base URL to api.softaggregator.com' => str_contains($config, 'https://api.softaggregator.com/api/v1'),

    'config defaults the request encoding to json' => str_contains($config, "'request_format' => env('SOFTAGGREGATOR_REQUEST_FORMAT', 'json')"),

    'config defaults currency to TRY' => str_contains($config, "'currency' => env('SOFTAGGREGATOR_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY'))"),

    'config registers the provider label entry' => str_contains($config, "'label' => 'SoftAggregator'"),

    'webhook routes are registered for GET and POST' => str_contains($routes, 'webhooks/softaggregator/callbacks')
        && str_contains($routes, 'SoftAggregatorWebhookController'),

    'webhook controller verifies the md5 signature' => str_contains($controller, 'verify($timestamp, $key)'),

    'webhook controller always answers HTTP 200' => str_contains($controller, 'response()->json($body, 200'),

    'waija client exposes overridable config hooks' => str_contains($waijaClient, 'protected function configKey()')
        && str_contains($waijaClient, 'protected function defaultCallbackPath()')
        && str_contains($waijaClient, 'protected function label()'),

    'waija wallet resolves the key late (static::)' => str_contains($waijaWallet, 'static::PROVIDER_KEY')
        && !str_contains($waijaWallet, 'self::PROVIDER_KEY'),

    'env example documents the SoftAggregator variables' => str_contains($envExample, 'SOFTAGGREGATOR_BASE_URL')
        && str_contains($envExample, 'SOFTAGGREGATOR_API_LOGIN')
        && str_contains($envExample, 'SOFTAGGREGATOR_SALT_KEY')
        && str_contains($envExample, 'SOFTAGGREGATOR_CALLBACK_PATH'),
];

$failures = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

echo PHP_EOL . count($checks) . ' kontrol, ' . $failures . ' hata' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
