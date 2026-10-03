<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Regression for the "ödemeyi gerçekleştirdim" manual deposit claim flow.
 *
 * Drives the real TopupController::claimManualDeposit and the Liteback
 * ManualDepositsController approve/reject against the live DB, asserting that
 * the player's method + amount reach the admin queue, that approval credits the
 * wallet, and that rejection leaves the balance untouched. Test rows are tagged
 * and removed so the live ledger and queue are left untouched.
 *
 * Usage: php tests/Casino/manual-deposit-claim-regression.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
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

function claim(TopupController $controller, float $amount, string $method): array
{
    $request = Request::create('/topup/manual-claim', 'POST', [
        'amount' => $amount,
        'method' => $method,
    ]);
    $request->headers->set('Accept', 'application/json');
    $response = $controller->claimManualDeposit($request);

    return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
}

$player = User::whereNotNull('shop_id')->orderBy('id')->first();
$admin = User::where('role_id', 6)->orderBy('id')->first() ?: $player;

if (!$player || !$admin) {
    fwrite(STDERR, "FAIL: need a shop user and an admin user to test against\n");
    exit(1);
}

$currency = strtoupper($player->shop->currency ?? 'TRY');
$rate = (float) (function_exists('settings') ? settings('coins_per_dollar', 100) : 100);
if ($rate <= 0) {
    $rate = 100;
}

$createdIntents = [];
$startBalance = (float) $player->balance;

echo "Manual deposit claim regression (user #{$player->id}, {$currency}, rate {$rate})\n";

try {
    // 1) Missing method must be rejected before anything is persisted.
    auth()->setUser($player);
    [$status, $payload] = claim(app(TopupController::class), 2000.0, '');
    check('claim without a method is rejected (HTTP 422)', $status === 422, $failures, $checks);
    check('rejection carries an error message', !empty($payload['error'] ?? null), $failures, $checks);

    // 2) A valid claim opens an intent + a pending manual_deposit carrying the method and amount.
    [$status, $payload] = claim(app(TopupController::class), 2000.0, 'bank');
    check('valid claim returns HTTP 200', $status === 200, $failures, $checks);
    check('valid claim returns ok=true', ($payload['ok'] ?? false) === true, $failures, $checks);

    $intentId = (int) ($payload['intent_id'] ?? 0);
    $createdIntents[] = $intentId;
    $deposit = DB::table('manual_deposits')->where('payment_intent_id', $intentId)->first();
    $intent = DB::table('payment_intents')->where('id', $intentId)->first();

    check('manual_deposit row persisted', $deposit !== null, $failures, $checks);
    check('manual_deposit records the method (bank)', $deposit && $deposit->method === 'bank', $failures, $checks);
    check('manual_deposit records the amount (2000)', $deposit && abs((float) $deposit->amount - 2000.0) < 0.001, $failures, $checks);
    check('manual_deposit starts pending (status 0)', $deposit && (int) $deposit->status === 0, $failures, $checks);
    check('intent marked submitted', $intent && $intent->status === 'submitted', $failures, $checks);
    check('intent carries the shop currency', $intent && strtoupper((string) $intent->currency) === $currency, $failures, $checks);

    // 3) The admin queue surfaces the pending claim with its method + amount.
    auth()->setUser($admin);
    $index = app(ManualDepositsController::class)->index();
    $viewData = $index->getData();
    check('admin index returns a view', $index instanceof \Illuminate\View\View, $failures, $checks);
    check('admin index reports a pending count', ($viewData['pendingCount'] ?? 0) >= 1, $failures, $checks);
    $listed = $viewData['deposits']->firstWhere('id', $deposit->id);
    check('pending claim appears in the admin queue', $listed !== null, $failures, $checks);
    check('queue row shows the method', $listed && $listed->method === 'bank', $failures, $checks);
    check('queue row shows the amount', $listed && abs((float) ($listed->amount ?? 0) - 2000.0) < 0.001, $failures, $checks);

    // 4) Approval credits the wallet by amount * rate and settles the intent.
    app(ManualDepositsController::class)->approve($deposit->id);
    $player->refresh();
    $approvedDeposit = DB::table('manual_deposits')->where('id', $deposit->id)->first();
    $approvedIntent = DB::table('payment_intents')->where('id', $intentId)->first();
    $expectedCredit = 2000.0 * $rate;

    check('approval marks the deposit approved (status 1)', (int) $approvedDeposit->status === 1, $failures, $checks);
    check('approval settles the intent as paid', $approvedIntent->status === 'paid', $failures, $checks);
    check('approval credits amount * rate to the wallet', abs(((float) $player->balance - $startBalance) - $expectedCredit) < 0.01, $failures, $checks);
    check('approval records a ledger transaction', DB::table('transactions')->where('user_id', $player->id)->where('note', 'like', '%approved by admin%')->where('amount', $expectedCredit)->exists(), $failures, $checks);

    // 5) A rejected claim must leave the balance untouched.
    auth()->setUser($player);
    [, $payload2] = claim(app(TopupController::class), 15000.0, 'havale');
    $intentId2 = (int) ($payload2['intent_id'] ?? 0);
    $createdIntents[] = $intentId2;
    $deposit2 = DB::table('manual_deposits')->where('payment_intent_id', $intentId2)->first();
    $balanceBeforeReject = (float) $player->fresh()->balance;

    auth()->setUser($admin);
    $rejectRequest = Request::create('/liteback/payments/manual/' . $deposit2->id . '/reject', 'POST', [
        'admin_note' => 'Test rejection: receipt unreadable',
    ]);
    app(ManualDepositsController::class)->reject($rejectRequest, $deposit2->id);

    $rejectedDeposit = DB::table('manual_deposits')->where('id', $deposit2->id)->first();
    $rejectedIntent = DB::table('payment_intents')->where('id', $intentId2)->first();
    check('rejection marks the deposit rejected (status 2)', (int) $rejectedDeposit->status === 2, $failures, $checks);
    check('rejection stores the admin note', $rejectedDeposit->admin_note === 'Test rejection: receipt unreadable', $failures, $checks);
    check('rejection settles the intent as rejected', $rejectedIntent->status === 'rejected', $failures, $checks);
    check('rejection leaves the wallet untouched', abs(((float) $player->fresh()->balance) - $balanceBeforeReject) < 0.001, $failures, $checks);
} catch (\Throwable $e) {
    check('regression ran without exception: ' . $e->getMessage(), false, $failures, $checks);
} finally {
    // Restore the player's balance and drop every test row we created.
    DB::table('users')->where('id', $player->id)->update(['balance' => $startBalance]);
    DB::table('transactions')->where('user_id', $player->id)->where('note', 'like', '%approved by admin%')->where('created_at', '>=', now()->subMinutes(10))->delete();
    foreach ($createdIntents as $intentId) {
        if ($intentId) {
            DB::table('manual_deposits')->where('payment_intent_id', $intentId)->delete();
            DB::table('payment_intents')->where('id', $intentId)->delete();
        }
    }
}

echo "\n" . ($failures ? 'FAILED ' . count($failures) . '/' . $checks : 'PASSED ' . $checks . '/' . $checks) . " checks\n";
exit($failures ? 1 : 0);
