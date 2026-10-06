<?php

namespace VanguardLTE\Casino\Waija;

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Models\CasinoWalletTransaction;
use VanguardLTE\User;

/**
 * Applies Waija seamless-wallet callbacks to the site balance.
 *
 * Waija GETs our callback URL with one of three actions and expects an
 * always-200 JSON body:
 *
 *  - balance -> {"error":0,"balance":<cents>}          (no mutation)
 *  - debit   -> subtract amount, {"error":0,"balance":<cents>}
 *  - credit  -> add amount,      {"error":0,"balance":<cents>}
 *
 * Two quirks matter:
 *  - Amounts and balances are integer **cents** on the wire ($2.50 => 250),
 *    while the site ledger stores decimal currency, so every figure is scaled
 *    by 100 at the boundary.
 *  - A rollback is not a separate action: it arrives as debit/credit with
 *    rb=1 and is applied exactly like a normal movement. A debit with
 *    type=bonus_fs must not touch cash at all.
 *
 * call_id is the unique transaction reference; a repeated call_id returns the
 * stored balance without applying the movement again.
 */
class WaijaWalletService
{
    public const PROVIDER_KEY = 'waija';

    public const OK = 0;
    public const INSUFFICIENT_FUNDS = 1;
    public const PROCESSING_ERROR = 2;

    public function handle(string $action, array $payload, WaijaClient $client): array
    {
        return match (strtolower($action)) {
            'balance' => $this->balance($payload, $client),
            'debit' => $this->debit($payload, $client),
            'credit' => $this->credit($payload, $client),
            default => $this->error(self::PROCESSING_ERROR),
        };
    }

    public function balance(array $payload, WaijaClient $client): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::PROCESSING_ERROR);
        }

        return $this->success($user);
    }

    public function debit(array $payload, WaijaClient $client): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::PROCESSING_ERROR);
        }

        // Free-round spins are funded by Waija, so they must not touch cash.
        if (strtolower((string) ($payload['type'] ?? '')) === 'bonus_fs') {
            return $this->recordNoop($user, $payload, 'bonus_fs');
        }

        $amount = $this->amount($payload);
        $transactionId = (string) ($payload['call_id'] ?? '');
        if ($transactionId === '') {
            return $this->error(self::PROCESSING_ERROR, $user);
        }

        return $this->commit($user, 'debit', $transactionId, $payload, -1 * $amount);
    }

    public function credit(array $payload, WaijaClient $client): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::PROCESSING_ERROR);
        }

        $amount = $this->amount($payload);
        $transactionId = (string) ($payload['call_id'] ?? '');
        if ($transactionId === '') {
            return $this->error(self::PROCESSING_ERROR, $user);
        }

        return $this->commit($user, 'credit', $transactionId, $payload, $amount);
    }

    /**
     * Apply one signed balance delta with idempotency and row locking.
     *
     * $delta is the decimal-currency change: negative for a debit, positive for
     * a credit. A debit that would overdraw is refused with error 1 and no
     * movement, as Waija expects.
     */
    private function commit(User $user, string $operation, string $transactionId, array $payload, float $delta): array
    {
        return DB::transaction(function () use ($user, $operation, $transactionId, $payload, $delta) {
            $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionId);
            if ($existing) {
                return $this->success($user, (float) $existing->balance_after);
            }

            $locked = User::lockForUpdate()->find($user->id);
            $before = (float) $locked->balance;
            $after = $before + $delta;

            if ($after < 0) {
                return $this->error(self::INSUFFICIENT_FUNDS, $locked);
            }

            $locked->balance = $after;
            $locked->save();

            CasinoWalletTransaction::create([
                'provider_key' => self::PROVIDER_KEY,
                'user_id' => $locked->id,
                'transaction_id' => $transactionId,
                'round_id' => (string) ($payload['round_id'] ?? ''),
                'game_id' => (string) ($payload['game_id'] ?? ''),
                'operation' => $operation,
                'reference_transaction_id' => (string) ($payload['rb'] ?? '0') === '1' ? 'rollback' : '',
                'bet_amount' => $operation === 'debit' ? abs($delta) : 0,
                'win_amount' => $operation === 'credit' ? abs($delta) : 0,
                'amount' => abs($delta),
                'balance_after' => $after,
                'status' => 'committed',
            ]);

            $this->recordLedger(
                $locked,
                $operation === 'debit' ? 'Withdraw' : 'Deposit',
                $after - $before,
                $before,
                $after,
                'Waija ' . $operation . ((string) ($payload['rb'] ?? '0') === '1' ? ' (rollback)' : '')
            );

            return $this->success($locked, $after);
        });
    }

    /**
     * Record a movement that must not change the balance (free-round debit), so
     * a retry of the same call_id stays idempotent.
     */
    private function recordNoop(User $user, array $payload, string $operation): array
    {
        $transactionId = (string) ($payload['call_id'] ?? '');
        if ($transactionId !== '') {
            $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionId);
            if (!$existing) {
                CasinoWalletTransaction::create([
                    'provider_key' => self::PROVIDER_KEY,
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                    'round_id' => (string) ($payload['round_id'] ?? ''),
                    'game_id' => (string) ($payload['game_id'] ?? ''),
                    'operation' => $operation,
                    'reference_transaction_id' => '',
                    'bet_amount' => 0,
                    'win_amount' => 0,
                    'amount' => 0,
                    'balance_after' => (float) $user->balance,
                    'status' => 'committed',
                ]);
            }
        }

        return $this->success($user);
    }

    private function resolveUser(array $payload): ?User
    {
        $username = (string) ($payload['username'] ?? '');
        if ($username === '') {
            return null;
        }

        $userId = CasinoProviderPlayer::resolveUserId(self::PROVIDER_KEY, $username);
        if ($userId) {
            return User::find($userId);
        }

        if (ctype_digit($username)) {
            return User::find((int) $username);
        }

        return null;
    }

    /** Wire amount is integer cents; the ledger stores decimal currency. */
    private function amount(array $payload): float
    {
        return round(((int) ($payload['amount'] ?? 0)) / 100, 2);
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

    /** Balance on the wire is integer cents. */
    private function cents(float $balance): int
    {
        return (int) round($balance * 100);
    }

    /** @return array{error: int, balance: int} */
    private function success(User $user, ?float $balance = null): array
    {
        return [
            'error' => self::OK,
            'balance' => $this->cents($balance ?? (float) $user->balance),
        ];
    }

    /** @return array{error: int, balance: int} */
    private function error(int $code, ?User $user = null): array
    {
        return [
            'error' => $code,
            'balance' => $user ? $this->cents((float) $user->balance) : 0,
        ];
    }
}
