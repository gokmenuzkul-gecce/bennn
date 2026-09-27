<?php
namespace VanguardLTE;
use Illuminate\Database\Eloquent\Model;
class StockPriceSnapshot extends Model { protected $fillable = ['stock_round_id','stock_asset_id','checkpoint','price_usd','observed_at','provider_updated_at','payload_hash','provider_payload']; protected $casts = ['price_usd'=>'float','observed_at'=>'datetime','provider_updated_at'=>'datetime']; }
