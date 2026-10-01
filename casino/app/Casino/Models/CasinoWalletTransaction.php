<?php

namespace VanguardLTE\Casino\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ledger of every wallet operation a provider performed on a site balance.
 *
 * transaction_id is unique per provider, which is what makes the callbacks
 * idempotent: a repeated BetWin/Deposit returns the stored balance and code 11.
 */
class CasinoWalletTransaction extends Model
{
    protected $table = 'casino_wallet_transactions';

    protected $fillable = [
        'provider_key',
        'user_id',
        'transaction_id',
        'round_id',
        'game_id',
        'operation',
        'reference_transaction_id',
        'bet_amount',
        'win_amount',
        'amount',
        'balance_after',
        'status',
    ];

    protected $casts = [
        'bet_amount' => 'decimal:2',
        'win_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public static function findFor(string $providerKey, string $transactionId): ?self
    {
        return static::query()
            ->where('provider_key', $providerKey)
            ->where('transaction_id', $transactionId)
            ->first();
    }
}
