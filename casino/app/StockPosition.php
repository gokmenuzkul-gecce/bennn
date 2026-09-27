<?php
namespace VanguardLTE;
use Illuminate\Database\Eloquent\Model;
class StockPosition extends Model { protected $fillable = ['user_id','stock_asset_id','stock_round_id','requested_interval','direction','leverage','stake','entry_price_usd','exit_price_usd','return_percent','payout_amount','status','settled_at']; protected $casts = ['leverage'=>'float','stake'=>'float','entry_price_usd'=>'float','exit_price_usd'=>'float','return_percent'=>'float','payout_amount'=>'float','settled_at'=>'datetime']; public function asset() { return $this->belongsTo(StockAsset::class, 'stock_asset_id'); } public function round() { return $this->belongsTo(StockRound::class, 'stock_round_id'); } public function user() { return $this->belongsTo(User::class); } }
