<?php
namespace VanguardLTE;
use Illuminate\Database\Eloquent\Model;
class StockRound extends Model { protected $fillable = ['stock_asset_id','interval','round_code','starts_at','ends_at','open_price_usd','close_price_usd','status','opened_at','settled_at']; protected $casts = ['starts_at'=>'datetime','ends_at'=>'datetime','opened_at'=>'datetime','settled_at'=>'datetime','open_price_usd'=>'float','close_price_usd'=>'float']; public function asset() { return $this->belongsTo(StockAsset::class, 'stock_asset_id'); } public function positions() { return $this->hasMany(StockPosition::class); } public function snapshots() { return $this->hasMany(StockPriceSnapshot::class); } }
