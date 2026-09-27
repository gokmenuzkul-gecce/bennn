<?php
namespace VanguardLTE;
use Illuminate\Database\Eloquent\Model;
class StockAsset extends Model { protected $fillable = ['provider_id','symbol','name','exchange','price_usd','change_percent','provider_updated_at','is_enabled']; protected $casts = ['price_usd'=>'float','change_percent'=>'float','provider_updated_at'=>'datetime','is_enabled'=>'boolean']; public function rounds() { return $this->hasMany(StockRound::class); } }
