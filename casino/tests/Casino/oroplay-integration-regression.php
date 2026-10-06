<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Static regression checks for the OroPlay aggregator integration.
 *
 * OroPlay is a third protocol alongside the legacy loginxgames aggregator and
 * smpl core: a bearer token minted from clientId/clientSecret for outbound
 * calls, HTTP Basic on the operator-side wallet callbacks, and a single
 * transaction endpoint whose amount sign decides bet vs win. These checks guard
 * the shape of that integration without touching the DB.
 *
 * Usage: php tests/Casino/oroplay-integration-regression.php
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$client = $read('app/Casino/OroPlay/OroPlayClient.php');
$wallet = $read('app/Casino/OroPlay/OroPlayWalletService.php');
$controller = $read('app/Http/Controllers/Web/Webhooks/OroPlayWebhookController.php');
$provider = $read('app/Casino/Providers/OroPlayProvider.php');
$registry = $read('app/Casino/Providers/CasinoProviderRegistry.php');
$config = $read('config/casino_providers.php');
$routes = $read('routes/web.php');
$csrf = $read('app/Http/Middleware/VerifyCsrfToken.php');
$envExample = $read('.env.example');
$sync = $read('app/Casino/CasinoCatalogSyncService.php');

$checks = [
    'client mints a bearer token from clientId/clientSecret' => str_contains($client, "'/auth/createtoken'")
        && str_contains($client, "'clientId' => (string) \$this->config['client_id']")
        && str_contains($client, "'clientSecret' => (string) \$this->config['client_secret']"),

    'client caches the token to respect the 5/30s token rate limit' => str_contains($client, 'TOKEN_CACHE_KEY')
        && str_contains($client, 'Cache::get(')
        && str_contains($client, 'Cache::put(')
        && str_contains($client, 'TOKEN_SKEW'),

    'client authenticates callbacks with HTTP Basic' => str_contains($client, 'basicAuthHeader()')
        && str_contains($client, "base64_encode(\$pair)")
        && str_contains($client, "'Basic ' . base64_encode"),

    'client verifies inbound Basic credentials in constant time' => str_contains($client, 'hash_equals($expected, $header)')
        && str_contains($client, 'PHP_AUTH_USER')
        && str_contains($client, 'PHP_AUTH_PW'),

    'client reads credentials from env with settings override' => str_contains($config, "env('OROPLAY_CLIENT_ID'")
        && str_contains($config, "env('OROPLAY_CLIENT_SECRET'")
        && str_contains($config, "'casino_provider_oroplay'")
        && str_contains($client, 'casino_providers.oroplay'),

    'no OroPlay secret is hardcoded in config' => !preg_match('/client_secret\'\s*=>\s*\'[A-Za-z0-9]{8,}/', $config),

    'client calls the vendors, games and launch endpoints' => str_contains($client, "'/vendors/list'")
        && str_contains($client, "'/games/list'")
        && str_contains($client, "'/game/launch-url'"),

    'client calls the balance-transfer user endpoints' => str_contains($client, "'/user/create'")
        && str_contains($client, "'/user/balance'"),

    'wallet implements balance, transaction and batch endpoints' => str_contains($wallet, 'public function balance')
        && str_contains($wallet, 'public function transaction')
        && str_contains($wallet, 'public function batchTransactions'),

    'transaction amount sign decides bet vs win' => str_contains($wallet, "\$operation = \$delta < 0 ? 'bet' : 'win';")
        && str_contains($wallet, "'bet_amount' => \$operation === 'bet' ? abs(\$delta) : 0")
        && str_contains($wallet, "'win_amount' => \$operation === 'win' ? abs(\$delta) : 0"),

    'cancelled transactions reverse the amount' => str_contains($wallet, "filter_var(\$payload['isCanceled'] ?? false, FILTER_VALIDATE_BOOLEAN)")
        && str_contains($wallet, "\$delta = \$cancelled ? -1 * \$amount : \$amount;"),

    'wallet replies with the OroPlay {success, message, errorCode} body' => str_contains($wallet, "'success' => true, 'message' => \$balance, 'errorCode' => \$errorCode")
        && str_contains($wallet, "'success' => false, 'message' => \$description, 'errorCode' => \$errorCode"),

    'wallet uses the documented OroPlay error codes' => str_contains($wallet, 'USER_DOES_NOT_EXIST = 2')
        && str_contains($wallet, 'INSUFFICIENT_USER_BALANCE = 4')
        && str_contains($wallet, 'DUPLICATE_TRANSACTION = 6')
        && str_contains($wallet, 'INVALID_TRANSACTION = 7')
        && str_contains($wallet, 'UNAUTHORIZED = 401'),

    'duplicate transactionCode returns the stored balance idempotently' => str_contains($wallet, 'CasinoWalletTransaction::findFor')
        && str_contains($wallet, 'return $this->ok((float) $existing->balance_after, self::DUPLICATE_TRANSACTION);'),

    'a finished round rejects late transactions' => str_contains($wallet, 'roundIsFinished')
        && str_contains($wallet, 'markRoundFinished')
        && str_contains($wallet, 'self::INVALID_TRANSACTION'),

    'wallet mutations are row-locked and ledgered' => str_contains($wallet, 'lockForUpdate()')
        && str_contains($wallet, "DB::table('transactions')->insert")
        && str_contains($wallet, "'source' => 'casino'"),

    'webhook verifies Basic auth then dispatches' => str_contains($controller, 'verifyBasic')
        && str_contains($controller, 'UNAUTHORIZED')
        && str_contains($controller, '$request->json()->all()'),

    'webhook always answers HTTP 200 with JSON' => str_contains($controller, 'response()->json($body, 200'),

    'callback routes are registered and CSRF-exempt' => str_contains($routes, "webhooks/oroplay/api")
        && str_contains($routes, "webhooks.oroplay.transaction")
        && str_contains($routes, "webhooks.oroplay.batch")
        && str_contains($csrf, 'webhooks/oroplay/*'),

    'provider adapter is registered in the registry' => str_contains($registry, 'OroPlayProvider::KEY')
        && str_contains($registry, 'new OroPlayProvider()'),

    'provider encodes vendor+game and strips the colon on launch' => str_contains($provider, "explode(':', \$gameId, 2)")
        && str_contains($provider, 'vendorCode')
        && str_contains($provider, 'gameCode'),

    'catalogue sync fetches vendors then per-vendor games' => str_contains($sync, 'fetchOroPlay')
        && str_contains($sync, '$client->vendors()')
        && str_contains($sync, '$client->games(')
        && str_contains($sync, "\$vendorCode . ':' . \$gameCode"),

    'env example documents the OroPlay keys' => str_contains($envExample, 'OROPLAY_CLIENT_ID=')
        && str_contains($envExample, 'OROPLAY_CLIENT_SECRET=')
        && str_contains($envExample, 'OROPLAY_BASE_URL=')
        && str_contains($envExample, 'OROPLAY_CURRENCY=TRY'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' OroPlay integration checks' . PHP_EOL;
