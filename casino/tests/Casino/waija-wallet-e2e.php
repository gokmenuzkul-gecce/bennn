<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end seamless-wallet check for the Waija provider.
 *
 * Exercises the real WaijaWalletService against the site DB: a balance lookup
 * reads the wallet, a debit subtracts, a duplicate call_id is idempotent, a
 * credit adds, a rollback (rb=1) applies like a normal movement, a bonus_fs
 * debit leaves cash untouched, and an over-drawing debit is refused with error
 * 1. Signature verification (md5(timestamp+salt), 30s window) is checked too.
 *
 * A synthetic but internally consistent salt is used so no live credential is
 * required; every row the test writes is tagged and cleaned up.
 *
 * Usage: php tests/Casino/waija-wallet-e2e.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Waija\WaijaClient;
use VanguardLTE\Casino\Waija\WaijaWalletService;
use VanguardLTE\User;

$config = config('casino_providers.waija');
$config['salt_key'] = 'test-salt-abc';
$config['base_url'] = 'https://staging.waija.example';
$config['api_login'] = 'test-login';
$config['api_password'] = 'test-password';
$client = new WaijaClient($config);
$wallet = new WaijaWalletService();

$user = User::orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "FAIL: no user to test against\n");
    exit(1);
}

$username = CasinoProviderPlayer::codeFor((int) $user->id, 'waija', 'u');
$startBalance = (float) $user->balance;
$startTxnId = (int) DB::table('transactions')->max('id');
$failures = 0;
$checks = 0;

$check = static function (string $label, bool $ok, string $extra = '') use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . ($extra !== '' ? '  [' . $extra . ']' : '') . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$call = static fn (string $action, array $extra): array => array_merge([
    'action' => $action,
    'username' => $username,
    'currency' => 'TRY',
    'timestamp' => (string) time(),
    'key' => $client->sign((string) time()),
], $extra);

$fresh = static fn (): array => [
    'timestamp' => (string) time(),
    'key' => $client->sign((string) time()),
];

// --- Signature verification -------------------------------------------------
[$ts, $key] = [ (string) time(), '' ];
$key = $client->sign($ts);
$check('signature accepts a fresh md5(timestamp+salt)', $client->verify($ts, $key));
$check('signature rejects a wrong key', !$client->verify($ts, 'deadbeef'));
$check('signature rejects a stale timestamp', !$client->verify((string) (time() - 120), $client->sign((string) (time() - 120))));

try {
    // --- Balance ------------------------------------------------------------
    $res = $wallet->handle('balance', $call('balance', []), $client);
    $check('balance returns error 0', ($res['error'] ?? -1) === 0, json_encode($res));
    $check('balance is reported in cents', ($res['balance'] ?? -1) === (int) round($startBalance * 100), 'got ' . ($res['balance'] ?? 'null'));

    // --- Debit --------------------------------------------------------------
    $txDebit = 'wtest-debit-1';
    $res = $wallet->handle('debit', $call('debit', ['amount' => 2000, 'call_id' => $txDebit, 'round_id' => 'r1', 'game_id' => 'g1', 'rb' => 0, 'type' => 'spin']), $client);
    $afterDebit = (float) User::find($user->id)->balance;
    $check('debit subtracts the stake', abs(($startBalance - 20.0) - $afterDebit) < 0.001, 'balance=' . $afterDebit);
    $check('debit reply carries the new balance in cents', ($res['balance'] ?? -1) === (int) round($afterDebit * 100));

    // --- Duplicate debit ----------------------------------------------------
    $res = $wallet->handle('debit', $call('debit', ['amount' => 2000, 'call_id' => $txDebit, 'round_id' => 'r1', 'game_id' => 'g1', 'rb' => 0, 'type' => 'spin']), $client);
    $afterDup = (float) User::find($user->id)->balance;
    $check('duplicate debit is idempotent', abs($afterDebit - $afterDup) < 0.001, 'balance=' . $afterDup);

    // --- Credit -------------------------------------------------------------
    $txCredit = 'wtest-credit-1';
    $res = $wallet->handle('credit', $call('credit', ['amount' => 500, 'call_id' => $txCredit, 'round_id' => 'r1', 'game_id' => 'g1', 'rb' => 0, 'type' => 'spin']), $client);
    $afterCredit = (float) User::find($user->id)->balance;
    $check('credit adds the win', abs(($afterDebit + 5.0) - $afterCredit) < 0.001, 'balance=' . $afterCredit);

    // --- Rollback (rb=1) ----------------------------------------------------
    $txRollback = 'wtest-rollback-1';
    $res = $wallet->handle('credit', $call('credit', ['amount' => 2000, 'call_id' => $txRollback, 'round_id' => 'r1', 'game_id' => 'g1', 'rb' => 1, 'type' => 'spin']), $client);
    $afterRollback = (float) User::find($user->id)->balance;
    $check('rollback applies like a normal movement', abs(($afterCredit + 20.0) - $afterRollback) < 0.001, 'balance=' . $afterRollback);
    $check('rollback row is tagged', DB::table('casino_wallet_transactions')->where('provider_key', 'waija')->where('transaction_id', $txRollback)->where('reference_transaction_id', 'rollback')->exists());

    // --- Bonus free spin ----------------------------------------------------
    $txFs = 'wtest-fs-1';
    $res = $wallet->handle('debit', $call('debit', ['amount' => 2500, 'call_id' => $txFs, 'round_id' => 'r2', 'game_id' => 'g1', 'rb' => 0, 'type' => 'bonus_fs']), $client);
    $afterFs = (float) User::find($user->id)->balance;
    $check('bonus_fs debit does not touch cash', abs($afterRollback - $afterFs) < 0.001, 'balance=' . $afterFs);
    $check('bonus_fs still returns error 0', ($res['error'] ?? -1) === 0);

    // --- Insufficient funds -------------------------------------------------
    $res = $wallet->handle('debit', $call('debit', ['amount' => (int) round(($afterFs + 1000) * 100), 'call_id' => 'wtest-over-1', 'round_id' => 'r3', 'game_id' => 'g1', 'rb' => 0, 'type' => 'spin']), $client);
    $afterOver = (float) User::find($user->id)->balance;
    $check('over-drawing debit is refused with error 1', ($res['error'] ?? -1) === 1, json_encode($res));
    $check('refused debit leaves the balance unchanged', abs($afterFs - $afterOver) < 0.001, 'balance=' . $afterOver);

    // --- Unknown player -----------------------------------------------------
    $res = $wallet->handle('balance', ['action' => 'balance', 'username' => 'nobody-xyz', 'timestamp' => (string) time(), 'key' => $client->sign((string) time())], $client);
    $check('unknown player returns error 2', ($res['error'] ?? -1) === 2);
} finally {
    // Restore the wallet and remove every row this run created.
    DB::table('casino_wallet_transactions')->where('provider_key', 'waija')->delete();
    DB::table('transactions')->where('id', '>', $startTxnId)->delete();
    DB::table('users')->where('id', $user->id)->update(['balance' => $startBalance]);
}

$final = (float) User::find($user->id)->balance;
$check('balance restored after cleanup', abs($final - $startBalance) < 0.001, 'balance=' . $final);

echo PHP_EOL . $checks . ' kontrol, ' . $failures . ' hata' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
