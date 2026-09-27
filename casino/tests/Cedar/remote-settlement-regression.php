<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/fixture-bootstrap.php';

use Illuminate\Support\Facades\DB;
use VanguardLTE\Services\PromexCedarService;
use VanguardLTE\Services\PromexCedarSettlementService;
use VanguardLTE\User;

$checks = 0;
function remoteSettlementCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
}

$client = new class extends PromexCedarService {
    public bool $failNext = false;
    public int $spinCalls = 0;
    public string $seed = '';

    public function init(string $game, string $scopeId): array
    {
        $this->seed = hash('sha256', $scopeId . '|initial');
        return [
            'status' => 'success', 'game' => $game, 'server_seed_hash' => hash('sha256', $this->seed), 'nonce' => 1,
            'presentation' => [
                'rows' => 3, 'reels' => 5, 'lines' => 20, 'wild' => 1,
                'symbols' => ['1' => 'S1', '2' => 'S2'], 'published_rtp' => 92.0,
                'min_wager' => '0.20', 'max_wager' => '100.00',
            ],
        ];
    }

    public function spin(
        string $game,
        string $scopeId,
        string $wager,
        string $clientSeed,
        string $serverSeedHash,
        ?string $requestId = null
    ): array {
        ++$this->spinCalls;
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException('Simulated ambiguous network failure.');
        }
        $next = hash('sha256', $scopeId . '|next');
        return [
            'status' => 'success', 'recovered' => $this->spinCalls > 1,
            'round_id' => 'hub-round-1', 'request_id' => $requestId,
            'next_server_seed_hash' => $next,
            'outcome' => [
                'game' => $game, 'math_version' => 'cedarcules.v1', 'wager' => $wager,
                'win_amount' => '25.00', 'multiplier' => 2.5, 'grid' => ['1', '2', '3'], 'line_wins' => [],
                'proof' => [
                    'server_seed' => $this->seed, 'server_seed_hash' => $serverSeedHash,
                    'client_seed' => $clientSeed, 'nonce' => 1,
                ],
            ],
            'proof' => ['signed_payload' => 'verified-by-client-boundary', 'signature' => 'test'],
        ];
    }
};
$service = new PromexCedarSettlementService($client);
DB::table('users')->where('id', 1)->update(['balance' => 1000]);

$init = $service->initialize(1, 'Cedarcules');
$state = json_decode((string) DB::table('cedar_states')->where('user_id', 1)->where('game', 'Cedarcules')->value('state'), true);
remoteSettlementCheck(
    preg_match('/^[a-f0-9]{64}$/D', $init['server_seed_hash']) === 1
    && preg_match('/^[a-f0-9]{32}$/D', $state['remote_scope_id']) === 1,
    'initialize stores an opaque player scope and Hub commitment'
);

$client->failNext = true;
$requestId = str_repeat('R', 24);
try {
    $service->settle(1, 'Cedarcules', $requestId, '10.00', 'browser-seed', $init['server_seed_hash']);
    remoteSettlementCheck(false, 'ambiguous failure leaves a recoverable reservation');
} catch (RuntimeException) {
    remoteSettlementCheck(
        (float) User::find(1)->balance === 990.0
        && DB::table('cedar_rounds')->where('request_id', $requestId)->where('status', 'pending')->count() === 1,
        'ambiguous failure leaves a recoverable reservation'
    );
}

try {
    $service->settle(1, 'Cedarcules', str_repeat('S', 24), '10.00', 'browser-seed', $init['server_seed_hash']);
    remoteSettlementCheck(false, 'a second request cannot bypass a pending reservation');
} catch (RuntimeException) {
    remoteSettlementCheck(
        (float) User::find(1)->balance === 990.0 && DB::table('cedar_rounds')->where('status', 'pending')->count() === 1,
        'a second request cannot bypass a pending reservation'
    );
}

$settled = $service->settle(1, 'Cedarcules', $requestId, '10.00', 'browser-seed', $init['server_seed_hash']);
remoteSettlementCheck(
    $settled['status'] === 'success' && $settled['win_amount'] === '25.00'
    && (float) User::find(1)->balance === 1015.0
    && DB::table('cedar_rounds')->where('request_id', $requestId)->where('status', 'settled')->count() === 1,
    'retry settles the reserved wager and signed outcome exactly once'
);
$balance = (float) User::find(1)->balance;
$auditCount = DB::table('stat_game')->count();
$commissionCount = DB::table('affiliate_commissions')->count();
$replay = $service->settle(1, 'Cedarcules', $requestId, '10.00', 'browser-seed', $init['server_seed_hash']);
remoteSettlementCheck(
    $replay === $settled && (float) User::find(1)->balance === $balance
    && DB::table('stat_game')->count() === $auditCount
    && DB::table('affiliate_commissions')->count() === $commissionCount,
    'settled replay returns the immutable result without paying twice'
);
$updatedState = json_decode((string) DB::table('cedar_states')->where('user_id', 1)->where('game', 'Cedarcules')->value('state'), true);
remoteSettlementCheck(
    $updatedState['server_seed_hash'] === $settled['next_server_seed_hash'] && $updatedState['nonce'] === 2,
    'settlement advances only the local commitment mirror'
);

echo "PASS: {$checks} remote Cedar settlement checks\n";
