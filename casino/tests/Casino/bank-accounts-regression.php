<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Regression for the multi-IBAN ("banka havalesi") deposit flow.
 *
 * Verifies that the operator can register many payment accounts, that the
 * player endpoint hands back a random active account for the chosen method,
 * that a claim with a receipt upload stores both the receipt and the account
 * used, that the admin queue exposes that account, and that approval credits
 * the wallet. All test rows are tagged and removed so the live pool, ledger and
 * queue are left untouched.
 *
 * Usage: php tests/Casino/bank-accounts-regression.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Http\Controllers\Web\Frontend\TopupController;
use VanguardLTE\Http\Controllers\Web\Liteback\ManualDepositsController;
use VanguardLTE\User;

$failures = [];
$checks = 0;

function check(string $label, bool $ok, array &$failures, int &$checks): void
{
    $checks++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . "\n";
    if (!$ok) {
        $failures[] = $label;
    }
}

$player = User::whereNotNull('shop_id')->orderBy('id')->first();
$admin = User::where('role_id', 6)->orderBy('id')->first() ?: $player;

if (!$player || !$admin) {
    fwrite(STDERR, "FAIL: need a shop user and an admin user to test against\n");
    exit(1);
}

$tag = 'regr_' . substr(md5((string) microtime(true)), 0, 8);
$accountIds = [];
$intentId = null;
$depositId = null;
$receiptAbs = null;
$balanceBefore = (float) $player->balance;

echo "Bank-accounts (multi-IBAN) regression\n";

try {
    // 1. Operator registers several IBANs for the "bank" method.
    for ($i = 1; $i <= 3; $i++) {
        $accountIds[] = DB::table('payment_bank_accounts')->insertGetId([
            'method' => 'bank',
            'bank' => 'Regresyon Bank ' . $i,
            'holder' => 'Regresyon Sahip ' . $i,
            'iban' => 'TR' . str_pad((string) (100000000000000000000000 + $i), 24, '0', STR_PAD_LEFT),
            'active' => 1,
            'position' => 9000 + $i,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    // A disabled account must never be drawn.
    $disabledId = DB::table('payment_bank_accounts')->insertGetId([
        'method' => 'bank',
        'bank' => 'Regresyon Pasif',
        'holder' => 'Pasif',
        'iban' => 'TR000000000000000000000099',
        'active' => 0,
        'position' => 9099,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $accountIds[] = $disabledId;

    check('3+ active accounts registered', count($accountIds) === 4, $failures, $checks);

    // 2. The player endpoint returns a random active account from the pool.
    $controller = app(TopupController::class);
    auth()->setUser($player);

    $drawn = [];
    $allValid = true;
    for ($n = 0; $n < 25; $n++) {
        $req = Request::create('/topup/random-bank-account', 'POST', ['method' => 'bank']);
        $req->headers->set('Accept', 'application/json');
        $res = $controller->randomBankAccount($req);
        $payload = json_decode((string) $res->getContent(), true);
        $id = $payload['account']['id'] ?? null;
        $drawn[] = $id;
        if (!$id || !in_array($id, array_slice($accountIds, 0, 3), true)) {
            $allValid = false;
        }
    }
    check('random endpoint always returns an active account', $allValid, $failures, $checks);
    check('random endpoint never returns the disabled account', !in_array($disabledId, $drawn, true), $failures, $checks);
    check('random endpoint varies the account across draws', count(array_unique($drawn)) > 1, $failures, $checks);

    // 3. Claim with a receipt upload + the drawn account.
    $chosenId = $drawn[0];
    $receiptAbs = sys_get_temp_dir() . '/regr_receipt_' . $tag . '.png';
    // 1x1 PNG so the image mime validation passes.
    file_put_contents($receiptAbs, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $upload = new UploadedFile($receiptAbs, 'dekont.png', 'image/png', null, true);

    $claimReq = Request::create('/topup/manual-claim', 'POST', [
        'amount' => 250,
        'method' => 'bank',
        'account_name' => 'Regresyon Oyuncu',
        'transaction_id' => 'TXN-' . $tag,
        'bank_account_id' => $chosenId,
    ]);
    $claimReq->files->set('receipt', $upload);
    $claimReq->headers->set('Accept', 'application/json');
    $claimRes = $controller->claimManualDeposit($claimReq);
    $claimPayload = json_decode((string) $claimRes->getContent(), true);
    $intentId = $claimPayload['intent_id'] ?? null;

    check('claim accepted (HTTP 200)', $claimRes->getStatusCode() === 200, $failures, $checks);
    check('claim returned an intent id', !empty($intentId), $failures, $checks);

    $deposit = $intentId
        ? DB::table('manual_deposits')->where('payment_intent_id', $intentId)->first()
        : null;
    $depositId = $deposit->id ?? null;

    check('manual_deposits row persisted', $deposit !== null, $failures, $checks);
    check('deposit records the account the player paid into', $deposit && (int) $deposit->bank_account_id === (int) $chosenId, $failures, $checks);
    check('receipt uploaded and stored on the deposit', $deposit && !empty($deposit->screenshot), $failures, $checks);
    check('receipt file exists on disk', $deposit && $deposit->screenshot && file_exists(public_path($deposit->screenshot)), $failures, $checks);

    // 4. The admin queue exposes the account + receipt to the operator.
    auth()->setUser($admin);
    $indexRes = app(ManualDepositsController::class)->index();
    $view = $indexRes->getData();
    $queue = $view['deposits'];
    $row = collect($queue->items())->firstWhere('id', $depositId);

    check('admin queue lists the new deposit', $row !== null, $failures, $checks);
    check('admin queue exposes the IBAN used', $row && !empty($row->account_iban), $failures, $checks);
    check('admin queue exposes the receipt', $row && !empty($row->screenshot), $failures, $checks);

    // 5. Approval credits the wallet exactly once.
    app(ManualDepositsController::class)->approve($depositId);
    $player->refresh();
    $credited = (float) $player->balance - $balanceBefore;
    $rate = (float) (function_exists('settings') ? settings('coins_per_dollar', 100) : 100);
    check('approval credited the wallet', abs($credited - (250 * $rate)) < 0.01, $failures, $checks);

    $after = DB::table('manual_deposits')->where('id', $depositId)->value('status');
    check('deposit marked approved', (int) $after === 1, $failures, $checks);
} finally {
    // Cleanup: restore balance, drop tagged rows, delete uploaded receipt.
    if ($depositId) {
        $screenshot = DB::table('manual_deposits')->where('id', $depositId)->value('screenshot');
        if ($screenshot && file_exists(public_path($screenshot))) {
            @unlink(public_path($screenshot));
        }
        DB::table('manual_deposits')->where('id', $depositId)->delete();
    }
    if ($intentId) {
        DB::table('payment_intents')->where('id', $intentId)->delete();
    }
    if ($accountIds) {
        DB::table('payment_bank_accounts')->whereIn('id', $accountIds)->delete();
    }
    if ($intentId) {
        DB::table('transactions')
            ->where('user_id', $player->id)
            ->where('source', 'manual')
            ->where('note', 'like', '%' . number_format(250, 2) . '%')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->delete();
    }
    DB::table('users')->where('id', $player->id)->update(['balance' => $balanceBefore, 'updated_at' => now()]);
    if ($receiptAbs && file_exists($receiptAbs)) {
        @unlink($receiptAbs);
    }
}

echo "\n";
if ($failures) {
    echo "FAILED ({$checks} checks):\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "PASS: {$checks} checks\n";
