<?php

namespace VanguardLTE;

use Illuminate\Database\Eloquent\Model;

class CryptoPriceSnapshot extends Model
{
    protected $fillable = ['crypto_round_id', 'crypto_asset_id', 'checkpoint', 'price_usd', 'observed_at', 'provider_updated_at', 'payload_hash', 'provider_payload'];
    protected $casts = ['price_usd' => 'float', 'observed_at' => 'datetime', 'provider_updated_at' => 'datetime'];
}
