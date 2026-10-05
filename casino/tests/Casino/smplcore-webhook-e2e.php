<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * End-to-end check for the smpl core seamless-wallet webhook.
 *
 * Signs real callbacks the way smpl core does (HMAC-SHA1 over the sorted merge
 * of the form body and the auth headers), posts them to the registered callback
 * URL, and asserts the site balance moves by the expected delta. Test rows are
 * tagged and cleaned up so the live ledger is left untouched.
 *
 * Usage: php tests/Casino/smplcore-webhook-e2e.php [base-url] [merchant-id] [merchant-key]
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use VanguardLTE\User;

$base = $argv[1] ?? 'http://127.0.0.1:12000';
$merchantId = $argv[2] ?? (string) config('casino_providers.smplcore.merchant_id', '');
$merchantKey = $argv[3] ?? (string) config('casino_providers.smplcore.merchant_key', '');

if ($merchantId === '' || $merchantKey === '') {
    fwrite(STDERR, "SKIP: smpl core merchant credentials are not set (pass as argv or set SMPL_MERCHANT_ID/KEY)\n");
    exit(0);
}

// The running app reads credentials from its own config; align it for this run.
config([
    'casino_providers.smplcore.merchant_id' => $merchantId,
    'casino_providers.smplcore.merchant_key' => $merchantKey,
]);

$user = User::orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "FAIL: no user to test against\n");
    exit(1);
}

$startBalance = (float) $user->balance;
$startedAt = now();
$tag = 'SMPL' . substr((string) microtime(true), -6);
$failures = [];
$checks = 0;

/** Independent HMAC-SHA1 signature, mirroring the smpl core docs. */
function smplSign(array $params, string $merchantId, string $timestamp, string $nonce, string $merchantKey): string
{
    $merged = array_merge($params, [
        'X-Merchant-Id' => $merchantId,
        'X-Timestamp' => $timestamp,
        'X-Nonce' => $nonce,
    ]);
    ksort($merged);

    return hash_hmac('sha1', http_build_query($merged), $merchantKey);
}

