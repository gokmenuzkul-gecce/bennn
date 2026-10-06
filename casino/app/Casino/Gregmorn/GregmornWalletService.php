<?php

namespace VanguardLTE\Casino\Gregmorn;

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Models\CasinoWalletTransaction;
use VanguardLTE\User;

/**
 * Applies Gregmorn Hub seamless-wallet callbacks to the site balance.
 *
 * The Hub posts one of three commands and expects an always-200 JSON body:
 *
 *  - getBalance  -> {"cmd":"getBalance","login","sessionid"}
 *  - writeBet    -> {"cmd":"writeBet","bet","win","login","sessionid",
 *                    "transactionId","gameId","round_finished","info"}
 *  - rollback    -> same shape as writeBet, transactionId equals the bet's
 *
 * A writeBet debits the stake and credits the win in one shot (balance delta =
 * win - bet). transactionId is the idempotency key: a repeated successful
 * writeBet returns the current balance without applying again. A rollback
 * reverses the effect of its matching writeBet.
 *
 * Every mutation is written to the site ledger and to casino_wallet_transactions
 * so retries stay idempotent.
 */
class GregmornWalletService
{
    public const PROVIDER_KEY = 'gregmorn';

    public const OK = 0;
    public const INSUFFICIENT_FUNDS = 6;

    /** @var array<string, mixed> */
    private array $lastPayload = [];

    public function handle(string $cmd, array $payload, GregmornClient $client): array
    {
        $this->lastPayload = $payload;

        return match (strtolower($cmd)) {
            'getbalance' => $this->getBalance($payload, $client),
            'writebet' => $this->writeBet($payload, $client),
            'rollback' => $this->rollback($payload, $client),
            default => $this->fail($payload, $client, 'Unknown command: ' . $cmd, 0.0),
        };
    }

    public function getBalance(array $payload, GregmornClient $client): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->fail($payload, $client, 'Player not found', 0.0);
        }

        return $this->success($payload, $client, (float) $user->balance);
    }

    public function writeBet(array $payload, GregmornClient $client): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->fail($payload, $client, 'Player not found', 0.0);
        }

        $transactionId = (string) ($payload['transactionId'] ?? '');
        if ($transactionId === '') {
            return $this->fail($payload, $client, 'Missing transactionId', (float) $user->balance);
        }

        $bet = $this->money($payload, 'bet');
        $win = $this->money($payload, 'win');
        $delta = $win - $bet;

        return DB::transaction(function () use ($payload, $client, $user, $transactionId, $bet, $win, $delta) {
            $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionId);
            if ($existing) {
                // Duplicate: report the current balance, do not apply again.
                return $this->success($payload, $client, (float) $existing->balance_after);
            }

            $locked = User::lockForUpdate()->find($user->id);
            $before = (float) $locked->balance;
            $after = $before + $delta;

            if ($after < 0) {
                return $this->fail($payload, $client, 'Insufficient funds available to complete the transaction', $before);
            }

            $locked->balance = $after;
            $locked->save();

            CasinoWalletTransaction::create([
                'provider_key' => self::PROVIDER_KEY,
                'user_id' => $locked->id,
                'transaction_id' => $transactionId,
                'round_id' => (string) ($payload['roundId'] ?? ''),
                'game_id' => (string) ($payload['gameId'] ?? ''),
                'operation' => 'writeBet',
                'reference_transaction_id' => (string) ($payload['sessionid'] ?? ''),
                'bet_amount' => $bet,
                'win_amount' => $win,
                'amount' => abs($delta),
                'balance_after' => $after,
                'status' => 'committed',
            ]);

            $this->recordLedger(
                $locked,
                'BetWin',
                $after - $before,
                $before,
                $after,
                'Gregmorn writeBet'
            );

            return $this->success($payload, $client, $after);
        });
    }

    /**
     * Reverse the effect of the writeBet that shares this transactionId.
     */
    public function rollback(array $payload, GregmornClient $client): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->fail($payload, $client, 'Player not found', 0.0);
        }

        $transactionId = (string) ($payload['transactionId'] ?? '');
        if ($transactionId === '') {
            return $this->fail($payload, $client, 'Missing transactionId', (float) $user->balance);
        }

        return DB::transaction(function () use ($payload, $client, $user, $transactionId) {
            $original = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $transactionId);
            if (!$original) {
                return $this->fail($payload, $client, 'Could not find reference transaction id.', (float) $user->balance);
            }

            // A repeated rollback for the same transaction is a no-op: report
            // the current balance so the Hub treats it as already processed.
            if ($original->status === 'rolled_back') {
                return $this->success($payload, $client, (float) $user->balance);
            }

            $locked = User::lockForUpdate()->find($user->id);
            $before = (float) $locked->balance;
            // Undo the writeBet delta: give back the stake, take back the win.
            $delta = (float) $original->bet_amount - (float) $original->win_amount;
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
                'Gregmorn rollback of ' . $transactionId
            );

            return $this->success($payload, $client, $after);
        });
    }

    private function resolveUser(array $payload): ?User
    {
        $login = (string) ($payload['login'] ?? '');
        if ($login === '') {
            return null;
        }

        $userId = CasinoProviderPlayer::resolveUserId(self::PROVIDER_KEY, $login);
        if ($userId) {
            return User::find($userId);
        }

        if (ctype_digit($login)) {
            return User::find((int) $login);
        }

        return null;
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

    /** @return array<string, mixed> */
    private function success(array $payload, GregmornClient $client, float $balance): array
    {
        return [
            'balance' => round($balance, 2),
            'currency' => $client->currency(),
            'duration' => 0,
            'error' => '',
            'login' => (string) ($payload['login'] ?? ''),
            'status' => 'success',
        ];
    }

    /** @return array<string, mixed> */
    private function fail(array $payload, GregmornClient $client, string $error, float $balance): array
    {
        return [
            'balance' => round($balance, 2),
            'currency' => $client->currency(),
            'duration' => 0,
            'error' => $error,
            'login' => (string) ($payload['login'] ?? ''),
            'status' => 'fail',
        ];
    }
}
