<?php

namespace VanguardLTE\Casino\Aggregator01;

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Models\CasinoFreespin;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Models\CasinoWalletDeficit;
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
 * The behaviour follows the 01.tech integration testing checklist
 * (https://docs.aggregator.01.tech/v2/casino/integration-testing-checklist):
 *   - every request is signed (verified upstream), amounts validated, and a
 *     rejected request answers HTTP 400/500 with a Twirp error carrying
 *     meta.api_code (100 insufficient funds, 101 player not found,
 *     154 currency not allowed, 400 validation, 409 already exists);
 *   - idempotency is per transaction `id`: a repeat returns the stored response
 *     with HTTP 200 and does not move money; the same `id` reused with a
 *     different type/player answers 409;
 *   - a rollback is always accepted, even for a missing original (tombstone) and
 *     even when a win rollback overdraws the player — that shortfall is carried
 *     in casino_wallet_deficits, Player/Balance keeps returning the visible
 *     (clamped) balance, and future credits offset the deficit first.
 */
class Aggregator01WalletService
{
    public const PROVIDER_KEY = 'aggregator01';

    /** api_codes from the 01.tech error table. */
    public const API_INSUFFICIENT_FUNDS = '100';
    public const API_PLAYER_NOT_FOUND = '101';
    public const API_MAX_BET = '106';
    public const API_UNSUPPORTED_CURRENCY = '154';
    public const API_BAD_REQUEST = '400';
    public const API_FORBIDDEN = '403';
    public const API_ALREADY_EXISTS = '409';
    public const API_UNKNOWN = '500';

    private const MAX_FIELD = 255;

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
            'FreespinsFinish' => $this->freespinsFinish($payload),
            default => $this->error(self::API_BAD_REQUEST, 'Unsupported operation.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function balance(array $payload): array
    {
        if (($error = $this->validateFields($payload, ['player_id', 'currency'])) !== null) {
            return $error;
        }

        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }
        if (($error = $this->checkCurrency($payload, $user)) !== null) {
            return $error;
        }

        return ['status' => 200, 'body' => ['balance' => $this->visibleBalance($user->id, (string) $user->balance)]];
    }

    /**
     * Apply a list of bet/win transactions in order.
     *
     * All transactions are processed atomically: if a bet cannot be covered the
     * whole request rolls back and a single insufficient-funds error is returned.
     * A win is applied even when it arrives before its bet.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function betWin(array $payload): array
    {
        if (($error = $this->validateFields($payload, ['player_id', 'round_id', 'game_id', 'currency', 'provider'])) !== null) {
            return $error;
        }

        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }
        if (($error = $this->checkCurrency($payload, $user)) !== null) {
            return $error;
        }

        [$transactions, $error] = $this->parseTransactions($payload, ['bet', 'win']);
        if ($error !== null) {
            return $error;
        }

        $roundId = (string) $payload['round_id'];
        $gameId = (string) $payload['game_id'];

        try {
            return DB::transaction(function () use ($user, $transactions, $roundId, $gameId) {
                /** @var User $locked */
                $locked = User::lockForUpdate()->find($user->id);
                $responseTxns = [];

                foreach ($transactions as $txn) {
                    $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $txn['id']);
                    if ($existing) {
                        if ($existing->operation !== $txn['type'] || (int) $existing->user_id !== (int) $locked->id) {
                            throw new ConflictException();
                        }
                        // Idempotent replay: no movement, return the stored result.
                        $responseTxns[] = $this->txnResponse($txn['id'], $locked->id, $txn['type']);

                        continue;
                    }

                    // A rolled-back transaction that arrives late must be ignored.
                    if (CasinoWalletTransaction::isRolledBack(self::PROVIDER_KEY, $txn['id'])) {
                        $responseTxns[] = $this->txnResponse($txn['id'], $locked->id, $txn['type']);

                        continue;
                    }

                    $amount = $this->normalize($txn['amount']);
                    $before = $this->visible($locked);

                    if ($txn['type'] === 'bet') {
                        // A bet is refused if the visible balance cannot cover it.
                        $this->applyDebit($locked, $amount, false);
                    } else {
                        $this->applyCredit($locked, $amount);
                    }

                    $this->recordTransaction($locked, $txn['id'], $txn['type'], $amount, $roundId, $gameId, (string) ($txn['bet_id'] ?? ''));
                    $this->recordLedger($locked, $txn['type'] === 'bet' ? 'Withdraw' : 'Deposit', $this->visible($locked) - $before, $before, $this->visible($locked), '01.tech ' . $txn['type']);

                    $responseTxns[] = $this->txnResponse($txn['id'], $locked->id, $txn['type']);
                }

                return $this->roundResponse($locked, $roundId, $responseTxns);
            });
        } catch (ConflictException) {
            return $this->error(self::API_ALREADY_EXISTS, 'Already exists.', $user, 'already_exists');
        } catch (InsufficientFundsException $e) {
            return $this->error(self::API_INSUFFICIENT_FUNDS, 'Player has not enough funds to process an action.', $user, null, $e->balance());
        }
    }

    /**
     * Reverse previously applied transactions.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function rollback(array $payload): array
    {
        if (($error = $this->validateFields($payload, ['player_id', 'round_id', 'game_id', 'currency', 'provider', 'finished'])) !== null) {
            return $error;
        }

        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }
        if (($error = $this->checkCurrency($payload, $user)) !== null) {
            return $error;
        }

        [$transactions, $error] = $this->parseTransactions($payload, ['rollback']);
        if ($error !== null) {
            return $error;
        }

        $roundId = (string) $payload['round_id'];
        $gameId = (string) $payload['game_id'];

        try {
            return DB::transaction(function () use ($user, $transactions, $roundId, $gameId) {
                /** @var User $locked */
                $locked = User::lockForUpdate()->find($user->id);
                $responseTxns = [];

                foreach ($transactions as $txn) {
                    $existing = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $txn['id']);
                    if ($existing) {
                        if ((int) $existing->user_id !== (int) $locked->id) {
                            throw new ConflictException();
                        }
                        $responseTxns[] = $this->txnResponse($txn['id'], $locked->id, 'rollback');

                        continue;
                    }

                    $originalId = $txn['original_id'];
                    $before = $this->visible($locked);
                    $original = CasinoWalletTransaction::findFor(self::PROVIDER_KEY, $originalId);

                    if ($original && $original->operation === 'bet') {
                        // Refund the stake (offsets any outstanding deficit first).
                        $this->applyCredit($locked, $this->normalize((string) $original->amount));
                        $this->recordLedger($locked, 'Deposit', $this->visible($locked) - $before, $before, $this->visible($locked), '01.tech rollback');
                    } elseif ($original && $original->operation === 'win') {
                        // Deduct the win; never refuse, carry any shortfall.
                        $this->applyDebit($locked, $this->normalize((string) $original->amount), true);
                        $this->recordLedger($locked, 'Withdraw', $this->visible($locked) - $before, $before, $this->visible($locked), '01.tech rollback');
                    }
                    // Missing original: accepted, but leaves a tombstone so a
                    // delayed bet/win with the same id can never be applied.

                    $this->recordTransaction($locked, $txn['id'], 'rollback', '0', $roundId, $gameId, $originalId);
                    $responseTxns[] = $this->txnResponse($txn['id'], $locked->id, 'rollback');
                }

                return $this->roundResponse($locked, $roundId, $responseTxns);
            });
        } catch (ConflictException) {
            return $this->error(self::API_ALREADY_EXISTS, 'Already exists.', $user, 'already_exists');
        }
    }

    /**
     * Close a round. No balance change, but the balance is returned.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function finish(array $payload): array
    {
        if (($error = $this->validateFields($payload, ['player_id', 'round_id', 'currency'])) !== null) {
            return $error;
        }

        $user = $this->resolveUser($payload);
        if (!$user) {
            return $this->error(self::API_PLAYER_NOT_FOUND, 'Player is invalid or not found');
        }
        if (($error = $this->checkCurrency($payload, $user)) !== null) {
            return $error;
        }

        return ['status' => 200, 'body' => ['balance' => $this->visibleBalance($user->id, (string) $user->balance)]];
    }

    /**
     * Credit the total win of a finished free spins campaign
     * (a8r_casino.Freespins/Finish).
     *
     * The request carries only `issue_id` and `amount`; the player is resolved
     * from the campaign we recorded when issuing it. Finalisation is idempotent
     * per `issue_id`: a repeat returns the stored balance without crediting
     * again. The credit offsets any outstanding deficit like any other credit.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function freespinsFinish(array $payload): array
    {
        $issueId = $payload['issue_id'] ?? null;
        if (!is_string($issueId) || trim($issueId) === '') {
            return $this->error(self::API_BAD_REQUEST, 'issue_id: value is required');
        }

        $amount = $payload['amount'] ?? null;
        if (!is_string($amount) || !preg_match('/^\d{1,18}(\.\d{1,12})?$/', $amount)) {
            return $this->error(self::API_BAD_REQUEST, 'amount: value must match the Money Amount format');
        }

        $campaign = CasinoFreespin::findFor(self::PROVIDER_KEY, $issueId);
        if (!$campaign) {
            return $this->error(self::API_BAD_REQUEST, 'Invalid freespins issue.');
        }

        return DB::transaction(function () use ($campaign, $issueId, $amount) {
            /** @var User $locked */
            $locked = User::lockForUpdate()->find($campaign->user_id);

            // Idempotent replay: the campaign is already closed.
            if ($campaign->status === 'finished') {
                return ['status' => 200, 'body' => ['balance' => $this->visibleBalance((int) $locked->id, (string) $locked->balance)]];
            }

            $before = $this->visible($locked);
            $this->applyCredit($locked, $this->normalize($amount));
            $this->recordLedger($locked, 'Deposit', $this->visible($locked) - $before, $before, $this->visible($locked), '01.tech freespins');

            $campaign->status = 'finished';
            $campaign->win_amount = (float) $this->scale($amount, 2);
            $campaign->finished_at = now();
            $campaign->save();

            return ['status' => 200, 'body' => ['balance' => $this->visibleBalance((int) $locked->id, (string) $locked->balance)]];
        });
    }

    // ---------------------------------------------------------------------
    // Balance movement
    // ---------------------------------------------------------------------

    /**
     * Credit the player: offset any outstanding deficit first, then the balance.
     */
    private function applyCredit(User $locked, string $amount): void
    {
        if ($this->isZero($amount)) {
            return;
        }

        $deficit = $this->lockDeficit($locked->id);
        $outstanding = $deficit ? $this->normalize((string) $deficit->amount) : '0';

        if ($this->isZero($outstanding)) {
            $locked->balance = (float) $this->add((string) $locked->balance, $amount);
            $locked->save();

            return;
        }

        if ($this->cmp($amount, $outstanding) >= 0) {
            // The credit covers the whole deficit; the remainder reaches the player.
            $remainder = $this->sub($amount, $outstanding);
            $this->setDeficit($deficit, '0');
            if (!$this->isZero($remainder)) {
                $locked->balance = (float) $this->add((string) $locked->balance, $remainder);
                $locked->save();
            }

            return;
        }

        // The credit only shrinks the deficit; the visible balance stays put.
        $this->setDeficit($deficit, $this->sub($outstanding, $amount));
    }

    /**
     * Debit the player.
     *
     * When $allowDeficit is false (a bet) an overdraw throws and aborts the whole
     * request. When true (a win rollback) the shortfall is carried as a deficit
     * and the visible balance is clamped to zero.
     */
    private function applyDebit(User $locked, string $amount, bool $allowDeficit): void
    {
        if ($this->isZero($amount)) {
            return;
        }

        $deficit = $this->lockDeficit($locked->id);
        $outstanding = $deficit ? $this->normalize((string) $deficit->amount) : '0';

        if (!$this->isZero($outstanding)) {
            // While a deficit exists the visible balance is zero: bets deepen it.
            $this->setDeficit($deficit, $this->add($outstanding, $amount));

            return;
        }

        $before = (float) $locked->balance;
        $after = (float) $this->sub((string) $locked->balance, $amount);

        if ($after < 0) {
            if (!$allowDeficit) {
                throw new InsufficientFundsException($this->money((string) $locked->balance));
            }

            // Carry the shortfall and clamp the visible balance.
            $shortfall = $this->sub($amount, (string) $locked->balance);
            $this->setDeficit($deficit, $shortfall);
            if ($before !== 0.0) {
                $locked->balance = 0.0;
                $locked->save();
            }

            return;
        }

        $locked->balance = $after;
        $locked->save();
    }

    /** Lock (or create) the provider's deficit row for a user. */
    private function lockDeficit(int $userId): ?CasinoWalletDeficit
    {
        $row = CasinoWalletDeficit::query()
            ->where('provider_key', self::PROVIDER_KEY)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
        if ($row) {
            return $row;
        }

        CasinoWalletDeficit::create([
            'provider_key' => self::PROVIDER_KEY,
            'user_id' => $userId,
            'amount' => 0,
        ]);

        return CasinoWalletDeficit::query()
            ->where('provider_key', self::PROVIDER_KEY)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    private function setDeficit(?CasinoWalletDeficit $deficit, string $amount): void
    {
        if (!$deficit) {
            return;
        }
        $deficit->amount = (float) $this->normalize($amount);
        $deficit->save();
    }

    /** Visible balance: the stored balance (already clamped when a deficit exists). */
    private function visible(User $user): float
    {
        return (float) $user->balance;
    }

    private function visibleBalance(int $userId, string $stored): string
    {
        $deficit = CasinoWalletDeficit::findFor(self::PROVIDER_KEY, $userId);
        $outstanding = $deficit ? $this->normalize((string) $deficit->amount) : '0';
        $visible = $this->isZero($outstanding) ? $stored : '0';

        // The ledger is a 2-decimal currency; no value is ever rounded away.
        return $this->scale($visible, 2);
    }

    // ---------------------------------------------------------------------
    // Validation helpers
    // ---------------------------------------------------------------------

    /**
     * Ensure each listed field is present, a non-empty string and within the
     * 255-character limit. Returns an error response or null when valid.
     *
     * @param  array<string, mixed>  $payload
     * @param  string[]  $fields
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function validateFields(array $payload, array $fields): ?array
    {
        foreach ($fields as $field) {
            $value = $payload[$field] ?? null;
            if (!is_string($value) || trim($value) === '') {
                return $this->error(self::API_BAD_REQUEST, $field . ': value is required');
            }
            if ($field !== 'currency' && strlen($value) > self::MAX_FIELD) {
                return $this->error(self::API_BAD_REQUEST, $field . ': value length must be at most ' . self::MAX_FIELD . ' symbols');
            }
        }

        return null;
    }

    /**
     * Parse and validate a transactions array for the given accepted types.
     *
     * @param  array<string, mixed>  $payload
     * @param  string[]  $acceptedTypes
     * @return array{0: array<int, array<string, string>>, 1: array{status: int, body: array<string, mixed>}|null}
     */
    private function parseTransactions(array $payload, array $acceptedTypes): array
    {
        $transactions = $payload['transactions'] ?? null;
        if (!is_array($transactions) || $transactions === []) {
            return [[], $this->error(self::API_BAD_REQUEST, 'transactions: value must contain at least 1 item')];
        }

        $seenIds = [];
        $seenOriginal = [];
        $parsed = [];

        foreach ($transactions as $txn) {
            if (!is_array($txn)) {
                return [[], $this->error(self::API_BAD_REQUEST, 'transactions: value must be a list of objects')];
            }

            $id = $txn['id'] ?? null;
            if (!is_string($id) || trim($id) === '' || strlen($id) > self::MAX_FIELD) {
                return [[], $this->error(self::API_BAD_REQUEST, 'transactions.id: value is required')];
            }
            if (isset($seenIds[$id])) {
                return [[], $this->error(self::API_BAD_REQUEST, 'transactions.id: value must be unique')];
            }
            $seenIds[$id] = true;

            if (in_array('rollback', $acceptedTypes, true)) {
                $originalId = $txn['original_id'] ?? null;
                if (!is_string($originalId) || trim($originalId) === '' || strlen($originalId) > self::MAX_FIELD) {
                    return [[], $this->error(self::API_BAD_REQUEST, 'transactions.original_id: value is required')];
                }
                if (isset($seenOriginal[$originalId])) {
                    return [[], $this->error(self::API_BAD_REQUEST, 'transactions.original_id: value must be unique')];
                }
                $seenOriginal[$originalId] = true;

                $parsed[] = ['id' => $id, 'original_id' => $originalId, 'type' => 'rollback', 'amount' => '0'];

                continue;
            }

            $type = strtolower((string) ($txn['type'] ?? ''));
            if (!in_array($type, $acceptedTypes, true)) {
                return [[], $this->error(self::API_BAD_REQUEST, 'transactions.type: value must be one of ' . implode(', ', $acceptedTypes))];
            }

            $amount = $txn['amount'] ?? null;
            if (!is_string($amount) || !preg_match('/^\d{1,18}(\.\d{1,12})?$/', $amount)) {
                return [[], $this->error(self::API_BAD_REQUEST, 'transactions.amount: value must match the Money Amount format')];
            }

            $parsed[] = ['id' => $id, 'type' => $type, 'amount' => $amount, 'bet_id' => (string) ($txn['bet_id'] ?? '')];
        }

        return [$parsed, null];
    }

    /**
     * The player must hold a wallet in the request currency (the site is
     * single-currency; anything else means there is no such wallet).
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function checkCurrency(array $payload, User $user): ?array
    {
        $currency = $this->normalizeCurrency((string) ($payload['currency'] ?? ''));
        if ($currency === '' || !preg_match('/^[A-Z0-9]{2,15}$/', $currency)) {
            return $this->error(self::API_BAD_REQUEST, 'currency: value must match the Currency format');
        }

        $site = $this->normalizeCurrency((string) config('casino_providers.aggregator01.currency', 'TRY'));
        if ($currency !== $site) {
            return $this->error(self::API_UNSUPPORTED_CURRENCY, 'Currency is not allowed for the player.', $user);
        }

        return null;
    }

    private function normalizeCurrency(string $currency): string
    {
        return strtoupper(trim($currency));
    }

    // ---------------------------------------------------------------------
    // Persistence
    // ---------------------------------------------------------------------

    private function recordTransaction(User $locked, string $id, string $type, string $amount, string $roundId, string $gameId, string $originalId): void
    {
        CasinoWalletTransaction::create([
            'provider_key' => self::PROVIDER_KEY,
            'user_id' => $locked->id,
            'transaction_id' => $id,
            'round_id' => $roundId,
            'game_id' => $gameId,
            'operation' => $type,
            'reference_transaction_id' => $originalId,
            'bet_amount' => $type === 'bet' ? $amount : 0,
            'win_amount' => $type === 'win' ? $amount : 0,
            'amount' => $type === 'rollback' ? 0 : $amount,
            'balance_after' => $this->visible($locked),
            'status' => 'committed',
        ]);
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

    /**
     * @param  array<int, array<string, string>>  $responseTxns
     * @return array{status: int, body: array<string, mixed>}
     */
    private function roundResponse(User $locked, string $roundId, array $responseTxns): array
    {
        return [
            'status' => 200,
            'body' => [
                'balance' => $this->visibleBalance($locked->id, (string) $locked->balance),
                'round_id_casino' => $this->roundIdCasino($roundId, $locked->id),
                'transactions' => $responseTxns,
            ],
        ];
    }

    private function casinoTxnId(string $id, int $userId, string $type): string
    {
        return substr(hash('sha256', 'a8r|' . $userId . '|' . $type . '|' . $id), 0, 32);
    }

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

    private function recordLedger(User $user, string $operation, float $delta, float $before, float $after, string $note): void
    {
        if (abs($delta) < 0.0000001) {
            return;
        }

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

    // ---------------------------------------------------------------------
    // Decimal helpers (BCMath when available)
    // ---------------------------------------------------------------------

    private function normalize(string $amount): string
    {
        $amount = trim($amount);
        if ($amount === '') {
            return '0';
        }

        return ltrim($amount, '+');
    }

    private function isZero(string $amount): bool
    {
        return $this->cmp($amount, '0') === 0;
    }

    private function cmp(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($this->normalize($left), $this->normalize($right), 12);
        }

        return ((float) $left <=> (float) $right);
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($this->normalize($left), $this->normalize($right), 12);
        }

        return number_format((float) $left + (float) $right, 12, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        if (function_exists('bcsub')) {
            return bcsub($this->normalize($left), $this->normalize($right), 12);
        }

        return number_format((float) $left - (float) $right, 12, '.', '');
    }

    /** Scale an amount to an exact number of decimals (mirrors the DB precision). */
    private function scale(string $value, int $points): string
    {
        if (function_exists('bcadd')) {
            return bcadd($this->normalize($value), '0', $points);
        }

        return number_format((float) $value, $points, '.', '');
    }

    /** Number of decimals in a decimal string (used to mirror the DB precision). */
    private function decimals(string $value): ?int
    {
        if (!preg_match('/\.(\d+)$/', trim($value), $m)) {
            return null;
        }

        return strlen($m[1]);
    }

    /** Format a stored balance as a Money Amount string. */
    private function money(string $value): string
    {
        return $this->scale($value, 2);
    }

    /**
     * Build a Twirp error body.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $apiCode, string $message, ?User $user = null, ?string $code = null, ?string $balance = null): array
    {
        $meta = [
            'api_code' => $apiCode,
            'api_message' => $message,
        ];
        if ($balance !== null) {
            $meta['balance'] = $balance;
        } elseif ($user) {
            $meta['balance'] = $this->visibleBalance((int) $user->id, (string) $user->balance);
        }

        return [
            'status' => $apiCode === self::API_UNKNOWN ? 500 : 400,
            'body' => [
                'code' => $code ?? ($apiCode === self::API_UNKNOWN ? 'internal' : 'invalid_argument'),
                'msg' => $message,
                'meta' => $meta,
            ],
        ];
    }
}
