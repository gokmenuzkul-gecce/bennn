<?php

namespace VanguardLTE\Casino\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A free spins campaign issued to the 01.tech Aggregator.
 *
 * Statuses: issued (waiting/being played), finished (win credited),
 * cancelled (withdrawn before play completed).
 */
class CasinoFreespin extends Model
{
    protected $table = 'casino_freespins';

    protected $fillable = [
        'provider_key',
        'user_id',
        'issue_id',
        'game_id',
        'game_provider',
        'quantity',
        'bet_amount',
        'valid_until',
        'status',
        'win_amount',
        'finished_at',
    ];

    protected $casts = [
        'bet_amount' => 'decimal:2',
        'win_amount' => 'decimal:2',
        'valid_until' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function findFor(string $providerKey, string $issueId): ?self
    {
        return static::query()
            ->where('provider_key', $providerKey)
            ->where('issue_id', $issueId)
            ->first();
    }
}
