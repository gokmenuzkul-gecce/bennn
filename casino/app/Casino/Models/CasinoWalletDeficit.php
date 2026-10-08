<?php

namespace VanguardLTE\Casino\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-provider shortfall the player carries after a win rollback that could
 * not be fully covered by their balance.
 *
 * The player sees a balance of 0 while this is positive; incoming credits
 * offset it first, so the visible balance only grows once the deficit is gone.
 */
class CasinoWalletDeficit extends Model
{
    protected $table = 'casino_wallet_deficits';

    protected $fillable = [
        'provider_key',
        'user_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public static function findFor(string $providerKey, int $userId): ?self
    {
        return static::query()
            ->where('provider_key', $providerKey)
            ->where('user_id', $userId)
            ->first();
    }
}
