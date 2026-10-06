<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Static regression checks for the Gregmorn Hub aggregator integration.
 *
 * Gregmorn Hub (docs.gregmorn.org) fronts many vendors (slots + live-casino
 * tables) behind one credential set and speaks its own protocol: JWT login for
 * the catalogue, a JSON openGame signed with X-Signature, and seamless-wallet
 * callbacks whose X-Signature is hex HMAC-SHA256 over the raw body. These
 * checks guard the shape of that integration without touching the DB.
 *
 * Usage: php tests/Casino/gregmorn-integration-regression.php
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$client = $read('app/Casino/Gregmorn/GregmornClient.php');
$wallet = $read('app/Casino/Gregmorn/GregmornWalletService.php');
$controller = $read('app/Http/Controllers/Web/Webhooks/GregmornWebhookController.php');
$provider = $read('app/Casino/Providers/GregmornProvider.php');
$registry = $read('app/Casino/Providers/CasinoProviderRegistry.php');
$config = $read('config/casino_providers.php');
$routes = $read('routes/web.php');
$csrf = $read('app/Http/Middleware/VerifyCsrfToken.php');
$envExample = $read('.env.example');
$sync = $read('app/Casino/CasinoCatalogSyncService.php');

$checks = [
    'client logs in with form-encoded credentials' => str_contains($client, '/auth/login')
        && str_contains($client, 'application/x-www-form-urlencoded')
        && str_contains($client, "http_build_query([")
        && str_contains($client, "'login' =>")
        && str_contains($client, "'password' =>"),

    'client fetches the catalogue by user id and currency' => str_contains($client, '/users/')
        && str_contains($client, '/getUserGames/')
        && str_contains($client, "'Authorization: Bearer '"),

    'client opens a game with a JSON body signed by X-Signature' => str_contains($client, '/games/openGame')
        && str_contains($client, "'X-Signature: ' . \$this->signBody(\$raw)")
        && str_contains($client, "json_encode(\$payload, JSON_UNESCAPED_UNICODE"),

    'signature is hex HMAC-SHA256 over the raw body' => str_contains($client, "hash_hmac('sha256', \$rawBody")
        && str_contains($client, 'verifyWebhook')
        && str_contains($client, 'hash_equals('),

    'openGame parses content.game.url from the response' => str_contains($client, "\$body['content']['game']['url']"),

    'credentials resolve from settings first, then config' => str_contains($client, "settings(\$settingsKey)")
        && str_contains($client, "config('casino_providers.gregmorn'"),

    'wallet implements getBalance, writeBet and rollback' => str_contains($wallet, "'getbalance' => \$this->getBalance")
        && str_contains($wallet, "'writebet' => \$this->writeBet")
        && str_contains($wallet, "'rollback' => \$this->rollback"),

    'writeBet applies win minus bet with an idempotency key' => str_contains($wallet, '$delta = $win - $bet')
        && str_contains($wallet, 'CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionId)')
        && str_contains($wallet, 'lockForUpdate'),

    'rollback reverses the matching writeBet exactly once' => str_contains($wallet, "(float) \$original->bet_amount - (float) \$original->win_amount")
        && str_contains($wallet, "\$original->status = 'rolled_back'"),

    'wallet replies with the documented JSON shape' => str_contains($wallet, "'balance' => round(")
        && str_contains($wallet, "'currency' =>")
        && str_contains($wallet, "'login' =>")
        && str_contains($wallet, "'status' => 'success'"),

    'wallet exposes the Hub balance, transaction and batch endpoints' => str_contains($wallet, 'function apiBalance(')
        && str_contains($wallet, 'function apiTransaction(')
        && str_contains($wallet, 'function apiBatchTransaction('),

    'webhook answers 200 on success and 400 on failure' => str_contains($controller, "=== 'success') ? 200 : 400")
        && str_contains($controller, 'function balance(')
        && str_contains($controller, 'function transaction(')
        && str_contains($controller, 'function batchTransaction('),

    'the three Hub callback paths are registered' => str_contains($routes, "'webhooks/gregmorn' => 'webhooks.gregmorn'")
        && str_contains($routes, "/api")
        && str_contains($routes, "post('balance'")
        && str_contains($routes, "post('transaction'")
        && str_contains($routes, "post('batch-transaction'"),

    'webhook verifies the raw body signature before acting' => str_contains($controller, '$request->getContent()')
        && str_contains($controller, "header('X-Signature'")
        && str_contains($controller, 'verifyWebhook'),

    'provider adapter is registered in the registry' => str_contains($registry, 'GregmornProvider::KEY')
        && str_contains($registry, 'new GregmornProvider()'),

    'provider launches through the Hub openGame client' => str_contains($provider, '$this->client()->openGame(')
        && str_contains($provider, 'office_base_url'),

    'config carries the Hub endpoints and reads credentials from env' => str_contains($config, "'gregmorn' => [")
        && str_contains($config, "env('GREGMORN_OFFICE_BASE_URL'")
        && str_contains($config, "env('GREGMORN_SECRET_KEY'")
        && str_contains($config, 'office-api-dev.gregmorn.org')
        && str_contains($config, 'client-api-dev.gregmorn.org'),

    'no Gregmorn secret is hardcoded in config' => !preg_match('/GREGMORN_SECRET_KEY\'\s*,\s*\'[A-Za-z0-9]{8,}/', $config),

    'callback route is registered and CSRF-exempt' => str_contains($routes, 'webhooks/gregmorn/callbacks')
        && str_contains($routes, 'webhooks.gregmorn.callbacks')
        && str_contains($csrf, 'webhooks/gregmorn/*'),

    'catalogue sync pulls slots and live tables via the Hub client' => str_contains($sync, 'fetchGregmorn')
        && str_contains($sync, '$client->games()')
        && str_contains($sync, "GregmornProvider::KEY"),

    'env example documents the Gregmorn keys' => str_contains($envExample, 'GREGMORN_OFFICE_BASE_URL=')
        && str_contains($envExample, 'GREGMORN_CLIENT_BASE_URL=')
        && str_contains($envExample, 'GREGMORN_LOGIN=')
        && str_contains($envExample, 'GREGMORN_SECRET_KEY=')
        && str_contains($envExample, 'GREGMORN_CURRENCY=TRY'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' Gregmorn integration checks' . PHP_EOL;
