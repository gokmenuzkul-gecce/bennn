<?php

namespace VanguardLTE;

use Illuminate\Database\Eloquent\Model;

class CryptoRound extends Model
{
    protected $fillable = ['crypto_asset_id', 'interval', 'round_code', 'starts_at', 'ends_at', 'open_price_usd', 'close_price_usd', 'status', 'opened_at', 'settled_at'];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'opened_at' => 'datetime', 'settled_at' => 'datetime',
        'open_price_usd' => 'float', 'close_price_usd' => 'float',
    ];

    public function asset() { return $this->belongsTo(CryptoAsset::class, 'crypto_asset_id'); }
    public function positions() { return $this->hasMany(CryptoPosition::class); }
    public function snapshots() { return $this->hasMany(CryptoPriceSnapshot::class); }
}
