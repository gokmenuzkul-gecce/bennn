<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end check for the Waija seamless-wallet webhook endpoint.
 *
 * Signs real callbacks the way Waija does (key = md5(timestamp + saltkey)),
 * drives them through the actual route/controller/wallet stack, and asserts the
 * site balance moves by the expected delta. GET is used, matching the Waija
 * docs; the balance travels in cents. Test rows are tagged and cleaned up.
 *
 * Usage: php tests/Casino/waija-webhook-e2e.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\User;

$salt = 'test-salt-abc';

// The running app reads credentials from its own config; align it for this run.
config([
    'casino_providers.waija.base_url' => 'https://staging.waija.example',
    'casino_providers.waija.api_login' => 'test-login',
    'casino_providers.waija.api_password' => 'test-password',
    'casino_providers.waija.salt_key' => $salt,
]);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = User::orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "FAIL: no user to test against\n");
    exit(1);
}

$username = CasinoProviderPlayer::codeFor((int) $user->id, 'waija', 'u');
$startBalance = (float) $user->balance;
$startedAt = now();
$tag = 'WAIJA' . substr((string) microtime(true), -6);
$failures = [];
$checks = 0;

/** Drive one signed callback through the real route stack. */
function callWebhook(Illuminate\Contracts\Http\Kernel $kernel, array $params, string $salt, ?string $keyOverride = null, ?string $tsOverride = null): array
{
    $timestamp = $tsOverride ?? (string) time();
    $params['timestamp'] = $timestamp;
    $params['key'] = $keyOverride ?? md5($timestamp . $salt);

    $query = http_build_query($params);
    $request = Illuminate\Http\Request::create('/webhooks/waija/callbacks?' . $query, 'GET');

    $response = $kernel->handle($request);
    $body = json_decode((string) $response->getContent(), true);

    return is_array($body) ? $body + ['_http' => $response->getStatusCode()] : ['_http' => $response->getStatusCode(), '_raw' => (string) $response->getContent()];
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

echo "Endpoint: GET /webhooks/waija/callbacks | user: {$user->id} | start balance: $startBalance" . PHP_EOL;

// --- balance ----------------------------------------------------------------
$bal = callWebhook($kernel, ['action' => 'balance', 'username' => $username, 'currency' => 'TRY'], $salt);
check($failures, $checks, 'balance returns HTTP 200', ($bal['_http'] ?? 0) === 200, 'http=' . ($bal['_http'] ?? '?'));
check($failures, $checks, 'balance equals site balance in cents',
    ($bal['error'] ?? -1) === 0 && ($bal['balance'] ?? -1) === (int) round($startBalance * 100),
    'balance=' . ($bal['balance'] ?? '?'));

// --- debit 20.00 ------------------------------------------------------------
$before = balanceOf((int) $user->id);
$debit = callWebhook($kernel, [
    'action' => 'debit', 'username' => $username, 'currency' => 'TRY',
    'amount' => 2000, 'call_id' => "$tag-debit", 'round_id' => "$tag-r", 'game_id' => 'test',
    'type' => 'spin', 'rb' => 0, 'gameplay_final' => 0,
], $salt);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'debit returns error 0', ($debit['error'] ?? -1) === 0, json_encode($debit));
check($failures, $checks, 'debit -20.00', abs(($after - $before) + 20.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- duplicate debit --------------------------------------------------------
$before = balanceOf((int) $user->id);
$dup = callWebhook($kernel, [
    'action' => 'debit', 'username' => $username, 'currency' => 'TRY',
    'amount' => 2000, 'call_id' => "$tag-debit", 'round_id' => "$tag-r", 'game_id' => 'test',
    'type' => 'spin', 'rb' => 0,
], $salt);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'duplicate call_id does not move balance', abs($after - $before) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- credit 25.00 -----------------------------------------------------------
$before = balanceOf((int) $user->id);
$credit = callWebhook($kernel, [
    'action' => 'credit', 'username' => $username, 'currency' => 'TRY',
    'amount' => 2500, 'call_id' => "$tag-credit", 'round_id' => "$tag-r", 'game_id' => 'test',
    'type' => 'spin', 'rb' => 0,
], $salt);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'credit +25.00', abs(($after - $before) - 25.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- rollback (rb=1) restores the debit -------------------------------------
$before = balanceOf((int) $user->id);
$rb = callWebhook($kernel, [
    'action' => 'credit', 'username' => $username, 'currency' => 'TRY',
    'amount' => 2000, 'call_id' => "$tag-rollback", 'round_id' => "$tag-r", 'game_id' => 'test',
    'type' => 'spin', 'rb' => 1,
], $salt);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'rollback applies like a movement', abs(($after - $before) - 20.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- bonus_fs debit does not touch cash -------------------------------------
$before = balanceOf((int) $user->id);
$fs = callWebhook($kernel, [
    'action' => 'debit', 'username' => $username, 'currency' => 'TRY',
    'amount' => 3000, 'call_id' => "$tag-fs", 'round_id' => "$tag-r", 'game_id' => 'test',
    'type' => 'bonus_fs', 'rb' => 0, 'freespins' => ['id' => 7, 'total' => 20, 'performed' => 3],
], $salt);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'bonus_fs debit leaves cash unchanged', abs($after - $before) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- insufficient funds -----------------------------------------------------
$ins = callWebhook($kernel, [
    'action' => 'debit', 'username' => $username, 'currency' => 'TRY',
    'amount' => 999999999, 'call_id' => "$tag-ins", 'round_id' => "$tag-r", 'game_id' => 'test',
    'type' => 'spin', 'rb' => 0,
], $salt);
check($failures, $checks, 'insufficient funds -> error 1', ($ins['error'] ?? -1) === 1, json_encode($ins));

// --- bad signature ----------------------------------------------------------
$bad = callWebhook($kernel, ['action' => 'balance', 'username' => $username, 'currency' => 'TRY'], $salt, str_repeat('0', 32));
check($failures, $checks, 'bad signature -> error 2', ($bad['error'] ?? -1) === 2, json_encode($bad));

// --- stale timestamp --------------------------------------------------------
$stale = callWebhook($kernel, ['action' => 'balance', 'username' => $username, 'currency' => 'TRY'], $salt, null, (string) (time() - 300));
check($failures, $checks, 'stale timestamp -> error 2', ($stale['error'] ?? -1) === 2, json_encode($stale));

// --- unknown player ---------------------------------------------------------
$unknown = callWebhook($kernel, ['action' => 'balance', 'username' => 'nobody-xyz', 'currency' => 'TRY'], $salt);
check($failures, $checks, 'unknown player -> error 2', ($unknown['error'] ?? -1) === 2, json_encode($unknown));

// --- Cleanup ----------------------------------------------------------------
DB::table('users')->where('id', $user->id)->update(['balance' => $startBalance]);
DB::table('casino_wallet_transactions')->where('provider_key', 'waija')->where('transaction_id', 'like', "$tag%")->delete();
DB::table('transactions')->where('source', 'casino')->where('created_at', '>=', $startedAt)->delete();
echo PHP_EOL . 'Temizlik: bakiye ' . balanceOf((int) $user->id) . ' (baslangic ' . $startBalance . ')' . PHP_EOL;

echo PHP_EOL . ($failures ? 'FAIL (' . count($failures) . '/' . $checks . ')' : 'PASS: ' . $checks . ' Waija checks') . PHP_EOL;
foreach ($failures as $f) {
    echo '  - ' . $f . PHP_EOL;
}
exit($failures ? 1 : 0);
