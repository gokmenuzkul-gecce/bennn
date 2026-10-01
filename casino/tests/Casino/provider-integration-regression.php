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

$contract = $read('app/Casino/Providers/Contracts/CasinoProvider.php');
$abstract = $read('app/Casino/Providers/AbstractCasinoProvider.php');
$registry = $read('app/Casino/Providers/CasinoProviderRegistry.php');
$wallet = $read('app/Casino/Wallet/CasinoWalletService.php');
$controller = $read('app/Http/Controllers/Web/Webhooks/CasinoWalletController.php');
$admin = $read('app/Http/Controllers/Web/Liteback/CasinoProviderController.php');
$adminView = $read('resources/views/liteback/casino/providers.blade.php');
$txView = $read('resources/views/liteback/casino/transactions.blade.php');
$layout = $read('resources/views/liteback/layout.blade.php');
$routes = $read('routes/web.php');
$config = $read('config/casino_providers.php');
$migration = $read('database/migrations/2026_10_01_000001_create_casino_provider_integration.php');
$csrf = $read('app/Http/Middleware/VerifyCsrfToken.php');

$checks = [
    'provider contract defines the adapter surface' => str_contains($contract, 'interface CasinoProvider')
        && str_contains($contract, 'public function sign(array $params, array $order): string;')
        && str_contains($contract, 'public function verify(array $payload, array $order): bool;')
        && str_contains($contract, 'public function testConnectivity(): array;')
        && str_contains($contract, 'public function launchUrl(string $userCode, string $gameId, string $lang = \'tr\'): string;'),

    'all four aggregator brands are registered' => str_contains($registry, 'PragmaticProvider::KEY')
        && str_contains($registry, 'PGSoftProvider::KEY')
        && str_contains($registry, 'AmaticProvider::KEY')
        && str_contains($registry, 'AmusnetProvider::KEY'),

    'each brand carries its endpoint and reads credentials from env' => str_contains($config, 'pk2api.loginxgamesapi.com')
        && str_contains($config, 'ggapi.loginxgamesapi.com')
        && str_contains($config, 'amapi.loginxgamesapi.com')
        && str_contains($config, 'api.gitamus.net')
        && str_contains($config, "env('PRAGMATIC_SECRET_KEY'")
        && str_contains($config, "env('AMUSNET_SECRET_KEY'"),

    'no provider secret is hardcoded in config' => !preg_match('/[\'"][0-9a-f]{32}[\'"]/', $config),

    'signing uses HMAC-SHA256 over ordered, 2-decimal money values' => str_contains($abstract, "hash_hmac('sha256'")
        && str_contains($abstract, 'number_format((float) $value, 2')
        && str_contains($abstract, "protected const MONEY_FIELDS = ['amount', 'betAmount', 'winAmount'];"),

    'signature verification is constant-time' => str_contains($abstract, 'hash_equals('),

    'wallet implements every callback operation' => str_contains($wallet, "'GetBalance' => \$this->getBalance")
        && str_contains($wallet, "'BetWin' => \$this->betWin")
        && str_contains($wallet, "'Withdraw' => \$this->withdraw")
        && str_contains($wallet, "'Deposit' => \$this->deposit")
        && str_contains($wallet, "'RollbackTransaction' => \$this->rollback"),

    'wallet result codes match the vendor contract' => str_contains($wallet, 'public const INVALID_SIGN = 3;')
        && str_contains($wallet, 'public const USER_NOT_FOUND = 5;')
        && str_contains($wallet, 'public const INSUFFICIENT_FUNDS = 6;')
        && str_contains($wallet, 'public const ALREADY_ROLLED_BACK = 9;')
        && str_contains($wallet, 'public const DUPLICATE = 11;'),

    'balance mutations are row-locked and ledgered' => str_contains($wallet, 'lockForUpdate()')
        && str_contains($wallet, "DB::table('transactions')->insert")
        && str_contains($wallet, "'source' => 'casino'"),

    'duplicate transactions return code 11 with the original balance' => str_contains($wallet, 'CasinoWalletTransaction::findFor')
        && str_contains($wallet, 'self::DUPLICATE')
        && str_contains($wallet, "'balance' => (float) \$existing->balance_after"),

    'rollback reverses the referenced transaction and is idempotent' => str_contains($wallet, 'rollbackDelta')
        && str_contains($wallet, "'rolled_back'")
        && str_contains($wallet, 'self::ALREADY_ROLLED_BACK'),

    'webhook resolves provider from player mapping with signature fallback' => str_contains($controller, 'CasinoProviderPlayer::query()->where(\'user_code\'')
        && str_contains($controller, '$provider->verify($payload, $provider->signOrder($operation))'),

    'webhook always answers HTTP 200 with a JSON result code' => str_contains($controller, 'response()->json($body, 200')
        && str_contains($controller, 'CasinoWalletService::INVALID_SIGN'),

    'callback route accepts the operation in the path or the body' => str_contains($routes, 'webhooks/aggregator/{slug}/wallet/{operation?}')
        && str_contains($routes, 'GetBalance|Withdraw|Deposit|BetWin|RollbackTransaction')
        && str_contains($controller, 'OPERATION_ALIASES')
        && str_contains($controller, 'resolveOperation(')
        && str_contains($csrf, 'webhooks/aggregator/*'),

    'migration adds provider columns and the wallet tables' => str_contains($migration, "'provider_key'")
        && str_contains($migration, "'casino_provider_players'")
        && str_contains($migration, "'casino_wallet_transactions'")
        && str_contains($migration, "'casino_tx_provider_tx_unique'"),

    'admin panel lists, edits, toggles and tests providers' => str_contains($admin, 'public function index()')
        && str_contains($admin, 'public function update(Request $request)')
        && str_contains($admin, 'public function toggle(Request $request)')
        && str_contains($admin, 'public function test(Request $request)')
        && str_contains($adminView, "route('liteback.casino.providers.update')")
        && str_contains($adminView, "route('liteback.casino.providers.test')"),

    'admin keeps existing secrets when a field is left blank' => str_contains($admin, 'boş bırak = değişmez')
        || (str_contains($admin, 'if (isset($data[$field]) && $data[$field] !== \'\')')),

    'wallet ledger is browsable and linked from the sidebar' => str_contains($txView, 'casino_wallet_transactions')
        || str_contains($admin, 'casino_wallet_transactions')
        && str_contains($layout, "route('liteback.casino.transactions')"),

    'provider admin routes are registered under liteback auth' => str_contains($routes, 'liteback.casino.providers')
        && str_contains($routes, 'CasinoProviderController@index')
        && str_contains($routes, 'CasinoProviderController@test'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' casino provider integration checks' . PHP_EOL;
