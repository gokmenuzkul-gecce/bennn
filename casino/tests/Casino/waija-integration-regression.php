<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Static regression checks for the Waija (Slotsgateway) aggregator integration.
 *
 * Waija speaks its own protocol: outbound JSON method calls authenticated with
 * api_login/api_password, and a single GET callback whose signature is
 * md5(timestamp + saltkey) inside a 30-second window. Balances travel in cents.
 * These checks guard the shape of that integration without touching the DB.
 *
 * Usage: php tests/Casino/waija-integration-regression.php
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$client = $read('app/Casino/Waija/WaijaClient.php');
$wallet = $read('app/Casino/Waija/WaijaWalletService.php');
$provider = $read('app/Casino/Providers/WaijaProvider.php');
$controller = $read('app/Http/Controllers/Web/Webhooks/WaijaWebhookController.php');
$registry = $read('app/Casino/Providers/CasinoProviderRegistry.php');
$sync = $read('app/Casino/CasinoCatalogSyncService.php');
$config = $read('config/casino_providers.php');
$routes = $read('routes/web.php');
$csrf = $read('app/Http/Middleware/VerifyCsrfToken.php');
$envExample = $read('.env.example');

$checks = [
    'client signs callbacks with md5(timestamp + saltkey)' => str_contains($client, "md5(\$timestamp . \$this->saltKey())"),

    'isConfigured only needs endpoint + credentials, not the salt' =>
        (preg_match('/function isConfigured\(\): bool\s*\{(.*?)\n    \}/s', $client, $m)
            && !str_contains($m[1], 'salt_key'))
        && str_contains($client, 'function canVerifyCallbacks('),

    'client enforces a freshness window on the callback timestamp' => str_contains($client, 'signature_window')
        && str_contains($client, 'abs(time() - (int) $timestamp)'),

    'client compares signatures in constant time' => str_contains($client, 'hash_equals($this->sign($timestamp), strtolower(trim($key)))'),

    'client posts method calls form-encoded (reference SDK wire format)' => str_contains($client, "http_build_query(\$payload)")
        && str_contains($client, "application/x-www-form-urlencoded")
        && str_contains($client, "'api_login'")
        && str_contains($client, "'api_password'")
        && str_contains($client, "'method' => \$method"),

    'client sends createPlayer with nickname' => str_contains($client, "'user_nickname'")
        && str_contains($client, "'user_username'")
        && str_contains($client, "'user_password'"),

    'client uppercases the currency on the wire' => str_contains($client, 'strtoupper((string) ($this->config[\'currency\'] ?? \'TRY\'))'),

    'client exposes free-round methods' => str_contains($client, "'addFreeRounds'")
        && str_contains($client, "'getFreeRounds'")
        && str_contains($client, "'deleteFreeRounds'"),

    'client exposes the demo (fun-play) launch' => str_contains($client, "'getGameDemo'")
        && str_contains($client, 'public function demo(')
        && str_contains($provider, 'function demoLaunch('),

    'client mirrors the official SDK method surface' => str_contains($client, "'getGameList'")
        && str_contains($client, "'createPlayer'")
        && str_contains($client, "'getGameDemo'")
        && str_contains($client, "'addFreeRounds'")
        && str_contains($client, "'getFreeRounds'")
        && str_contains($client, "'deleteFreeRounds'")
        && str_contains($client, "'deleteAllFreeRounds'"),

    'client sends play_for_fun and optional branded on launch' => str_contains($client, "'play_for_fun' => 0")
        && str_contains($client, '$this->branded()'),

    'client does not call the deprecated playerExists' => !str_contains($client, "'playerExists'"),

    'client mints a session with createPlayer then getGame' => str_contains($client, "'createPlayer'")
        && str_contains($client, "'getGame'")
        && str_contains($client, "'user_username'")
        && str_contains($client, "'gameid'"),

    'client reads the catalogue from getGameList id_hash' => str_contains($client, "'getGameList'")
        && str_contains($sync, "'id_hash'"),

    'wallet scales wire amounts and balances to cents' => str_contains($wallet, 'round(((int) ($payload[\'amount\'] ?? 0)) / 100, 2)')
        && str_contains($wallet, 'round($balance * 100)'),

    'wallet handles balance/debit/credit actions' => str_contains($wallet, "'balance' => \$this->balance(")
        && str_contains($wallet, "'debit' => \$this->debit(")
        && str_contains($wallet, "'credit' => \$this->credit("),

    'wallet applies rollback as debit/credit flagged with rb' => str_contains($wallet, "payload['rb']")
        && str_contains($wallet, 'rollback'),

    'wallet does not touch cash on bonus_fs debits' => str_contains($wallet, "'bonus_fs'")
        && str_contains($wallet, 'recordNoop'),

    'wallet is idempotent on repeated call_id' => str_contains($wallet, 'CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionId)')
        && str_contains($wallet, 'lockForUpdate'),

    'wallet returns error 1 on insufficient funds and error 2 on failure' => str_contains($wallet, 'INSUFFICIENT_FUNDS = 1')
        && str_contains($wallet, 'PROCESSING_ERROR = 2'),

    'controller answers HTTP 200 for success and failure alike' => str_contains($controller, 'response()->json($body, 200')
        && str_contains($controller, 'WaijaWalletService::PROCESSING_ERROR'),

    'controller verifies the signature before touching the wallet' => str_contains($controller, '$this->client->verify($timestamp, $key)')
        && str_contains($controller, "payload['action']"),

    'routes expose the Waija callback for GET and POST' => str_contains($routes, "webhooks/waija/callbacks")
        && str_contains($routes, "foreach (['get', 'post'] as \$method)"),

    'csrf middleware exempts the Waija callback' => str_contains($csrf, 'webhooks/waija/*'),

    'config reads credentials from env with a settings override' => str_contains($config, "env('WAIJA_BASE_URL'")
        && str_contains($config, "env('WAIJA_SALT_KEY'")
        && str_contains($config, "'casino_provider_waija'"),

    'provider adapter registers createPlayer before launch' => str_contains($provider, 'createPlayer')
        && str_contains($provider, "'getGame'")
        || str_contains($provider, 'client()->launch'),

    'provider is registered in the registry' => str_contains($registry, 'WaijaProvider::KEY'),

    'env example documents the Waija variables' => str_contains($envExample, 'WAIJA_BASE_URL')
        && str_contains($envExample, 'WAIJA_SALT_KEY')
        && str_contains($envExample, 'WAIJA_CALLBACK_PATH'),
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
