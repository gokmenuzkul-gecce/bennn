<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end seamless-wallet check for the 01.tech Aggregator (A8R) provider.
 *
 * Exercises the real Aggregator01WalletService against the site DB: a balance
 * lookup reads the wallet, a BetWin debits/credits, a duplicate transaction id is
 * idempotent, a Rollback refunds a bet and deducts a win, a rollback for a
 * missing original still answers success, an over-drawing bet is refused with
 * api_code 100, and an unknown player is refused with api_code 101. Signature
 * verification (hex HMAC-SHA256 over the raw body) is checked too.
 *
 * A synthetic but internally consistent AUTH_TOKEN is used so no live credential
 * is required; every row the test writes is tagged and cleaned up.
 *
 * Usage: php tests/Casino/aggregator01-wallet-e2e.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Aggregator01\Aggregator01Client;
use VanguardLTE\Casino\Aggregator01\Aggregator01WalletService;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\User;

$config = config('casino_providers.aggregator01');
$config['base_url'] = 'https://staging.aggregator01.example';
$config['auth_token'] = 'test-auth-token-xyz';
$config['casino_id'] = 'gecce';
$client = new Aggregator01Client($config);
$wallet = new Aggregator01WalletService();

$user = User::orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "FAIL: no user to test against\n");
    exit(1);
}

$playerId = CasinoProviderPlayer::codeFor((int) $user->id, 'aggregator01', 'u');
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

// --- Signature verification -------------------------------------------------
$body = '{"player_id":"' . $playerId . '","currency":"TRY"}';
$good = hash_hmac('sha256', $body, 'test-auth-token-xyz');
$check('signature accepts a valid HMAC over the raw body', $client->verifyWebhook($body, $good));
$check('signature rejects a wrong sign', !$client->verifyWebhook($body, 'deadbeef'));
$check('signature rejects a body that was re-encoded', !$client->verifyWebhook($body . ' ', $good));
$check('signature rejects an empty sign', !$client->verifyWebhook($body, ''));