/** POST a signed webhook and return the decoded JSON. */
function postWebhook(string $url, array $payload, string $merchantId, string $merchantKey, ?string $overrideSign = null): array
{
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $sign = $overrideSign ?? smplSign($payload, $merchantId, $timestamp, $nonce, $merchantKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'X-Merchant-Id: ' . $merchantId,
            'X-Timestamp: ' . $timestamp,
            'X-Nonce: ' . $nonce,
            'X-Sign: ' . $sign,
        ],
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

$url = rtrim($base, '/') . '/webhooks/smplcore/callbacks';
$player = (string) $user->id;

echo "Base: $url | user: {$user->id} | start balance: $startBalance" . PHP_EOL;

// --- balance ----------------------------------------------------------------
$bal = postWebhook($url, ['action' => 'balance', 'player_id' => $player, 'currency' => 'TRY'], $merchantId, $merchantKey);
check($failures, $checks, 'balance returns HTTP 200', ($bal['_http'] ?? 0) === 200, 'http=' . ($bal['_http'] ?? '?'));
check($failures, $checks, 'balance equals site balance',
    isset($bal['balance']) && abs((float) $bal['balance'] - $startBalance) < 0.001,
    'balance=' . ($bal['balance'] ?? '?'));

// --- bet: -10 ---------------------------------------------------------------
$before = balanceOf((int) $user->id);
$bet = postWebhook($url, [
    'action' => 'bet', 'amount' => '10.00', 'currency' => 'TRY', 'game_uuid' => 'test',
    'player_id' => $player, 'transaction_id' => "$tag-bet", 'session_id' => "$tag-sess",
    'type' => 'bet', 'round_id' => "$tag-r", 'finished' => 'false',
], $merchantId, $merchantKey);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'bet returns balance', isset($bet['balance']), 'resp=' . json_encode($bet));
check($failures, $checks, 'bet -10.00', abs(($after - $before) + 10.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- win: +25 ---------------------------------------------------------------
$before = balanceOf((int) $user->id);
$win = postWebhook($url, [
    'action' => 'win', 'amount' => '25.00', 'currency' => 'TRY', 'game_uuid' => 'test',
    'player_id' => $player, 'transaction_id' => "$tag-win", 'session_id' => "$tag-sess",
    'type' => 'win', 'round_id' => "$tag-r",
], $merchantId, $merchantKey);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'win +25.00', abs(($after - $before) - 25.0) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- duplicate win is idempotent -------------------------------------------
$before = balanceOf((int) $user->id);
$dup = postWebhook($url, [
    'action' => 'win', 'amount' => '25.00', 'currency' => 'TRY', 'game_uuid' => 'test',
    'player_id' => $player, 'transaction_id' => "$tag-win", 'session_id' => "$tag-sess",
    'type' => 'win', 'round_id' => "$tag-r",
], $merchantId, $merchantKey);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'duplicate win does not move balance',
    abs($after - $before) < 0.001, sprintf('%.2f -> %.2f', $before, $after));

// --- rollback bet+win: net -15 ---------------------------------------------
$before = balanceOf((int) $user->id);
$rb = postWebhook($url, [
    'action' => 'rollback', 'player_id' => $player, 'currency' => 'TRY', 'game_uuid' => 'test',
    'transaction_id' => "$tag-rb", 'session_id' => "$tag-sess", 'round_id' => "$tag-r",
    'rollback_transactions' => json_encode([
        ['action' => 'bet', 'amount' => '10.00', 'transaction_id' => "$tag-bet"],
        ['action' => 'win', 'amount' => '25.00', 'transaction_id' => "$tag-win"],
    ]),
], $merchantId, $merchantKey);
$after = balanceOf((int) $user->id);
check($failures, $checks, 'rollback returns balance', isset($rb['balance']), 'resp=' . json_encode($rb));
check($failures, $checks, 'rollback restores balance',
    abs($after - $startBalance) < 0.001, sprintf('%.2f (start %.2f)', $after, $startBalance));

// --- insufficient funds -----------------------------------------------------
$ins = postWebhook($url, [
    'action' => 'bet', 'amount' => '999999999.00', 'currency' => 'TRY', 'game_uuid' => 'test',
    'player_id' => $player, 'transaction_id' => "$tag-ins", 'session_id' => "$tag-sess",
    'type' => 'bet',
], $merchantId, $merchantKey);
check($failures, $checks, 'insufficient funds rejected',
    ($ins['error_code'] ?? '') === 'INSUFFICIENT_FUNDS', 'resp=' . json_encode($ins));

// --- bad signature ----------------------------------------------------------
$bad = postWebhook($url, ['action' => 'balance', 'player_id' => $player, 'currency' => 'TRY'],
    $merchantId, $merchantKey, str_repeat('0', 40));
check($failures, $checks, 'bad signature rejected',
    ($bad['error_code'] ?? '') === 'INTERNAL_ERROR' && ($bad['error_description'] ?? '') === 'Invalid signature',
    'resp=' . json_encode($bad));

// --- Cleanup ----------------------------------------------------------------
DB::table('users')->where('id', $user->id)->update(['balance' => $startBalance]);
DB::table('casino_wallet_transactions')->where('transaction_id', 'like', "$tag%")->delete();
DB::table('transactions')->where('source', 'casino')->where('created_at', '>=', $startedAt)->delete();
echo PHP_EOL . 'Temizlik: bakiye ' . balanceOf((int) $user->id) . ' (baslangic ' . $startBalance . ')' . PHP_EOL;

echo PHP_EOL . ($failures ? 'FAIL (' . count($failures) . '/' . $checks . ')' : 'PASS: ' . $checks . ' smpl core checks') . PHP_EOL;
foreach ($failures as $f) {
    echo '  - ' . $f . PHP_EOL;
}
exit($failures ? 1 : 0);
