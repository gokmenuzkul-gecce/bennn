<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end seamless-wallet check for the four aggregator providers.
 *
 * Signs real callbacks the way the vendor does, posts them to the registered
 * callback URL, and asserts the site balance moves by the expected delta. Test
 * rows are tagged and cleaned up so the live ledger is left untouched.
 *
 * Usage: php tests/Casino/wallet-e2e-check.php [base-url]
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;
use VanguardLTE\User;

$base = $argv[1] ?? 'http://127.0.0.1:12000';
$slug = (string) config('casino_providers.callback_slug', 'gregmorn');
$registry = app(CasinoProviderRegistry::class);

$user = User::orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "FAIL: no user to test against\n");
    exit(1);
}

$startBalance = (float) $user->balance;
$startedAt = now();
$tag = 'E2E' . substr((string) microtime(true), -6);
$failures = [];
$checks = 0;

/** POST a signed callback and return the decoded JSON. */
function postCallback(string $url, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $body, true);

    return is_array($json) ? $json + ['_http' => $code] : ['_http' => $code, '_raw' => (string) $body];
}

function balanceOf(int $userId): float
{
    return (float) DB::table('users')->where('id', $userId)->value('balance');
}

function check(array &$failures, int &$checks, string $label, bool $ok, string $detail = ''): void
{
    $checks++;
    if (!$ok) {
        $failures[] = $label . ($detail !== '' ? ' -> ' . $detail : '');
    }
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($detail !== '' ? '  [' . $detail . ']' : '') . PHP_EOL;
}

echo "Base: $base | slug: $slug | user: {$user->id} | start balance: $startBalance" . PHP_EOL;

foreach ($registry->keys() as $key) {
    $provider = $registry->make($key);
    if (!$provider->isConfigured()) {
        echo "[$key] atlandi (yapilandirilmamis)" . PHP_EOL;
        continue;
    }
    if (!$registry->isEnabled($key)) {
        echo "[$key] atlandi (kapali)" . PHP_EOL;
        continue;
    }

    echo "[$key]" . PHP_EOL;
    $userCode = CasinoProviderPlayer::codeFor((int) $user->id, $key);
    $agent = (string) ($provider->config()['agent_id'] ?? '');
    $url = rtrim($base, '/') . '/webhooks/aggregator/' . $slug . '/wallet';

    $sign = function (string $op, array $p) use ($provider): array {
        $p['sign'] = $provider->sign($p, $provider->signOrder($op));
        return $p;
    };

    // --- GetBalance ---------------------------------------------------------
    $bal = postCallback($url . '/GetBalance', $sign('GetBalance', [
        'agentID' => $agent, 'userID' => $userCode, 'gameID' => 'test',
    ]));
    check($failures, $checks, "$key GetBalance code=0",
        ($bal['code'] ?? -1) === 0, 'code=' . ($bal['code'] ?? '?'));

    // --- BetWin: net +15 (bet 10, win 25) -----------------------------------
    $before = balanceOf((int) $user->id);
    $bw = postCallback($url . '/BetWin', $sign('BetWin', [
        'agentID' => $agent, 'userID' => $userCode,
        'betAmount' => '10.00', 'winAmount' => '25.00',
        'transactionID' => "$tag-bw", 'roundID' => "$tag-r", 'gameID' => 'test',
    ]));
    $after = balanceOf((int) $user->id);
    check($failures, $checks, "$key BetWin code=0", ($bw['code'] ?? -1) === 0, 'code=' . ($bw['code'] ?? '?'));
    check($failures, $checks, "$key BetWin +15.00",
        abs(($after - $before) - 15.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

    // --- Withdraw: -5 -------------------------------------------------------
    $before = balanceOf((int) $user->id);
    $wd = postCallback($url . '/Withdraw', $sign('Withdraw', [
        'agentID' => $agent, 'userID' => $userCode, 'amount' => '5.00',
        'transactionID' => "$tag-wd", 'roundID' => "$tag-r", 'gameID' => 'test',
    ]));
    $after = balanceOf((int) $user->id);
    check($failures, $checks, "$key Withdraw code=0", ($wd['code'] ?? -1) === 0, 'code=' . ($wd['code'] ?? '?'));
    check($failures, $checks, "$key Withdraw -5.00",
        abs(($after - $before) + 5.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

    // --- Deposit (win): +3 --------------------------------------------------
    $before = balanceOf((int) $user->id);
    $dp = postCallback($url . '/Deposit', $sign('Deposit', [
        'agentID' => $agent, 'userID' => $userCode, 'amount' => '3.00',
        'refTransactionID' => "$tag-bw", 'transactionID' => "$tag-dp",
        'roundID' => "$tag-r", 'gameID' => 'test',
    ]));
    $after = balanceOf((int) $user->id);
    check($failures, $checks, "$key Deposit code=0", ($dp['code'] ?? -1) === 0, 'code=' . ($dp['code'] ?? '?'));
    check($failures, $checks, "$key Deposit +3.00",
        abs(($after - $before) - 3.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

    // --- Rollback the BetWin: -15 ------------------------------------------
    $before = balanceOf((int) $user->id);
    $rb = postCallback($url . '/RollbackTransaction', $sign('RollbackTransaction', [
        'agentID' => $agent, 'userID' => $userCode,
        'refTransactionID' => "$tag-bw", 'gameID' => 'test',
    ]));
    $after = balanceOf((int) $user->id);
    check($failures, $checks, "$key Rollback code=0", ($rb['code'] ?? -1) === 0, 'code=' . ($rb['code'] ?? '?'));
    check($failures, $checks, "$key Rollback -15.00",
        abs(($after - $before) + 15.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

    // --- Duplicate BetWin must be rejected as duplicate ---------------------
    $dup = postCallback($url . '/BetWin', $sign('BetWin', [
        'agentID' => $agent, 'userID' => $userCode,
        'betAmount' => '10.00', 'winAmount' => '25.00',
        'transactionID' => "$tag-bw", 'roundID' => "$tag-r", 'gameID' => 'test',
    ]));
    check($failures, $checks, "$key duplicate rejected", ($dup['code'] ?? -1) === 11, 'code=' . ($dup['code'] ?? '?'));

    // --- Bad signature must be rejected ------------------------------------
    $bad = $sign('GetBalance', ['agentID' => $agent, 'userID' => $userCode, 'gameID' => 'test']);
    $bad['sign'] = str_repeat('0', 64);
    $badRes = postCallback($url . '/GetBalance', $bad);
    check($failures, $checks, "$key bad sign rejected", ($badRes['code'] ?? -1) === 3, 'code=' . ($badRes['code'] ?? '?'));
}

// --- Cleanup: restore balance, drop test ledger rows ------------------------
DB::table('users')->where('id', $user->id)->update(['balance' => $startBalance]);
DB::table('casino_wallet_transactions')->where('transaction_id', 'like', "$tag%")->delete();
DB::table('transactions')->where('source', 'casino')->where('created_at', '>=', $startedAt)->delete();
echo PHP_EOL . 'Temizlik: bakiye ' . balanceOf((int) $user->id) . ' (baslangic ' . $startBalance . ')' . PHP_EOL;

echo PHP_EOL . ($failures ? 'FAIL (' . count($failures) . '/' . $checks . ')' : 'PASS: ' . $checks . ' wallet checks') . PHP_EOL;
foreach ($failures as $f) {
    echo '  - ' . $f . PHP_EOL;
}
exit($failures ? 1 : 0);
