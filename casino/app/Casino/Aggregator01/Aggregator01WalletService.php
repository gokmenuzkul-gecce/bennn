<?php

namespace VanguardLTE\Casino\Aggregator01;

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Models\CasinoWalletTransaction;
use VanguardLTE\User;

/**
 * Applies 01.tech Aggregator (A8R) seamless-wallet callbacks to the site balance.
 *
 * The Aggregator POSTs JSON to our callback URL for one of:
 *   - a8r_casino.Player/Balance   read the balance (no mutation)
 *   - a8r_casino.Round/BetWin     a list of bet/win transactions
 *   - a8r_casino.Round/Rollback   reverse previously applied transactions
 *   - a8r_casino.Round/Finish     close the round (no balance change)
 *
 * Amounts are decimal **strings** (up to 18 integer / 12 fractional digits),
 * not cents, so every figure is processed with BCMath and only rounded to the
 * ledger's 2-decimal column at the boundary.
 *
 * Idempotency is per transaction id: a repeated BetWin/Rollback returns the
 * stored balance and the original per-transaction response without moving money
 * again. Rollbacks leave a tombstone so a delayed original request cannot undo
 * the cancellation.
 *
 * Responses follow the Twirp envelope: a success body for the operation, or a
 * {code, msg, meta:{api_code, api_message, balance}} error body carried with
 * HTTP 400 (invalid_argument) / 500 (internal).
 */
class Aggregator01WalletService
{
    public const PROVIDER_KEY = 'aggregator01';

    /** Error api_codes from the 01.tech error table. */
    public const API_INSUFFICIENT_FUNDS = '100';
    public const API_PLAYER_NOT_FOUND = '101';
    public const API_PLAYER_DISABLED = '110';
    public const API_BAD_REQUEST = '400';
    public const API_FORBIDDEN = '403';
    public const API_UNKNOWN = '500';

    /**
     * Dispatch an inbound callback by its logical operation.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(string $operation, array $payload, Aggregator01Client $client): array
    {
        return match ($operation) {
            'Balance' => $this->balance($payload),
            'BetWin' => $this->betWin($payload),
            'Rollback' => $this->rollback($payload),
            'Finish' => $this->finish($payload),
            default => $this->error(self::API_BAD_REQUEST, 'Unsupported operation.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function balance(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }

        return ['status' => 200, 'body' => ['balance' => $this->money((string) $user->balance)]];
    }

    /**
     * Apply a list of bet/win transactions in order.
     *
     * All transactions are processed; if any is refused (insufficient funds for
     * a bet) the whole request rolls back and a single error is returned. Wins
     * are applied even if they precede their bet.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function betWin(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }

        $transactions = is_array($payload['transactions'] ?? null) ? $payload['transactions'] : [];
        if ($transactions === []) {
            return $this->error(self::API_BAD_REQUEST, 'transactions: value must contain at least 1 item', $user);
        }

        $roundId = (string) ($payload['round_id'] ?? '');
        $gameId = (string) ($payload['game_id'] ?? '');

        try {
            return DB::transaction(function () use ($user, $transactions, $roundId, $gameId) {
                /** @var User $locked */
                $locked = User::lockForUpdate()->find($user->id);
                $responseTxns = [];

                foreach ($transactions as $txn) {
                    if (!is_array($txn)) {
                        throw new \RuntimeException('bad transaction');
                    }

                    $id = (string) ($txn['id'] ?? '');
                    $type = strtolower((string) ($txn['type'] ?? ''));
                    $amount = $this->normalize((string) ($txn['amount'] ?? '0'));
                    if ($id === '' || !in_array($type, ['bet', 'win'], true)) {
                        throw new \RuntimeException('bad transaction');
                    }

                    // Idempotency: a repeated id returns the stored result.
                    $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $id);
                    if ($existing) {
                        $responseTxns[] = $this->txnResponse($id, $locked->id, $type);
                        $locked->balance = (float) $existing->balance_after;

                        continue;
                    }

