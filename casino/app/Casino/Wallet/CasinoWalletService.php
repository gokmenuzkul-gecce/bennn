<?php

namespace VanguardLTE\Casino\Wallet;

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Models\CasinoWalletTransaction;
use VanguardLTE\Casino\Providers\Contracts\CasinoProvider;
use VanguardLTE\User;

/**
 * Applies seamless-wallet operations to the site balance.
 *
 * The vendor expects a JSON body with a result code; only HTTP 200 counts as an
 * acknowledgement. Every mutation is written to the site transactions ledger
 * and to casino_wallet_transactions so repeated callbacks stay idempotent.
 *
 * Result codes (vendor-wide): 0 ok, 1 general, 2 bad params, 3 bad sign,
 * 4 bad agent, 5 user not found, 6 insufficient funds, 8 unknown reference,
 * 9 already rolled back, 11 duplicate transaction.
 */
class CasinoWalletService
{
    public const OK = 0;
    public const GENERAL_ERROR = 1;
    public const WRONG_PARAMS = 2;
    public const INVALID_SIGN = 3;
    public const USER_NOT_FOUND = 5;
    public const INSUFFICIENT_FUNDS = 6;
    public const UNKNOWN_REFERENCE = 8;
    public const ALREADY_ROLLED_BACK = 9;
    public const DUPLICATE = 11;

    public function handle(string $operation, CasinoProvider $provider, array $payload): array
    {
        return match ($operation) {
            'GetBalance' => $this->getBalance($provider, $payload),
            'BetWin' => $this->betWin($provider, $payload),
            'Withdraw' => $this->withdraw($provider, $payload),
            'Deposit' => $this->deposit($provider, $payload),
            'RollbackTransaction' => $this->rollback($provider, $payload),
            default => $this->error(self::GENERAL_ERROR, 'Unknown operation: ' . $operation),
        };
    }

    public function getBalance(CasinoProvider $provider, array $payload): array
    {
        $user = $this->resolveUser($provider, $payload);
        if (!$user) {
            return $this->error(self::USER_NOT_FOUND, 'Cannot find specified user id');
        }

        return $this->success((float) $user->balance);
    }

    public function betWin(CasinoProvider $provider, array $payload): array
    {
        $user = $this->resolveUser($provider, $payload);
        if (!$user) {
            return $this->error(self::USER_NOT_FOUND, 'Cannot find specified user id');
        }

        $bet = $this->money($payload, 'betAmount');
        $win = $this->money($payload, 'winAmount');
        $transactionId = (string) $this->value($payload, 'transactionID');

        return $this->commit(
            $provider,
            $user,
            'BetWin',
            $transactionId,
            $payload,
            $bet,
            $win,
            $bet - $win
        );
    }

    public function withdraw(CasinoProvider $provider, array $payload): array
    {
        $user = $this->resolveUser($provider, $payload);
        if (!$user) {
            return $this->error(self::USER_NOT_FOUND, 'Cannot find specified user id');
        }

        $amount = $this->money($payload, 'amount');
        $transactionId = (string) $this->value($payload, 'transactionID');

        return $this->commit(
            $provider,
            $user,
            'Withdraw',
            $transactionId,
            $payload,
            0.0,
            0.0,
            $amount
        );
    }

    public function deposit(CasinoProvider $provider, array $payload): array
    {
        $user = $this->resolveUser($provider, $payload);
        if (!$user) {
            return $this->error(self::USER_NOT_FOUND, 'Cannot find specified user id');
        }

        $amount = $this->money($payload, 'amount');
        $transactionId = (string) $this->value($payload, 'transactionID');

        return $this->commit(
            $provider,
            $user,
            'Deposit',
            $transactionId,
            $payload,
            0.0,
            0.0,
            -1 * $amount
        );
    }

