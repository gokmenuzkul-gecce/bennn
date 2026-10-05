<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Static regression checks for the smpl core aggregator integration.
 *
 * smpl core speaks a different protocol from the legacy loginxgames aggregator:
 * HMAC-SHA1 header auth and one webhook endpoint driven by an "action" field.
 * These checks guard the shape of that integration without touching the DB.
 *
 * Usage: php tests/Casino/smplcore-integration-regression.php
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$client = $read('app/Casino/SmplCore/SmplCoreClient.php');
$wallet = $read('app/Casino/SmplCore/SmplCoreWalletService.php');
$controller = $read('app/Http/Controllers/Web/Webhooks/SmplCoreWebhookController.php');
$config = $read('config/casino_providers.php');
$routes = $read('routes/web.php');
$csrf = $read('app/Http/Middleware/VerifyCsrfToken.php');
$envExample = $read('.env.example');

$checks = [
    'client signs with HMAC-SHA1 over sorted merged params' => str_contains($client, "hash_hmac('sha1'")
        && str_contains($client, 'ksort($merged)')
        && str_contains($client, 'http_build_query($merged)')
        && str_contains($client, "'X-Merchant-Id'")
        && str_contains($client, "'X-Timestamp'")
        && str_contains($client, "'X-Nonce'"),

    'client validates inbound signatures in constant time' => str_contains($client, 'hash_equals($expected, $received)')
        && str_contains($client, 'hash_equals((string) $this->config[\'merchant_id\'], $merchantId)'),

    'client reads credentials from env with settings override' => str_contains($config, "env('SMPL_MERCHANT_ID'")
        && str_contains($config, "env('SMPL_MERCHANT_KEY'")
        && str_contains($config, "'casino_provider_smplcore'")
        && str_contains($client, 'casino_providers.smplcore'),

    'no smpl core secret is hardcoded in config' => !preg_match('/merchant_key\'\s*=>\s*\'[0-9a-fA-F]{8,}/', $config),

    'client calls the games and init endpoints' => str_contains($client, "'/games'")
        && str_contains($client, "'/games/init'"),

    'wallet implements every smpl core action' => str_contains($wallet, "'balance' => \$this->balance")
        && str_contains($wallet, "'bet' => \$this->bet")
        && str_contains($wallet, "'win' => \$this->win")
        && str_contains($wallet, "'refund' => \$this->refund")
        && str_contains($wallet, "'rollback' => \$this->rollback"),

    'bet deducts, win and refund credit the balance' => str_contains($wallet, "'bet', \$transactionId, \$payload, -1 * \$amount")
        && str_contains($wallet, "'win', \$transactionId, \$payload, \$amount")
        && str_contains($wallet, "'refund', \$transactionId, \$payload, \$amount"),

    'wallet returns smpl core error vocabulary' => str_contains($wallet, "const INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';")
        && str_contains($wallet, "const INTERNAL_ERROR = 'INTERNAL_ERROR';")
        && str_contains($wallet, "'error_code' => \$code")
        && str_contains($wallet, "'error_description' => \$description"),

    'wallet mutations are row-locked and ledgered' => str_contains($wallet, 'lockForUpdate()')
        && str_contains($wallet, "DB::table('transactions')->insert")
        && str_contains($wallet, "'source' => 'casino'"),

    'duplicate transaction ids return the stored balance idempotently' => str_contains($wallet, 'CasinoWalletTransaction::findFor')
        && str_contains($wallet, 'return [')
        && str_contains($wallet, "'transaction_id' => \$existing->transaction_id"),

    'rollback credits bets and debits wins, idempotently' => str_contains($wallet, 'rollback_transactions')
        && str_contains($wallet, "'rolled_back'")
        && str_contains($wallet, "\$adjustment += \$amount")
        && str_contains($wallet, "\$adjustment -= \$amount"),

    'webhook controller verifies signature then dispatches the action' => str_contains($controller, '$this->client->verify($payload, $headers)')
        && str_contains($controller, "Invalid signature")
        && str_contains($controller, '$this->wallet->handle($action, $payload)'),

    'webhook always answers HTTP 200 with JSON' => str_contains($controller, 'response()->json($body, 200'),

    'callback route is registered and CSRF-exempt' => str_contains($routes, "webhooks/smplcore/callbacks")
        && str_contains($routes, "webhooks.smplcore.callbacks")
        && str_contains($csrf, 'webhooks/smplcore/*'),

    'env example documents the smpl core keys' => str_contains($envExample, 'SMPL_MERCHANT_ID=')
        && str_contains($envExample, 'SMPL_MERCHANT_KEY=')
        && str_contains($envExample, 'SMPL_BASE_URL='),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' smpl core integration checks' . PHP_EOL;
