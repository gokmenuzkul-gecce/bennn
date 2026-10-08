<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end seamless-wallet check for the 01.tech Aggregator (A8R) provider.
 *
 * Exercises the real Aggregator01WalletService against the site DB along the
 * 01.tech integration testing checklist
 * (https://docs.aggregator.01.tech/v2/casino/integration-testing-checklist):
 * balance lookup, bet/win in one request (order preserved), idempotent replay,
 * a repeated id with different data answering 409, bet/win rollback, rollback of
 * a missing original (tombstone) and its effect on a late original, a win
 * rollback that overdraws (deficit carried, Player/Balance clamped) with the
 * deficit later offset by a credit, insufficient funds (api_code 100), unknown
 * player (101), unsupported currency (154), field/amount validation (400) and
 * signature verification.
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
$balance = static fn (): float => (float) User::find($user->id)->balance;
$base = static fn (array $extra = []): array => array_merge([
    'player_id' => $playerId,
    'currency' => 'TRY',
    'round_id' => 'round-1',
    'game_id' => '01tech:PlatinumLightning',
    'provider' => '01tech',
], $extra);

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
        'balance is a 2-decimal string matching the stored wallet',
        ($res['body']['balance'] ?? '') === number_format($startBalance, 2, '.', ''),
        'got ' . ($res['body']['balance'] ?? 'null')
    );

    // --- BetWin: bet then win in one request --------------------------------
    $betId = 'a8r-bet-1';
    $winId = 'a8r-win-1';
    $res = $wallet->betWin($base(['transactions' => [
        ['id' => $betId, 'type' => 'bet', 'amount' => '10.50'],
        ['id' => $winId, 'type' => 'win', 'amount' => '3.25'],
    ]]));
    $afterRound = $balance();
    $check('bet then win nets the right balance', abs(($startBalance - 10.50 + 3.25) - $afterRound) < 0.001, 'balance=' . $afterRound);
    $check('betwin answers HTTP 200', ($res['status'] ?? 0) === 200);
    $check('betwin echoes one response per request transaction', count($res['body']['transactions'] ?? []) === 2);
    $check('betwin response balance matches the wallet', ($res['body']['balance'] ?? '') === number_format($afterRound, 2, '.', ''));

    // --- Idempotency: repeat the same bet id --------------------------------
    $res = $wallet->betWin($base(['transactions' => [['id' => $betId, 'type' => 'bet', 'amount' => '10.50']]]));
    $check('duplicate bet id answers HTTP 200', ($res['status'] ?? 0) === 200);
    $check('duplicate bet id does not move the balance', abs($afterRound - $balance()) < 0.001, 'balance=' . $balance());

    // --- 409: same id, different type ---------------------------------------
    $res = $wallet->betWin($base(['transactions' => [['id' => $betId, 'type' => 'win', 'amount' => '10.50']]]));
    $check('same id with a different type answers api_code 409', ($res['body']['meta']['api_code'] ?? '') === '409');
    $check('409 does not move the balance', abs($afterRound - $balance()) < 0.001, 'balance=' . $balance());

    // --- Validation (400) ---------------------------------------------------
    $res = $wallet->betWin($base(['transactions' => [['id' => 'a8r-neg', 'type' => 'bet', 'amount' => '-1.50']]]));
    $check('negative amount answers api_code 400', ($res['body']['meta']['api_code'] ?? '') === '400');
    $res = $wallet->betWin($base(['transactions' => []]));
    $check('empty transactions answers api_code 400', ($res['body']['meta']['api_code'] ?? '') === '400');
    $res = $wallet->betWin($base(['transactions' => [['id' => 'a8r-dup', 'type' => 'bet', 'amount' => '1.00'], ['id' => 'a8r-dup', 'type' => 'win', 'amount' => '1.00']]]));
    $check('duplicate ids in one request answer api_code 400', ($res['body']['meta']['api_code'] ?? '') === '400');
    unset($res);

    // --- Rollback: refund the bet -------------------------------------------
    $res = $wallet->rollback($base(['finished' => 'true', 'transactions' => [['id' => 'a8r-rb-bet-1', 'original_id' => $betId]]]));
    $check('bet rollback refunds the stake', abs(($afterRound + 10.50) - $balance()) < 0.001, 'balance=' . $balance());

    // --- Rollback: deduct the win -------------------------------------------
    $res = $wallet->rollback($base(['finished' => 'true', 'transactions' => [['id' => 'a8r-rb-win-1', 'original_id' => $winId]]]));
    $afterRbWin = $balance();
    $check('win rollback deducts the win', abs(($afterRound + 10.50 - 3.25) - $afterRbWin) < 0.001, 'balance=' . $afterRbWin);

    // --- Rollback of a missing original + tombstone -------------------------
    $res = $wallet->rollback($base(['round_id' => 'round-2', 'finished' => 'true', 'transactions' => [['id' => 'a8r-rb-missing-1', 'original_id' => 'does-not-exist']]]));
    $check('rollback of a missing original answers HTTP 200', ($res['status'] ?? 0) === 200);
    $check('rollback of a missing original leaves the balance', abs($afterRbWin - $balance()) < 0.001, 'balance=' . $balance());

    $res = $wallet->betWin($base(['round_id' => 'round-2', 'transactions' => [['id' => 'does-not-exist', 'type' => 'bet', 'amount' => '5.00']]]));
    $check('late bet on a tombstoned id is ignored (HTTP 200)', ($res['status'] ?? 0) === 200);
    $check('late bet on a tombstoned id leaves the balance', abs($afterRbWin - $balance()) < 0.001, 'balance=' . $balance());

    // --- Insufficient funds -------------------------------------------------
    $res = $wallet->betWin($base(['round_id' => 'round-3', 'transactions' => [['id' => 'a8r-over-1', 'type' => 'bet', 'amount' => (string) ($afterRbWin + 1000)]]]));
    $check('over-drawing bet answers HTTP 400', ($res['status'] ?? 0) === 400);
    $check('over-drawing bet uses api_code 100', ($res['body']['meta']['api_code'] ?? '') === '100');
    $check('refused bet leaves the balance unchanged', abs($afterRbWin - $balance()) < 0.001, 'balance=' . $balance());
    $check('refused bet returns the balance in the error meta', isset($res['body']['meta']['balance']));

    // --- Win rollback that overdraws -> deficit carried, balance clamped ----
    // The player wins 10025, spends 15000 of it, then that win is rolled back:
    // the stake cannot be repaid in full, so the 5000 shortfall is carried.
    $winId2 = 'a8r-win-over';
    $wallet->betWin($base(['round_id' => 'round-4', 'transactions' => [['id' => $winId2, 'type' => 'win', 'amount' => (string) ($afterRbWin + 25)]]]));
    $wallet->betWin($base(['round_id' => 'round-4b', 'transactions' => [['id' => 'a8r-spend', 'type' => 'bet', 'amount' => '15000.00']]]));
    $res = $wallet->rollback($base(['round_id' => 'round-4', 'finished' => 'true', 'transactions' => [['id' => 'a8r-rb-over', 'original_id' => $winId2]]]));
    $check('over-drawing win rollback answers HTTP 200', ($res['status'] ?? 0) === 200);
    $check('over-drawing win rollback clamps the visible balance to 0', $balance() === 0.0, 'balance=' . $balance());
    $res = $wallet->balance(['player_id' => $playerId, 'currency' => 'TRY']);
    $check('Player/Balance returns 0 while a deficit is outstanding', ($res['body']['balance'] ?? '') === '0.00', 'got ' . ($res['body']['balance'] ?? 'null'));
    $deficit = (float) DB::table('casino_wallet_deficits')->where('provider_key', 'aggregator01')->where('user_id', $user->id)->value('amount');
    $check('deficit equals the uncovered win rollback', abs($deficit - 5000.0) < 0.001, 'deficit=' . $deficit);

    // A credit of 20 is swallowed by the deficit: visible balance stays 0.
    $wallet->betWin($base(['round_id' => 'round-5', 'transactions' => [['id' => 'a8r-c1', 'type' => 'win', 'amount' => '20.00']]]));
    $check('credit below the deficit keeps the visible balance at 0', $balance() === 0.0, 'balance=' . $balance());
    // A further 4990 clears the 4980 remaining and leaves a visible 10.
    $wallet->betWin($base(['round_id' => 'round-6', 'transactions' => [['id' => 'a8r-c2', 'type' => 'win', 'amount' => '4990.00']]]));
    $check('credit above the deficit offsets it and credits the remainder', abs($balance() - 10.0) < 0.001, 'balance=' . $balance());

    // --- Unknown player -----------------------------------------------------
    $res = $wallet->betWin(['player_id' => 'nobody-xyz', 'currency' => 'TRY', 'round_id' => 'r', 'game_id' => 'g', 'provider' => 'p', 'transactions' => [['id' => 'a8r-x-1', 'type' => 'bet', 'amount' => '1.00']]]);
    $check('unknown player answers api_code 101', ($res['body']['meta']['api_code'] ?? '') === '101');

    // --- Unsupported currency ----------------------------------------------
    $res = $wallet->balance(['player_id' => $playerId, 'currency' => 'EUR']);
    $check('unsupported currency answers api_code 154', ($res['body']['meta']['api_code'] ?? '') === '154');

    // --- Finish -------------------------------------------------------------
    $res = $wallet->finish(['player_id' => $playerId, 'currency' => 'TRY', 'round_id' => 'round-1']);
    $check('finish answers HTTP 200 with the balance', ($res['status'] ?? 0) === 200 && isset($res['body']['balance']));

    // --- Freespins/Finish credits the recorded campaign, once ---------------
    $beforeFs = $balance();
    $issueId = 'a8r-fs-1';
    DB::table('casino_freespins')->insert([
        'provider_key' => 'aggregator01',
        'user_id' => $user->id,
        'issue_id' => $issueId,
        'game_id' => '01tech:BookOfDead',
        'game_provider' => '01tech',
        'quantity' => 10,
        'bet_amount' => 1.00,
        'valid_until' => now()->addDays(7),
        'status' => 'issued',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $res = $wallet->handle('FreespinsFinish', ['issue_id' => $issueId, 'amount' => '12.50'], $client);
    $check('freespins finish answers HTTP 200', ($res['status'] ?? 0) === 200);
    $check('freespins finish credits the win', abs(($beforeFs + 12.50) - $balance()) < 0.001, 'balance=' . $balance());
    $res = $wallet->handle('FreespinsFinish', ['issue_id' => $issueId, 'amount' => '12.50'], $client);
    $check('freespins finish is idempotent per issue_id', abs(($beforeFs + 12.50) - $balance()) < 0.001, 'balance=' . $balance());
    $res = $wallet->handle('FreespinsFinish', ['issue_id' => 'no-such-issue', 'amount' => '1.00'], $client);
    $check('freespins finish for an unknown issue answers 400', ($res['body']['meta']['api_code'] ?? '') === '400');
} finally {
    // Restore the wallet and remove every row this run created.
    DB::table('casino_wallet_transactions')->where('provider_key', 'aggregator01')->delete();
    DB::table('casino_wallet_deficits')->where('provider_key', 'aggregator01')->delete();
    DB::table('casino_freespins')->where('provider_key', 'aggregator01')->delete();
    DB::table('transactions')->where('id', '>', $startTxnId)->delete();
    DB::table('users')->where('id', $user->id)->update(['balance' => $startBalance]);
}

$final = (float) User::find($user->id)->balance;
$check('balance restored after cleanup', abs($final - $startBalance) < 0.001, 'balance=' . $final);

echo PHP_EOL . $checks . ' kontrol, ' . $failures . ' hata' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
