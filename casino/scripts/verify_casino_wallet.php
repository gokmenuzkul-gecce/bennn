<?php

// Ad-hoc verification of the seamless wallet flow. Run: php scripts/verify_casino_wallet.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;
use VanguardLTE\Casino\Wallet\CasinoWalletService;
use VanguardLTE\User;

$registry = app(CasinoProviderRegistry::class);
$wallet = app(CasinoWalletService::class);
$provider = $registry->make('pragmatic');
$secret = $provider->config()['secret_key'];

$user = User::where('username', '!=', '')->orderBy('id')->first();
$userId = (int) $user->id;
$userCode = CasinoProviderPlayer::codeFor($userId, 'pragmatic', 'u');

// Start from a clean slate so the assertions are deterministic across runs,
// and remember the real balance so the verification leaves no trace behind.
$testTxIds = ['tx-betwin-001', 'tx-deposit-001', 'tx-withdraw-001', 'tx-huge-bet'];
$originalBalance = (float) $user->balance;
DB::table('casino_wallet_transactions')->whereIn('transaction_id', $testTxIds)->delete();
DB::table('transactions')->where('source', 'casino')->where('user_id', $userId)->delete();

DB::table('users')->where('id', $userId)->update(['balance' => 100.00]);
$user->refresh();

function signed(array $params, array $order, string $secret): array
{
    $msg = '';
    foreach ($order as $f) {
        $v = $params[$f] ?? '';
        if (in_array($f, ['amount', 'betAmount', 'winAmount'], true)) {
            $v = number_format((float) $v, 2, '.', '');
        }
        $msg .= $v;
    }
    $params['sign'] = strtoupper(hash_hmac('sha256', $msg, $secret));
    return $params;
}

$pass = 0; $fail = 0;
function check(string $label, $expected, $actual) {
    global $pass, $fail;
    if ($expected == $actual) { $pass++; echo "  OK   $label\n"; }
    else { $fail++; echo "  FAIL $label (beklenen=" . json_encode($expected) . " gelen=" . json_encode($actual) . ")\n"; }
}

echo "== 1. GetBalance ==\n";
$p = signed(['agentID' => 'stagingGeccebet53TRY', 'userID' => $userCode, 'gameID' => 2001], $provider->signOrder('GetBalance'), $secret);
$r = $wallet->handle('GetBalance', $provider, $p);
check('bakiye 100', 100.0, (float) $r['balance']);

echo "== 2. BetWin (bet 10, win 0) ==\n";
$tx1 = 'tx-betwin-001';
$p = signed(['agentID' => 'stagingGeccebet53TRY', 'userID' => $userCode, 'betAmount' => 10.0, 'winAmount' => 0.0, 'transactionID' => $tx1, 'roundID' => 'round-1', 'gameID' => 2001], $provider->signOrder('BetWin'), $secret);
$r = $wallet->handle('BetWin', $provider, $p);
check('kod 0', 0, $r['code']);
check('bakiye 90', 90.0, (float) $r['balance']);

echo "== 3. BetWin tekrar (idempotency) ==\n";
$r = $wallet->handle('BetWin', $provider, $p);
check('kod 11 duplicate', 11, $r['code']);
check('bakiye hala 90', 90.0, (float) $r['balance']);

echo "== 4. Deposit (win 50) ==\n";
$tx2 = 'tx-deposit-001';
$p = signed(['agentID' => 'stagingGeccebet53TRY', 'userID' => $userCode, 'amount' => 50.0, 'refTransactionID' => $tx1, 'transactionID' => $tx2, 'roundID' => 'round-1', 'gameID' => 2001], $provider->signOrder('Deposit'), $secret);
$r = $wallet->handle('Deposit', $provider, $p);
check('kod 0', 0, $r['code']);
check('bakiye 140', 140.0, (float) $r['balance']);

echo "== 5. Withdraw (10) ==\n";
$tx3 = 'tx-withdraw-001';
$p = signed(['agentID' => 'stagingGeccebet53TRY', 'userID' => $userCode, 'amount' => 10.0, 'transactionID' => $tx3, 'roundID' => 'round-2', 'gameID' => 2001], $provider->signOrder('Withdraw'), $secret);
$r = $wallet->handle('Withdraw', $provider, $p);
check('kod 0', 0, $r['code']);
check('bakiye 130', 130.0, (float) $r['balance']);

echo "== 6. Rollback of BetWin ==\n";
$p = signed(['agentID' => 'stagingGeccebet53TRY', 'userID' => $userCode, 'refTransactionID' => $tx1, 'gameID' => 2001], $provider->signOrder('RollbackTransaction'), $secret);
$r = $wallet->handle('RollbackTransaction', $provider, $p);
check('kod 0', 0, $r['code']);
check('bakiye 140 (10 geri geldi)', 140.0, (float) $r['balance']);

echo "== 7. Rollback tekrar ==\n";
$r = $wallet->handle('RollbackTransaction', $provider, $p);
check('kod 9 already rolled back', 9, $r['code']);

echo "== 8. Yetersiz bakiye ==\n";
$tx4 = 'tx-huge-bet';
$p = signed(['agentID' => 'stagingGeccebet53TRY', 'userID' => $userCode, 'betAmount' => 999999.0, 'winAmount' => 0.0, 'transactionID' => $tx4, 'roundID' => 'round-3', 'gameID' => 2001], $provider->signOrder('BetWin'), $secret);
$r = $wallet->handle('BetWin', $provider, $p);
check('kod 6 insufficient', 6, $r['code']);

echo "== 9. Gecersiz imza reddi ==\n";
$bad = ['agentID' => 'stagingGeccebet53TRY', 'userID' => $userCode, 'gameID' => 2001, 'sign' => str_repeat('A', 64)];
check('verify false', false, $provider->verify($bad, $provider->signOrder('GetBalance')));

echo "== 10. Bilinmeyen kullanici ==\n";
$p = signed(['agentID' => 'stagingGeccebet53TRY', 'userID' => 'yok_123', 'gameID' => 2001], $provider->signOrder('GetBalance'), $secret);
$r = $wallet->handle('GetBalance', $provider, $p);
check('kod 5 user not found', 5, $r['code']);

echo "\n== Ledger ==\n";
$ledger = DB::table('transactions')->where('source', 'casino')->count();
echo "  casino islem kaydi: $ledger\n";
$walletTx = DB::table('casino_wallet_transactions')->count();
echo "  cuzdan islem kaydi: $walletTx\n";
echo "  son bakiye: " . User::find($userId)->balance . "\n";

// Leave the real account exactly as we found it.
DB::table('casino_wallet_transactions')->whereIn('transaction_id', $testTxIds)->delete();
DB::table('transactions')->where('source', 'casino')->where('user_id', $userId)->delete();
DB::table('users')->where('id', $userId)->update(['balance' => $originalBalance]);
echo "  temizlik: bakiye " . $originalBalance . " olarak geri yuklendi\n";

echo "\nSONUC: $pass gecti, $fail basarisiz\n";
exit($fail > 0 ? 1 : 0);