try {
    // --- Balance ------------------------------------------------------------
    $res = $wallet->balance(['player_id' => $playerId, 'currency' => 'TRY']);
    $check('balance returns HTTP 200', ($res['status'] ?? 0) === 200);
    $check(
        'balance is a decimal string matching the stored wallet',
        ($res['body']['balance'] ?? '') === number_format($startBalance, 2, '.', ''),
        'got ' . ($res['body']['balance'] ?? 'null')
    );

    // --- BetWin: bet then win in one request --------------------------------
    $betId = 'a8r-bet-1';
    $winId = 'a8r-win-1';
    $res = $wallet->betWin([
        'player_id' => $playerId,
        'currency' => 'TRY',
        'round_id' => 'round-1',
        'game_id' => '01tech:PlatinumLightning',
        'transactions' => [
            ['id' => $betId, 'type' => 'bet', 'amount' => '10.50'],
            ['id' => $winId, 'type' => 'win', 'amount' => '3.25'],
        ],
    ]);
    $afterRound = (float) User::find($user->id)->balance;
    $check('bet then win nets the right balance', abs(($startBalance - 10.50 + 3.25) - $afterRound) < 0.001, 'balance=' . $afterRound);
    $check('betwin answers HTTP 200', ($res['status'] ?? 0) === 200);
    $check('betwin echoes one response per request transaction', count($res['body']['transactions'] ?? []) === 2);
    $check(
        'betwin response balance matches the wallet',
        ($res['body']['balance'] ?? '') === number_format($afterRound, 2, '.', '')
    );

    // --- Idempotency: repeat the same bet id --------------------------------
    $res = $wallet->betWin([
        'player_id' => $playerId,
        'currency' => 'TRY',
        'round_id' => 'round-1',
        'game_id' => '01tech:PlatinumLightning',
        'transactions' => [['id' => $betId, 'type' => 'bet', 'amount' => '10.50']],
    ]);
    $afterDup = (float) User::find($user->id)->balance;
    $check('duplicate bet id is idempotent', abs($afterRound - $afterDup) < 0.001, 'balance=' . $afterDup);

    // --- Rollback: refund the bet -------------------------------------------
    $res = $wallet->rollback([
        'player_id' => $playerId,
        'currency' => 'TRY',
        'round_id' => 'round-1',
        'game_id' => '01tech:PlatinumLightning',
        'transactions' => [['id' => 'a8r-rb-bet-1', 'original_id' => $betId]],
    ]);
    $afterRbBet = (float) User::find($user->id)->balance;
    $check('bet rollback refunds the stake', abs(($afterRound + 10.50) - $afterRbBet) < 0.001, 'balance=' . $afterRbBet);

    // --- Rollback: deduct the win -------------------------------------------
    $res = $wallet->rollback([
        'player_id' => $playerId,
        'currency' => 'TRY',
        'round_id' => 'round-1',
        'game_id' => '01tech:PlatinumLightning',
        'transactions' => [['id' => 'a8r-rb-win-1', 'original_id' => $winId]],
    ]);
    $afterRbWin = (float) User::find($user->id)->balance;
    $check('win rollback deducts the win', abs(($afterRbBet - 3.25) - $afterRbWin) < 0.001, 'balance=' . $afterRbWin);

    // --- Rollback for a missing original still succeeds ---------------------
    $res = $wallet->rollback([
        'player_id' => $playerId,
        'currency' => 'TRY',
        'round_id' => 'round-2',
        'game_id' => '01tech:PlatinumLightning',
        'transactions' => [['id' => 'a8r-rb-missing-1', 'original_id' => 'does-not-exist']],
    ]);
    $afterMissing = (float) User::find($user->id)->balance;
    $check('rollback of a missing original answers HTTP 200', ($res['status'] ?? 0) === 200);
    $check('rollback of a missing original leaves the balance', abs($afterRbWin - $afterMissing) < 0.001, 'balance=' . $afterMissing);

    // --- Insufficient funds -------------------------------------------------
    $res = $wallet->betWin([
        'player_id' => $playerId,
        'currency' => 'TRY',
        'round_id' => 'round-3',
        'game_id' => '01tech:PlatinumLightning',
        'transactions' => [['id' => 'a8r-over-1', 'type' => 'bet', 'amount' => (string) ($afterMissing + 1000)]],
    ]);
    $afterOver = (float) User::find($user->id)->balance;
    $check('over-drawing bet answers HTTP 400', ($res['status'] ?? 0) === 400);
    $check('over-drawing bet uses api_code 100', ($res['body']['meta']['api_code'] ?? '') === '100');
    $check('refused bet leaves the balance unchanged', abs($afterMissing - $afterOver) < 0.001, 'balance=' . $afterOver);
    $check('refused bet returns the balance in the error meta', isset($res['body']['meta']['balance']));

    // --- Unknown player -----------------------------------------------------
    $res = $wallet->betWin([
        'player_id' => 'nobody-xyz',
        'currency' => 'TRY',
        'transactions' => [['id' => 'a8r-x-1', 'type' => 'bet', 'amount' => '1.00']],
    ]);
    $check('unknown player answers api_code 101', ($res['body']['meta']['api_code'] ?? '') === '101');

    // --- Finish -------------------------------------------------------------
    $res = $wallet->finish(['player_id' => $playerId, 'currency' => 'TRY', 'round_id' => 'round-1']);
    $check('finish answers HTTP 200 with the balance', ($res['status'] ?? 0) === 200 && isset($res['body']['balance']));
} finally {
    // Restore the wallet and remove every row this run created.
    DB::table('casino_wallet_transactions')->where('provider_key', 'aggregator01')->delete();
    DB::table('transactions')->where('id', '>', $startTxnId)->delete();
    DB::table('users')->where('id', $user->id)->update(['balance' => $startBalance]);
}

$final = (float) User::find($user->id)->balance;
$check('balance restored after cleanup', abs($final - $startBalance) < 0.001, 'balance=' . $final);

echo PHP_EOL . $checks . ' kontrol, ' . $failures . ' hata' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
