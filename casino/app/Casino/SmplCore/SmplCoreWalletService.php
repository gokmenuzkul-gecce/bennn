<?php

namespace VanguardLTE\Casino\SmplCore;

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Models\CasinoWalletTransaction;
use VanguardLTE\User;

/**
 * Applies smpl core webhook actions to the site balance.
 *
 * smpl core uses a different vocabulary from the legacy aggregator: a single
 * endpoint receives an "action" of balance/bet/win/refund/rollback, and the
 * reply is a JSON body with either {"balance", "transaction_id"} on success or
 * {"error_code", "error_description"} on failure. HTTP status is always 200;
 * errors travel in the body.
 *
 * Every mutation is written to the site ledger and to casino_wallet_transactions
 * so repeated callbacks (same transaction_id) stay idempotent.
 */
class SmplCoreWalletService
{
    public const PROVIDER_KEY = 'smplcore';

    public const INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    public const INTERNAL_ERROR = 'INTERNAL_ERROR';

    public function handle(string $action, array $payload): array
    {
        return match (strtolower($action)) {
            'balance' => $this->balance($payload),
            'bet' => $this->bet($payload),
            'win' => $this->win($payload),
            'refund' => $this->refund($payload),
            'rollback' => $this->rollback($payload),
            default => $this->error(self::INTERNAL_ERROR, 'Unknown action: ' . $action),
        };
    }

