<?php

namespace VanguardLTE\Casino\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps one site user to the vendor-facing user code for a given provider.
 *
 * The vendor round-trips its own userID string in every callback, so we keep an
 * explicit mapping instead of guessing an encoding at call time.
 */
class CasinoProviderPlayer extends Model
{
    protected $table = 'casino_provider_players';

    protected $fillable = [
        'user_id',
        'provider_key',
        'user_code',
    ];

    public static function codeFor(int $userId, string $providerKey, string $prefix = 'u'): string
    {
        $existing = static::query()
            ->where('user_id', $userId)
            ->where('provider_key', $providerKey)
            ->first();

        if ($existing) {
            return $existing->user_code;
        }

        $code = $prefix . $userId . substr(md5($providerKey . $userId . microtime(true)), 0, 8);

        $record = static::create([
            'user_id' => $userId,
            'provider_key' => $providerKey,
            'user_code' => $code,
        ]);

        return $record->user_code;
    }

    public static function resolveUserId(string $providerKey, string $userCode): ?int
    {
        $record = static::query()
            ->where('provider_key', $providerKey)
            ->where('user_code', $userCode)
            ->first();

        return $record ? (int) $record->user_id : null;
    }
}