    /**
     * Roll back the effect of a previously committed transaction.
     *
     * A BetWin is reverted by removing the win and returning the stake, a
     * Withdraw by returning the stake, a Deposit by taking the win back.
     */
    public function rollback(CasinoProvider $provider, array $payload): array
    {
        $user = $this->resolveUser($provider, $payload);
        if (!$user) {
            return $this->error(self::USER_NOT_FOUND, 'Cannot find specified user id');
        }

        $reference = (string) $this->value($payload, 'refTransactionID');
        if ($reference === '') {
            return $this->error(self::WRONG_PARAMS, 'Missing refTransactionID');
        }

        return DB::transaction(function () use ($provider, $user, $reference, $payload) {
            $original = CasinoWalletTransaction::findFor($provider->key(), $reference);
            if (!$original) {
                return $this->error(self::UNKNOWN_REFERENCE, 'Could not find reference transaction id.');
            }

            if ($original->status === 'rolled_back') {
                return $this->error(self::ALREADY_ROLLED_BACK, 'Transaction is already rolled back');
            }

            $locked = User::lockForUpdate()->find($user->id);
            $delta = $this->rollbackDelta($original);
            $before = (float) $locked->balance;
            $after = $before + $delta;

            if ($after < 0) {
                $after = 0.0;
            }

            $locked->balance = $after;
            $locked->save();

            $original->status = 'rolled_back';
            $original->save();

            $this->recordLedger(
                $locked,
                'RollbackTransaction',
                $after - $before,
                $before,
                $after,
                'Casino rollback of ' . $reference . ' (' . $provider->label() . ')'
            );

            return $this->success($after);
        });
    }

    /**
     * Sign the payload, check idempotency, then apply the net balance delta.
     */
    private function commit(
        CasinoProvider $provider,
        User $user,
        string $operation,
        string $transactionId,
        array $payload,
        float $betAmount,
        float $winAmount,
        float $delta
    ): array {
        if ($transactionId === '') {
            return $this->error(self::WRONG_PARAMS, 'Missing transactionID');
        }

        return DB::transaction(function () use ($provider, $user, $operation, $transactionId, $payload, $betAmount, $winAmount, $delta) {
            $existing = CasinoWalletTransaction::findFor($provider->key(), $transactionId);
            if ($existing) {
                // Duplicate: report the original balance and let the vendor
                // treat the operation as already processed.
                return [
                    'code' => self::DUPLICATE,
                    'message' => 'Duplicate transaction',
                    'platformTransactionID' => $existing->transaction_id,
                    'balance' => (float) $existing->balance_after,
                ];
            }

            $locked = User::lockForUpdate()->find($user->id);
            $before = (float) $locked->balance;
            $after = $before - $delta;

            if ($delta > 0 && $after < 0) {
                return $this->error(self::INSUFFICIENT_FUNDS, 'Insufficient funds available to complete the transaction');
            }

            $locked->balance = $after;
            $locked->save();

            CasinoWalletTransaction::create([
                'provider_key' => $provider->key(),
                'user_id' => $locked->id,
                'transaction_id' => $transactionId,
                'round_id' => (string) $this->value($payload, 'roundID'),
                'game_id' => (string) $this->value($payload, 'gameID'),
                'operation' => $operation,
                'reference_transaction_id' => (string) $this->value($payload, 'refTransactionID'),
                'bet_amount' => $betAmount,
                'win_amount' => $winAmount,
                'amount' => abs($delta),
                'balance_after' => $after,
                'status' => 'committed',
            ]);

            $this->recordLedger(
                $locked,
                $operation,
                $after - $before,
                $before,
                $after,
                'Casino ' . $operation . ' (' . $provider->label() . ')'
            );

            return [
                'code' => self::OK,
                'message' => '',
                'platformTransactionID' => $transactionId,
                'balance' => $after,
            ];
        });
    }

    /** Balance delta that undoes a committed transaction. */
    private function rollbackDelta(CasinoWalletTransaction $original): float
    {
        return match ($original->operation) {
            'BetWin' => (float) $original->bet_amount - (float) $original->win_amount,
            'Withdraw' => (float) $original->amount,
            'Deposit' => -1 * (float) $original->amount,
            default => 0.0,
        };
    }

    private function resolveUser(CasinoProvider $provider, array $payload): ?User
    {
        $userCode = (string) $this->value($payload, 'userID');
        if ($userCode === '') {
            return null;
        }

        $userId = CasinoProviderPlayer::resolveUserId($provider->key(), $userCode);
        if (!$userId) {
            return null;
        }

        return User::find($userId);
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
        return round((float) $this->value($payload, $field), 2);
    }

    private function value(array $payload, string $field): mixed
    {
        if (array_key_exists($field, $payload)) {
            return $payload[$field];
        }
        $needle = strtolower($field);
        foreach ($payload as $key => $value) {
            if (strtolower((string) $key) === $needle) {
                return $value;
            }
        }

        return null;
    }

    /** @return array{code: int, message: string, balance: float} */
    private function success(float $balance): array
    {
        return ['code' => self::OK, 'message' => '', 'balance' => round($balance, 2)];
    }

    /** @return array{code: int, message: string} */
    private function error(int $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
