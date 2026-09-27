<?php

namespace VanguardLTE;

use Illuminate\Database\Eloquent\Model;

class CryptoAsset extends Model
{
    protected $fillable = ['provider_id', 'symbol', 'name', 'market_rank', 'price_usd', 'change_24h', 'provider_updated_at', 'is_enabled'];

    protected $casts = [
        'price_usd' => 'float', 'change_24h' => 'float', 'provider_updated_at' => 'datetime', 'is_enabled' => 'boolean',
    ];

    public function rounds() { return $this->hasMany(CryptoRound::class); }
}
