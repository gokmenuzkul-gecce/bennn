<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use VanguardLTE\StatGame;
use VanguardLTE\User;

/** Keeps wallets local while a licensed Hub installation supplies signed outcomes. */
final class PromexCedarSettlementService
{
    public function __construct(private readonly ?PromexCedarService $cedar = null) {}

    public function initialize(int $userId, string $game): array
    {
        $state = DB::transaction(function () use ($userId, $game): array {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $this->assertPlayable($user);
            $row = DB::table('cedar_states')->where('user_id', $userId)->where('game', $game)->lockForUpdate()->first();
            $state = $row ? json_decode((string) $row->state, true, 32, JSON_THROW_ON_ERROR) : [];
            if (!preg_match('/^[a-f0-9]{32}$/D', (string) ($state['remote_scope_id'] ?? ''))) {
                $state = [
                    'remote_scope_id' => bin2hex(random_bytes(16)),
                    'client_seed' => bin2hex(random_bytes(8)),
                    'server_seed_hash' => null,
                    'nonce' => 1,
                    'active' => null,
                ];
                DB::table('cedar_states')->updateOrInsert(
                    ['user_id' => $userId, 'game' => $game],
                    ['state' => json_encode($state, JSON_THROW_ON_ERROR)]
                );
            }
            return $state;
        }, 3);

        $commitment = $this->client()->init($game, $state['remote_scope_id']);
        DB::transaction(function () use ($userId, $game, $state, $commitment): void {
            $row = DB::table('cedar_states')->where('user_id', $userId)->where('game', $game)->lockForUpdate()->first();
            $current = $row ? json_decode((string) $row->state, true, 32, JSON_THROW_ON_ERROR) : [];
            if (($current['remote_scope_id'] ?? null) !== $state['remote_scope_id']) {
                throw new RuntimeException('The Cedar play scope changed. Initialize again.');
            }
            $current['server_seed_hash'] = $commitment['server_seed_hash'];
            $current['nonce'] = (int) $commitment['nonce'];
            $current['min_wager'] = $commitment['presentation']['min_wager'];
            $current['max_wager'] = $commitment['presentation']['max_wager'];
            DB::table('cedar_states')->where('user_id', $userId)->where('game', $game)
                ->update(['state' => json_encode($current, JSON_THROW_ON_ERROR)]);
        }, 3);

        return [
            'server_seed_hash' => $commitment['server_seed_hash'],
            'client_seed' => $state['client_seed'],
            'nonce' => (int) $commitment['nonce'],
            'presentation' => $commitment['presentation'],
        ];
    }

