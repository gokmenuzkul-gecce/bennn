<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end seamless-wallet check for the Gregmorn Hub provider.
 *
 * Exercises the real GregmornWalletService against the site DB: a writeBet
 * debits the net stake, a duplicate writeBet is idempotent, a rollback restores
 * the balance, and a repeated rollback is a no-op. A synthetic but internally
 * consistent secret is used so no live credential is required; every row the
 * test writes is tagged and cleaned up.
 *
 * Usage: php tests/Casino/gregmorn-wallet-e2e.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Gregmorn\GregmornClient;
use VanguardLTE\Casino\Gregmorn\GregmornWalletService;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\User;

$config = config('casino_providers.gregmorn');
$config['secret_key'] = 'test-secret-abc';
$client = new GregmornClient($config);
$wallet = new GregmornWalletService();

$user = User::orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "FAIL: no user to test against\n");
    exit(1);
}

$login = CasinoProviderPlayer::codeFor((int) $user->id, 'gregmorn', 'u');
$start = (float) $user->balance;
$failures = 0;
$checks = 0;

$check = static function (string $label, bool $ok, string $extra = '') use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . ($extra !== '' ? '  [' . $extra . ']' : '') . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$bet = static fn (string $tx): array => [
    'cmd' => 'writeBet', 'bet' => '20.00', 'win' => '5.00', 'login' => $login,
    'sessionid' => 's1', 'transactionId' => $tx, 'gameId' => 'g1',
    'round_finished' => true, 'info' => '',
];

$tx = 'E2E' . substr((string) microtime(true), -6);
$bigTx = 'E2EBIG' . substr((string) microtime(true), -6);

$balance = $wallet->handle('getBalance', ['cmd' => 'getBalance', 'login' => $login, 'sessionid' => 's1'], $client);
$check('getBalance returns the site balance', $balance['status'] === 'success' && abs($balance['balance'] - $start) < 0.001, 'bal=' . $balance['balance']);

$betResult = $wallet->handle('writeBet', $bet($tx), $client);
$check('writeBet debits win-bet (net 15)', $betResult['status'] === 'success' && abs((float) User::find($user->id)->balance - ($start - 15)) < 0.001, 'bal=' . User::find($user->id)->balance);

$wallet->handle('writeBet', $bet($tx), $client);
$check('duplicate writeBet is idempotent', abs((float) User::find($user->id)->balance - ($start - 15)) < 0.001, 'bal=' . User::find($user->id)->balance);

$rollback = $wallet->handle('rollback', $bet($tx), $client);
$check('rollback restores the balance', $rollback['status'] === 'success' && abs((float) User::find($user->id)->balance - $start) < 0.001, 'bal=' . User::find($user->id)->balance);

$wallet->handle('rollback', $bet($tx), $client);
$check('duplicate rollback is a no-op', abs((float) User::find($user->id)->balance - $start) < 0.001, 'bal=' . User::find($user->id)->balance);

$big = $wallet->handle('writeBet', ['cmd' => 'writeBet', 'bet' => '99999999.00', 'win' => '0', 'login' => $login, 'sessionid' => 's1', 'transactionId' => $bigTx, 'gameId' => 'g1', 'round_finished' => true, 'info' => ''], $client);
$check('insufficient funds is rejected', $big['status'] === 'fail' && abs((float) User::find($user->id)->balance - $start) < 0.001, 'err=' . $big['error']);

$unknown = $wallet->handle('getBalance', ['cmd' => 'getBalance', 'login' => 'nobody-xyz', 'sessionid' => 's1'], $client);
$check('unknown player fails', $unknown['status'] === 'fail');

$raw = (string) json_encode(['cmd' => 'getBalance', 'login' => $login, 'sessionid' => 's1']);
$check('webhook signature verifies', $client->verifyWebhook($raw, $client->signBody($raw)));
$check('tampered signature is rejected', !$client->verifyWebhook($raw, str_repeat('a', 64)));

// Clean up only the rows this test created and restore the starting balance.
DB::table('casino_wallet_transactions')->where('provider_key', 'gregmorn')->whereIn('transaction_id', [$tx, $bigTx])->delete();
DB::table('transactions')->where('source', 'casino')->where('note', 'like', 'Gregmorn%')->where('created_at', '>=', now()->subMinutes(5))->delete();
User::where('id', $user->id)->update(['balance' => $start]);

echo PHP_EOL . 'Temizlik: bakiye ' . (float) User::find($user->id)->balance . ' (baslangic ' . $start . ')' . PHP_EOL;
echo $failures === 0 ? "PASS ({$checks}/{$checks})\n" : "FAIL ({$failures}/{$checks})\n";

exit($failures === 0 ? 0 : 1);