                    $delta = $type === 'bet' ? '-' . $amount : $amount;
                    $before = (float) $locked->balance;
                    $after = (float) $this->add((string) $locked->balance, $delta);

                    if ($after < 0) {
                        // Abort the whole request; the transaction wrapper rolls back.
                        throw new InsufficientFundsException($this->money((string) $locked->balance));
                    }

                    $locked->balance = $after;
                    $locked->save();

                    CasinoWalletTransaction::create([
                        'provider_key' => self::PROVIDER_KEY,
                        'user_id' => $locked->id,
                        'transaction_id' => $id,
                        'round_id' => $roundId,
                        'game_id' => $gameId,
                        'operation' => $type,
                        'reference_transaction_id' => (string) ($txn['bet_id'] ?? ''),
                        'bet_amount' => $type === 'bet' ? $amount : 0,
                        'win_amount' => $type === 'win' ? $amount : 0,
                        'amount' => $amount,
                        'balance_after' => $after,
                        'status' => 'committed',
                    ]);

                    $this->recordLedger(
                        $locked,
                        $type === 'bet' ? 'Withdraw' : 'Deposit',
                        $after - $before,
                        $before,
                        $after,
                        '01.tech ' . $type
                    );

                    $responseTxns[] = $this->txnResponse($id, $locked->id, $type);
                }

                return [
                    'status' => 200,
                    'body' => [
                        'balance' => $this->money((string) $locked->balance),
                        'round_id_casino' => $this->roundIdCasino($roundId, $user->id),
                        'transactions' => $responseTxns,
                    ],
                ];
            });
        } catch (InsufficientFundsException $e) {
            return $this->error(self::API_INSUFFICIENT_FUNDS, 'Player has not enough funds to process an action.', $user, $e->balance());
        }
    }

    /**
     * Reverse previously applied transactions.
     *
     * A bet rollback refunds the player, a win rollback deducts. Missing
     * originals are still answered with a valid response (tombstone behaviour),
     * so a delayed original request cannot re-apply the cancelled movement.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function rollback(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }

        $transactions = is_array($payload['transactions'] ?? null) ? $payload['transactions'] : [];
        if ($transactions === []) {
            return $this->error(self::API_BAD_REQUEST, 'transactions: value must contain at least 1 item', $user);
        }

        $roundId = (string) ($payload['round_id'] ?? '');
        $gameId = (string) ($payload['game_id'] ?? '');

        return DB::transaction(function () use ($user, $transactions, $roundId, $gameId) {
            /** @var User $locked */
            $locked = User::lockForUpdate()->find($user->id);
            $responseTxns = [];

            foreach ($transactions as $txn) {
                if (!is_array($txn)) {
                    throw new \RuntimeException('bad rollback transaction');
                }

                $id = (string) ($txn['id'] ?? '');
                $originalId = (string) ($txn['original_id'] ?? '');
                if ($id === '' || $originalId === '') {
                    throw new \RuntimeException('bad rollback transaction');
                }

                // Idempotency: a repeated rollback id returns the stored result.
                $existingRollback = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $id);
                if ($existingRollback) {
                    $locked->balance = (float) $existingRollback->balance_after;
                    $responseTxns[] = $this->txnResponse($id, $locked->id, 'rollback');

                    continue;
                }

                // Find the original movement to reverse (may be absent).
                $original = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $originalId);
                if ($original && $original->operation !== 'rollback') {
                    $delta = $original->operation === 'bet'
                        ? (string) $original->amount          // refund a bet
                        : '-' . (string) $original->amount;   // deduct a win
                    $before = (float) $locked->balance;
                    $after = (float) $this->add((string) $locked->balance, $delta, 2);
                    // The player may be unable to cover a win rollback; clamp at 0.
                    if ($after < 0) {
                        $after = 0.0;
                    }

                    $locked->balance = $after;
                    $locked->save();

                    $this->recordLedger(
                        $locked,
                        $after >= $before ? 'Deposit' : 'Withdraw',
                        $after - $before,
                        $before,
                        $after,
                        '01.tech rollback'
                    );
                }

                CasinoWalletTransaction::create([
                    'provider_key' => self::PROVIDER_KEY,
                    'user_id' => $locked->id,
                    'transaction_id' => $id,
                    'round_id' => $roundId,
                    'game_id' => $gameId,
                    'operation' => 'rollback',
                    'reference_transaction_id' => $originalId,
                    'bet_amount' => 0,
                    'win_amount' => 0,
                    'amount' => 0,
                    'balance_after' => (float) $locked->balance,
                    'status' => 'committed',
                ]);

                $responseTxns[] = $this->txnResponse($id, $locked->id, 'rollback');
            }

            return [
                'status' => 200,
                'body' => [
                    'balance' => $this->money((string) $locked->balance),
                    'round_id_casino' => $this->roundIdCasino($roundId, $user->id),
                    'transactions' => $responseTxns,
                ],
            ];
        });
    }

    /**
     * Close a round. No balance change, but the balance is returned.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function finish(array $payload): array
    {
        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }

        return ['status' => 200, 'body' => ['balance' => $this->money((string) $user->balance)]];
    }

    /**
     * One entry of the response transactions array. bonus_amount is always "0"
     * because this integration has no separate bonus balance.
     *
     * @return array<string, string>
     */
    private function txnResponse(string $id, int $userId, string $type): array
    {
        return [
            'id' => $id,
            'id_casino' => $this->casinoTxnId($id, $userId, $type),
            'bonus_amount' => '0',
            'processed_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** Deterministic casino-side transaction id derived from the aggregator id. */
    private function casinoTxnId(string $id, int $userId, string $type): string
    {
        return substr(hash('sha256', 'a8r|' . $userId . '|' . $type . '|' . $id), 0, 32);
    }

    /** Deterministic casino-side round id (the Aggregator's round_id is stored as-is). */
    private function roundIdCasino(string $roundId, int $userId): string
    {
        if ($roundId === '') {
            $roundId = 'round';
        }

        return substr(hash('sha256', 'a8r-round|' . $userId . '|' . $roundId), 0, 32);
    }

    private function resolveUser(array $payload): ?User
    {
        $playerId = (string) ($payload['player_id'] ?? '');
        if ($playerId === '') {
            return null;
        }

        $userId = CasinoProviderPlayer::resolveUserId(self::PROVIDER_KEY, $playerId);
        if ($userId) {
            return User::find($userId);
        }

        if (ctype_digit($playerId)) {
            return User::find((int) $playerId);
        }

        return null;
    }

    /**
     * Normalise a wire amount to a 12-decimal string (Money Amount Format).
     *
     * Rejects negative or non-numeric values.
     */
    private function normalize(string $amount): string
    {
        $amount = trim($amount);
        if ($amount === '' || !preg_match('/^\d{1,18}(\.\d{1,12})?$/', $amount)) {
            return '0';
        }

        return $amount;
    }

    /** Add two decimal strings; scale 2 (the ledger column's precision). */
    private function add(string $left, string $right, int $scale = 2): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left === '' ? '0' : $left, $right === '' ? '0' : $right, $scale);
        }

        return number_format((float) $left + (float) $right, $scale, '.', '');
    }

    /** Format a stored balance as a Money Amount string (2 decimals). */
    private function money(string $value): string
    {
        if (function_exists('bcadd')) {
            return bcadd($value === '' ? '0' : $value, '0', 2);
        }

        return number_format((float) $value, 2, '.', '');
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

    /**
     * Build a Twirp error body.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $apiCode, string $message, ?User $user = null, ?string $balance = null): array
    {
        $meta = [
            'api_code' => $apiCode,
            'api_message' => $message,
        ];
        if ($balance !== null) {
            $meta['balance'] = $balance;
        } elseif ($user) {
            $meta['balance'] = $this->money((string) $user->balance);
        }

        return [
            'status' => $apiCode === self::API_UNKNOWN ? 500 : 400,
            'body' => [
                'code' => $apiCode === self::API_UNKNOWN ? 'internal' : 'invalid_argument',
                'msg' => $message,
                'meta' => $meta,
            ],
        ];
    }
}