    public function balance(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::INTERNAL_ERROR, 'Player not found');
        }

        return ['balance' => round((float) $user->balance, 2)];
    }

    public function bet(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::INTERNAL_ERROR, 'Player not found');
        }

        $amount = $this->money($payload, 'amount');
        $transactionId = $this->transactionId($payload);
        if ($transactionId === '') {
            return $this->error(self::INTERNAL_ERROR, 'Missing transaction_id');
        }

        return $this->commit($user, 'bet', $transactionId, $payload, -1 * $amount);
    }

    public function win(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::INTERNAL_ERROR, 'Player not found');
        }

        $amount = $this->money($payload, 'amount');
        $transactionId = $this->transactionId($payload);
        if ($transactionId === '') {
            return $this->error(self::INTERNAL_ERROR, 'Missing transaction_id');
        }

        return $this->commit($user, 'win', $transactionId, $payload, $amount);
    }

    public function refund(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::INTERNAL_ERROR, 'Player not found');
        }

        $amount = $this->money($payload, 'amount');
        $transactionId = $this->transactionId($payload);
        if ($transactionId === '') {
            return $this->error(self::INTERNAL_ERROR, 'Missing transaction_id');
        }

        return $this->commit($user, 'refund', $transactionId, $payload, $amount);
    }

    /**
     * Roll back a set of previously committed transactions.
     *
     * smpl core sends rollback_transactions as a list of {transaction_id,
     * action, amount}. A bet is credited back, a win/refund is deducted.
     */
    public function rollback(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::INTERNAL_ERROR, 'Player not found');
        }

        $rollbackTxnId = $this->transactionId($payload);
        if ($rollbackTxnId === '') {
            return $this->error(self::INTERNAL_ERROR, 'Missing transaction_id');
        }

        $transactions = $this->rollbackTransactions($payload);
        if ($transactions === []) {
            return $this->error(self::INTERNAL_ERROR, 'Missing rollback_transactions');
        }

        return DB::transaction(function () use ($user, $rollbackTxnId, $payload, $transactions) {
            $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $rollbackTxnId);
            if ($existing) {
                return [
                    'balance' => round((float) $existing->balance_after, 2),
                    'transaction_id' => $existing->transaction_id,
                    'rollback_transactions' => $this->decodedRolledBackIds($existing),
                ];
            }

            $locked = User::lockForUpdate()->find($user->id);
            $before = (float) $locked->balance;
            $adjustment = 0.0;
            $rolledBackIds = [];

            foreach ($transactions as $txn) {
                $aggTxnId = (string) ($txn['transaction_id'] ?? '');
                if ($aggTxnId === '') {
                    continue;
                }

                $original = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $aggTxnId);
                if (!$original) {
                    continue;
                }

                if ($original->status === 'rolled_back') {
                    $rolledBackIds[] = $original->transaction_id;
                    continue;
                }

                $amount = (float) ($txn['amount'] ?? $original->amount);
                $action = strtolower((string) ($txn['action'] ?? $original->operation));

                if ($action === 'bet') {
                    $adjustment += $amount;
                } elseif ($action === 'win' || $action === 'refund') {
                    $adjustment -= $amount;
                }

                $original->status = 'rolled_back';
                $original->save();

                $rolledBackIds[] = $original->transaction_id;
            }

            $after = $before + $adjustment;
            if ($after < 0) {
                $after = 0.0;
            }

            $locked->balance = $after;
            $locked->save();

            CasinoWalletTransaction::create([
                'provider_key' => self::PROVIDER_KEY,
                'user_id' => $locked->id,
                'transaction_id' => $rollbackTxnId,
                'round_id' => (string) ($payload['round_id'] ?? ''),
                'game_id' => (string) ($payload['game_uuid'] ?? ''),
                'operation' => 'rollback',
                'reference_transaction_id' => '',
                'bet_amount' => 0,
                'win_amount' => 0,
                'amount' => abs($adjustment),
                'balance_after' => $after,
                'status' => 'committed',
            ]);

            $this->recordLedger(
                $locked,
                'RollbackTransaction',
                $after - $before,
                $before,
                $after,
                'smpl core rollback (' . count($rolledBackIds) . ' işlem)'
            );

            return [
                'balance' => round($after, 2),
                'transaction_id' => $rollbackTxnId,
                'rollback_transactions' => $rolledBackIds,
            ];
        });
    }

    /**
     * Apply a single balance delta with idempotency and row locking.
     *
     * $delta is the signed change to the balance: negative for a bet, positive
     * for a win or refund.
     */
    private function commit(User $user, string $operation, string $transactionId, array $payload, float $delta): array
    {
        return DB::transaction(function () use ($user, $operation, $transactionId, $payload, $delta) {
            $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionId);
            if ($existing) {
                return [
                    'balance' => round((float) $existing->balance_after, 2),
                    'transaction_id' => $existing->transaction_id,
                ];
            }

            $locked = User::lockForUpdate()->find($user->id);
            $before = (float) $locked->balance;
            $after = $before + $delta;

            if ($after < 0) {
                return $this->error(self::INSUFFICIENT_FUNDS, 'Player balance is too low');
            }

            $locked->balance = $after;
            $locked->save();

            CasinoWalletTransaction::create([
                'provider_key' => self::PROVIDER_KEY,
                'user_id' => $locked->id,
                'transaction_id' => $transactionId,
                'round_id' => (string) ($payload['round_id'] ?? ''),
                'game_id' => (string) ($payload['game_uuid'] ?? ''),
                'operation' => $operation,
                'reference_transaction_id' => (string) ($payload['session_id'] ?? ''),
                'bet_amount' => $operation === 'bet' ? abs($delta) : 0,
                'win_amount' => in_array($operation, ['win', 'refund'], true) ? abs($delta) : 0,
                'amount' => abs($delta),
                'balance_after' => $after,
                'status' => 'committed',
            ]);

            $this->recordLedger(
                $locked,
                ucfirst($operation),
                $after - $before,
                $before,
                $after,
                'smpl core ' . $operation
            );

            return [
                'balance' => round($after, 2),
                'transaction_id' => $transactionId,
            ];
        });
    }

    private function resolveUser(array $payload): ?User
    {
        $playerId = (string) ($payload['player_id'] ?? '');
        if ($playerId === '') {
            return null;
        }

        if (ctype_digit($playerId)) {
            $user = User::find((int) $playerId);
            if ($user) {
                return $user;
            }
        }

        $userId = CasinoProviderPlayer::resolveUserId(self::PROVIDER_KEY, $playerId);

        return $userId ? User::find($userId) : null;
    }

    private function transactionId(array $payload): string
    {
        return (string) ($payload['transaction_id'] ?? '');
    }

    /** @return array<int, array<string, mixed>> */
    private function rollbackTransactions(array $payload): array
    {
        $raw = $payload['rollback_transactions'] ?? null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_array'));
            }
        }

        if (is_array($raw)) {
            return array_values(array_filter($raw, 'is_array'));
        }

        return [];
    }

    /** @return array<int, string> */
    private function decodedRolledBackIds(CasinoWalletTransaction $transaction): array
    {
        $decoded = json_decode((string) $transaction->reference_transaction_id, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function recordLedger(User $user, string $operation, float $delta, float $before, float $after, string $note): void
    {
        DB::table('transactions')->insert([
            'user_id' => $user->id,
            'admin_id' => null,
            'direction' => $delta >= 0 ? 'add' : 'deduct',
            'amount' => abs($delta),
            'balance_before' => $before,
            'balance_after' => $after,
            'source' => 'casino',
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function money(array $payload, string $field): float
    {
        return round((float) ($payload[$field] ?? 0), 2);
    }

    /** @return array{error_code: string, error_description: string} */
    private function error(string $code, string $description): array
    {
        return ['error_code' => $code, 'error_description' => $description];
    }
}
