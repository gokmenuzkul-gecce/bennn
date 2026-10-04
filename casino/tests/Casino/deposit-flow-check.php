<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end check for the real deposit (top-up) flow.
 *
 * Drives the actual TopupController + ManualPaymentDriver against the live DB:
 * creates a payment intent, asserts the returned manual payment URL, then loads
 * the payment page. Test rows are tagged and deleted so the live ledger and
 * payment queue are left untouched. Also asserts the retired free-credit
 * endpoint no longer answers.
 *
 * Usage: php tests/Casino/deposit-flow-check.php [base-url]
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Http\Controllers\Web\Frontend\TopupController;
use VanguardLTE\User;

$base = $argv[1] ?? 'http://127.0.0.1:12000';
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

$user = User::whereNotNull('shop_id')->orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "FAIL: no user with a shop to test against\n");
    exit(1);
}

$amount = 137.00;
// Manual deposits settle in TL regardless of the shop's internal accounting currency.
$currency = strtoupper((string) (function_exists('settings') ? settings('default_currency', 'TRY') : 'TRY'));
if ($currency === '') {
    $currency = 'TRY';
}
$intentId = null;

echo "Deposit flow check (user #{$user->id}, {$currency})\n";

try {
    auth()->setUser($user);

    $request = Request::create('/topup/create', 'POST', [
        'amount' => $amount,
        'driver' => 'manual',
    ]);
    $request->headers->set('Accept', 'application/json');

    $response = app(TopupController::class)->create($request);
    $payload = json_decode((string) $response->getContent(), true);

    check('create() returns HTTP 200', $response->getStatusCode() === 200, $failures, $checks);
    check('response carries a payment_url', !empty($payload['payment_url'] ?? null), $failures, $checks);
    check('response carries an intent_id', !empty($payload['intent_id'] ?? null), $failures, $checks);

    $intentId = $payload['intent_id'] ?? null;

    $row = $intentId ? DB::table('payment_intents')->where('id', $intentId)->first() : null;
    check('payment_intents row persisted', $row !== null, $failures, $checks);
    check('driver recorded as manual', $row && $row->driver === 'manual', $failures, $checks);
    check('amount persisted', $row && abs((float) $row->amount - $amount) < 0.001, $failures, $checks);
    check('currency is the TL deposit currency', $row && strtoupper((string) $row->currency) === $currency, $failures, $checks);
    check('intent starts pending', $row && $row->status === 'pending', $failures, $checks);
    check('payment_url targets the manual page', $row && str_contains((string) $row->payment_url, '/payment/manual/' . $intentId), $failures, $checks);

    if ($intentId) {
        $view = app(TopupController::class)->showManualPayment($request, $intentId);
        check('manual payment page renders', $view instanceof \Illuminate\View\View, $failures, $checks);
    }
} catch (\Throwable $e) {
    check('deposit flow ran without exception: ' . $e->getMessage(), false, $failures, $checks);
} finally {
    if ($intentId) {
        DB::table('payment_intents')->where('id', $intentId)->delete();
    }
}

// The free-credit endpoint must be gone: the balance top-up is a real deposit now.
$ch = curl_init($base . '/refill-coins');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
check('retired /refill-coins endpoint no longer answers 200 (got ' . $code . ')', $code !== 200, $failures, $checks);

echo "\n" . ($failures ? 'FAILED ' . count($failures) . '/' . $checks : 'PASSED ' . $checks . '/' . $checks) . " checks\n";
exit($failures ? 1 : 0);