    public function settle(
        int $userId,
        string $game,
        string $requestId,
        string $wager,
        string $clientSeed,
        string $serverSeedHash
    ): array {
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $requestId)) {
            throw new RuntimeException('The Cedar request identifier is invalid.');
        }
        $wager = $this->money($wager);
        $clientSeed = trim($clientSeed);
        $serverSeedHash = strtolower(trim($serverSeedHash));
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $clientSeed)
            || !preg_match('/^[a-f0-9]{64}$/D', $serverSeedHash)) {
            throw new RuntimeException('The Cedar fairness inputs are invalid.');
        }
        $request = compact('game', 'requestId', 'wager', 'clientSeed', 'serverSeedHash');
        $requestHash = hash('sha256', json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $reservation = DB::transaction(function () use ($userId, $game, $requestId, $wager, $clientSeed, $serverSeedHash, $requestHash): array {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $this->assertPlayable($user);
            $existing = DB::table('cedar_rounds')->where('user_id', $userId)->where('game', $game)
                ->where('request_id', $requestId)->lockForUpdate()->first();
            if ($existing) {
                $data = json_decode((string) $existing->data, true, 128, JSON_THROW_ON_ERROR);
                if (!hash_equals((string) ($data['request_sha256'] ?? ''), $requestHash)) {
                    throw new RuntimeException('This Cedar request ID was already used with different input.');
                }
                if ($existing->status === 'settled') return ['settled' => $data['result']];
                return ['round_id' => $existing->id, 'data' => $data];
            }
            if (DB::table('cedar_rounds')->where('user_id', $userId)->where('game', $game)
                ->where('status', 'pending')->lockForUpdate()->exists()) {
                throw new RuntimeException('A previous Cedar spin is still being recovered.');
            }
            $stateRow = DB::table('cedar_states')->where('user_id', $userId)->where('game', $game)->lockForUpdate()->first();
            $state = $stateRow ? json_decode((string) $stateRow->state, true, 32, JSON_THROW_ON_ERROR) : [];
            if (!preg_match('/^[a-f0-9]{32}$/D', (string) ($state['remote_scope_id'] ?? ''))
                || !hash_equals((string) ($state['server_seed_hash'] ?? ''), strtolower($serverSeedHash))) {
                throw new RuntimeException('The Cedar commitment changed. Initialize again.');
            }
            if ((float) $wager < (float) ($state['min_wager'] ?? INF)
                || (float) $wager > (float) ($state['max_wager'] ?? -INF)) {
                throw new RuntimeException('The Cedar wager is outside the licensed game range.');
            }
            if ((float) $user->balance < (float) $wager) throw new RuntimeException('Insufficient Cedar Coins balance.');
            $roundId = (string) Str::uuid();
            $hubRequestId = substr(hash('sha256', 'cedar-v1|' . $state['remote_scope_id'] . '|' . $requestId), 0, 32);
            $data = [
                'request_sha256' => $requestHash,
                'hub_request_id' => $hubRequestId,
                'scope_id' => $state['remote_scope_id'],
                'client_seed' => $clientSeed,
                'server_seed_hash' => strtolower($serverSeedHash),
                'result' => null,
            ];
            $user->decrement('balance', (float) $wager);
            DB::table('cedar_rounds')->insert([
                'id' => $roundId, 'user_id' => $userId, 'game' => $game, 'request_id' => $requestId,
                'status' => 'pending', 'wager' => $wager, 'win' => 0,
                'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['round_id' => $roundId, 'data' => $data];
        }, 5);
        if (isset($reservation['settled'])) return $reservation['settled'];

        $remote = $this->client()->spin(
            $game, $reservation['data']['scope_id'], $wager, $clientSeed,
            strtolower($serverSeedHash), $reservation['data']['hub_request_id']
        );

        return DB::transaction(function () use ($userId, $game, $requestId, $requestHash, $reservation, $remote, $wager, $clientSeed): array {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $round = DB::table('cedar_rounds')->where('id', $reservation['round_id'])->lockForUpdate()->firstOrFail();
            $data = json_decode((string) $round->data, true, 128, JSON_THROW_ON_ERROR);
            if (!hash_equals((string) ($data['request_sha256'] ?? ''), $requestHash)) {
                throw new RuntimeException('The pending Cedar round changed unexpectedly.');
            }
            if ($round->status === 'settled') return $data['result'];
            if ($round->status !== 'pending') throw new RuntimeException('The Cedar round is not recoverable.');

            $win = $this->money((string) ($remote['outcome']['win_amount'] ?? ''));
            if ((float) $win > 0) $user->increment('balance', (float) $win);
            AffiliateService::recordWagerCommission($user, (float) $wager, strtolower($game), true);
            VipService::recordWagerXpAndRakeback($user, (float) $wager, 5.0);
            $user->refresh();
            (new StatGame([
                'user_id' => $user->id, 'balance' => $user->balance, 'bet' => $wager, 'win' => $win,
                'game' => $game, 'in_game' => 1, 'shop_id' => $user->shop_id ?: 1, 'date_time' => now(),
            ]))->saveQuietly();

            $proofInput = $remote['outcome']['proof'] ?? [];
            $result = [
                'status' => 'success', 'bet_id' => $round->id, 'request_id' => $requestId,
                'wager' => $wager, 'grid' => $remote['outcome']['grid'],
                'line_wins' => $remote['outcome']['line_wins'], 'win_amount' => $win, 'total_win' => $win,
                'server_seed' => $proofInput['server_seed'] ?? null,
                'revealed_server_seed' => $proofInput['server_seed'] ?? null,
                'server_seed_hash' => $proofInput['server_seed_hash'] ?? null,
                'next_server_seed_hash' => $remote['next_server_seed_hash'],
                'client_seed' => $clientSeed, 'nonce' => $proofInput['nonce'] ?? null,
                'math_version' => $remote['outcome']['math_version'], 'proof' => $remote['proof'],
            ];
            $user->refresh();
            $result['balance'] = number_format((float) $user->balance, 2, '.', '');
            $result['new_balance'] = $result['balance'];
            $data['hub_round_id'] = $remote['round_id'];
            $data['result'] = $result;
            DB::table('cedar_rounds')->where('id', $round->id)->update([
                'status' => 'settled', 'win' => $win,
                'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            $stateRow = DB::table('cedar_states')->where('user_id', $userId)->where('game', $game)->lockForUpdate()->firstOrFail();
            $state = json_decode((string) $stateRow->state, true, 32, JSON_THROW_ON_ERROR);
            $state['server_seed_hash'] = $remote['next_server_seed_hash'];
            $state['client_seed'] = $clientSeed;
            $state['nonce'] = ((int) ($proofInput['nonce'] ?? 0)) + 1;
            DB::table('cedar_states')->where('user_id', $userId)->where('game', $game)
                ->update(['state' => json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            return $result;
        }, 5);
    }

    private function client(): PromexCedarService
    {
        return $this->cedar ?? new PromexCedarService();
    }

    private function assertPlayable(User $user): void
    {
        if ($user->is_blocked || $user->status !== 'Active') {
            throw new RuntimeException('This account cannot play.');
        }
    }

    private function money(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?$/D', $value, $match)) {
            throw new RuntimeException('The Cedar amount is invalid.');
        }
        return $match[1] . '.' . str_pad((string) ($match[2] ?? ''), 2, '0');
    }
}
