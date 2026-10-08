<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Static regression checks for the 01.tech Aggregator (A8R) integration.
 *
 * A8R speaks Twirp v7 with a raw-body HMAC-SHA256 signature (X-REQUEST-SIGN),
 * decimal-string money amounts and a seamless wallet. These checks guard the
 * wiring (config, client, wallet, provider, registry, routes, catalogue sync)
 * without touching the DB. The behavioural wallet flow lives in
 * aggregator01-wallet-e2e.php.
 *
 * Usage: php tests/Casino/aggregator01-integration-regression.php
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$client = $read('app/Casino/Aggregator01/Aggregator01Client.php');
$wallet = $read('app/Casino/Aggregator01/Aggregator01WalletService.php');
$exception = $read('app/Casino/Aggregator01/InsufficientFundsException.php');
$provider = $read('app/Casino/Providers/Aggregator01Provider.php');
$controller = $read('app/Http/Controllers/Web/Webhooks/Aggregator01WebhookController.php');
$registry = $read('app/Casino/Providers/CasinoProviderRegistry.php');
$sync = $read('app/Casino/CasinoCatalogSyncService.php');
$config = $read('config/casino_providers.php');
$routes = $read('routes/web.php');
$envExample = $read('.env.example');

$checks = [
    'client signs the raw body with HMAC-SHA256' => str_contains($client, "hash_hmac('sha256', \$rawBody")
        && str_contains($client, 'X-REQUEST-SIGN'),

    'client verifies inbound callbacks with hash_equals' => str_contains($client, 'public function verifyWebhook')
        && str_contains($client, 'hash_equals('),

    'client calls Twirp paths under /v2/<Service>/<Method>' => str_contains($client, "'/v2/' . \$service . '/' . \$method"),

    'client requests the catalogue via casino_a8r.Game/List' => str_contains($client, "'casino_a8r.Game', 'List'"),

    'client mints real sessions via casino_a8r.Launcher/Real' => str_contains($client, "'casino_a8r.Launcher', \$method")
        && str_contains($client, "launch('Real'"),

    'client mints demo sessions via casino_a8r.Launcher/Demo' => str_contains($client, "launch('Demo'"),

    'client reads the launch_url from the launcher reply' => str_contains($client, "\$response['body']['launch_url']"),

    'client flattens providers -> games from the gamelist reply' => str_contains($client, "\$provider['games']"),

    'client skips unreleased/recalled games' => str_contains($client, 'released_at') && str_contains($client, 'recalled_at'),

    'client defaults casino_id to gecce' => str_contains($client, "'casino_id' => \$this->casinoId()")
        && str_contains($config, "AGGREGATOR01_CASINO_ID', 'gecce'"),

    'config defines the aggregator01 block' => str_contains($config, "'aggregator01' => ["),

    'config defaults currency to TRY' => str_contains($config, "'currency' => env('AGGREGATOR01_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY'))"),

    'config registers the provider label entry' => str_contains($config, "'label' => '01.tech Aggregator'"),

    'config defaults the callback path' => str_contains($config, "'/webhooks/aggregator01/callbacks'"),

    'wallet dispatches Balance/BetWin/Rollback/Finish' => str_contains($wallet, "'Balance' => \$this->balance")
        && str_contains($wallet, "'BetWin' => \$this->betWin")
        && str_contains($wallet, "'Rollback' => \$this->rollback")
        && str_contains($wallet, "'Finish' => \$this->finish"),

    'wallet processes amounts with BCMath, not floats' => str_contains($wallet, 'bcadd('),

    'wallet validates the 18/12 money pattern' => str_contains($wallet, '/^\\d{1,18}(\\.\\d{1,12})?$/'),

    'wallet is idempotent per transaction id' => str_contains($wallet, "CasinoWalletTransaction::findFor(self::PROVIDER_KEY, \$id)"),

    'wallet refunds a bet rollback and deducts a win rollback' => str_contains($wallet, "refund a bet")
        && str_contains($wallet, "deduct a win"),

    'wallet leaves a rollback tombstone for missing originals' => str_contains($wallet, "operation' => 'rollback'"),

    'wallet refuses an over-drawing bet with api_code 100' => str_contains($wallet, 'API_INSUFFICIENT_FUNDS')
        && str_contains($wallet, 'InsufficientFundsException'),

    'wallet errors use the Twirp envelope with meta.api_code' => str_contains($wallet, "'api_code' => \$apiCode")
        && str_contains($wallet, "'api_message' => \$message"),

    'wallet responds balance as a decimal string' => str_contains($wallet, "'balance' => \$this->money("),

    'insufficient-funds exception carries the balance' => str_contains($exception, 'public function balance()'),

    'provider declares the aggregator01 key' => str_contains($provider, "KEY = 'aggregator01'"),

    'provider label is 01.tech Aggregator' => str_contains($provider, "return '01.tech Aggregator';"),

    'provider launches through the client' => str_contains($provider, 'client()->launchReal('),

    'provider is registered in the registry' => str_contains($registry, 'Aggregator01Provider::KEY'),

    'catalogue sync dispatches to fetchAggregator01' => str_contains($sync, 'fetchAggregator01')
        && str_contains($sync, 'Aggregator01Provider::KEY'),

    'webhook routes are registered under /webhooks/aggregator01' => str_contains($routes, 'webhooks/aggregator01')
        && str_contains($routes, 'Aggregator01WebhookController'),

    'webhook exposes the Aggregator method paths' => str_contains($routes, 'callbacks/Player/Balance')
        && str_contains($routes, 'callbacks/Round/BetWin')
        && str_contains($routes, 'callbacks/Round/Rollback')
        && str_contains($routes, 'callbacks/Round/Finish'),

    'webhook controller verifies the signature over the raw body' => str_contains($controller, 'verifyWebhook($raw, $signature)'),

    'webhook rejects a bad signature with api_code 403' => str_contains($controller, "'api_code' => '403'"),

    'webhook reads the operation from the body on the flat route' => str_contains($controller, 'operationFromBody'),

    'env example documents the A8R variables' => str_contains($envExample, 'AGGREGATOR01_BASE_URL')
        && str_contains($envExample, 'AGGREGATOR01_AUTH_TOKEN')
        && str_contains($envExample, 'AGGREGATOR01_CASINO_ID')
        && str_contains($envExample, 'AGGREGATOR01_CALLBACK_PATH'),

    'env example warns that we whitelist their IPs' => str_contains($envExample, 'we must whitelist'),
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
