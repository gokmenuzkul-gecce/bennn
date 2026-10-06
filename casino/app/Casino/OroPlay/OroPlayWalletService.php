<?php

namespace VanguardLTE\Casino\OroPlay;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Models\CasinoWalletTransaction;
use VanguardLTE\User;

/**
 * Applies OroPlay wallet callbacks to the site balance.
 *
 * OroPlay posts three operator-side endpoints, all authenticated with HTTP
 * Basic and all answered with {"success", "message", "errorCode"} where
 * "message" carries the resulting balance:
 *
 *   - POST /api/balance            -> current balance
 *   - POST /api/transaction        -> one bet or win (amount<0 debit, >0 credit)
 *   - POST /api/batch-transactions -> a list of the same
 *
 * transactionCode is the idempotency key: a repeated code is answered with
 * DUPLICATE_TRANSACTION and the stored balance so the round is not applied
 * twice. A transaction that arrives for a round already marked finished is
 * rejected with INVALID_TRANSACTION.
 */
class OroPlayWalletService
{
    public const PROVIDER_KEY = 'oroplay';

    // OroPlay error codes (see the API reference "Error Codes").
    public const NO_ERROR = 0;
    public const USER_DOES_NOT_EXIST = 2;
    public const INSUFFICIENT_USER_BALANCE = 4;
    public const DUPLICATE_TRANSACTION = 6;
    public const INVALID_TRANSACTION = 7;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const UNKNOWN_SERVER_ERROR = 500;

    /** Finished rounds are remembered briefly so late messages are rejected. */
    private const FINISHED_ROUND_TTL = 86400;

    /** @return array{success: bool, message: float|string, errorCode: int} */
    public function balance(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::USER_DOES_NOT_EXIST, 'User does not exist');
        }

        return $this->ok(round((float) $user->balance, 2));
    }

    /** @return array{success: bool, message: float|string, errorCode: int} */
    public function transaction(array $payload): array
    {
        return $this->applyTransaction($payload);
    }

    /**
     * Apply a list of transactions and report the balance after the last one.
     *
     * @return array{success: bool, message: float|string, errorCode: int}
     */
    public function batchTransactions(array $payload): array
    {
        $transactions = $payload['transactions'] ?? null;
        if (!is_array($transactions) || $transactions === []) {
            return $this->error(self::BAD_REQUEST, 'Missing transactions');
        }

        $lastBalance = null;
        foreach ($transactions as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $result = $this->applyTransaction($entry);
            if ((int) $result['errorCode'] !== self::NO_ERROR && (int) $result['errorCode'] !== self::DUPLICATE_TRANSACTION) {
                return $result;
            }
            if (is_numeric($result['message'])) {
                $lastBalance = (float) $result['message'];
            }
        }

        if ($lastBalance === null) {
            return $this->error(self::BAD_REQUEST, 'No valid transactions');
        }

        return $this->ok($lastBalance);
    }

    /**
     * Apply one signed transaction: a negative amount debits the balance (bet),
     * a positive amount credits it (win). A cancelled transaction reverses the
     * direction of its amount.
     *
     * @return array{success: bool, message: float|string, errorCode: int}
     */
    private function applyTransaction(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::USER_DOES_NOT_EXIST, 'User does not exist');
        }

        $transactionCode = (string) ($payload['transactionCode'] ?? '');
        if ($transactionCode === '') {
            return $this->error(self::BAD_REQUEST, 'Missing transactionCode');
        }

        $roundId = (string) ($payload['roundId'] ?? '');

        // Idempotency: a repeated transactionCode is not applied again.
        $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionCode);
        if ($existing) {
            return $this->ok((float) $existing->balance_after, self::DUPLICATE_TRANSACTION);
        }

        if ($roundId !== '' && $this->roundIsFinished($roundId)) {
            return $this->error(self::INVALID_TRANSACTION, 'Round is already finished');
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $cancelled = filter_var($payload['isCanceled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $delta = $cancelled ? -1 * $amount : $amount;
        $operation = $delta < 0 ? 'bet' : 'win';

        return DB::transaction(function () use ($user, $transactionCode, $roundId, $payload, $delta, $operation) {
            $locked = User::lockForUpdate()->find($user->id);
            $before = (float) $locked->balance;
            $after = $before + $delta;

            if ($after < 0) {
                return $this->error(self::INSUFFICIENT_USER_BALANCE, 'User does not have enough balance');
            }

            $locked->balance = $after;
            $locked->save();

            CasinoWalletTransaction::create([
                'provider_key' => self::PROVIDER_KEY,
                'user_id' => $locked->id,
                'transaction_id' => $transactionCode,
                'round_id' => $roundId,
                'game_id' => (string) ($payload['gameCode'] ?? ''),
                'operation' => $operation,
                'reference_transaction_id' => (string) ($payload['historyId'] ?? ''),
                'bet_amount' => $operation === 'bet' ? abs($delta) : 0,
                'win_amount' => $operation === 'win' ? abs($delta) : 0,
                'amount' => abs($delta),
                'balance_after' => $after,
                'status' => 'committed',
            ]);

            $this->recordLedger(
                $locked,
                $operation === 'bet' ? 'Withdraw' : 'Deposit',
                $delta,
                $before,
                $after,
                'OroPlay ' . $operation . ' (' . $transactionCode . ')'
            );

            if (filter_var($payload['isFinished'] ?? false, FILTER_VALIDATE_BOOLEAN) && $roundId !== '') {
                $this->markRoundFinished($roundId);
            }

            return $this->ok(round($after, 2));
        });
    }

    private function resolveUser(array $payload): ?User
    {
        $userCode = (string) ($payload['userCode'] ?? '');
        if ($userCode === '') {
            return null;
        }

        $userId = CasinoProviderPlayer::resolveUserId(self::PROVIDER_KEY, $userCode);
        if ($userId) {
            return User::find($userId);
        }

        if (ctype_digit($userCode)) {
            return User::find((int) $userCode);
        }

        return null;
    }

    private function roundIsFinished(string $roundId): bool
    {
        return (bool) Cache::get($this->roundKey($roundId), false);
    }

    private function markRoundFinished(string $roundId): void
    {
        Cache::put($this->roundKey($roundId), true, self::FINISHED_ROUND_TTL);
    }

    private function roundKey(string $roundId): string
    {
        return 'oroplay.round.finished.' . $roundId;
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

    /** @return array{success: bool, message: float|string, errorCode: int} */
    private function ok(float $balance, int $errorCode = self::NO_ERROR): array
    {
        return ['success' => true, 'message' => $balance, 'errorCode' => $errorCode];
    }

    /** @return array{success: bool, message: float|string, errorCode: int} */
    private function error(int $errorCode, string $description): array
    {
        return ['success' => false, 'message' => $description, 'errorCode' => $errorCode];
    }
}
