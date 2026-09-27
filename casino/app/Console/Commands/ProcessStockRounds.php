<?php
namespace VanguardLTE\Console\Commands;
use Illuminate\Console\Command; use VanguardLTE\Services\StockTradingService;
class ProcessStockRounds extends Command { protected $signature='stocks:process-rounds'; protected $description='Refresh cached Yahoo stock prices and settle due virtual stock rounds'; public function handle(StockTradingService $stocks): int { $result=$stocks->processDueRounds(); $this->info("Stock rounds: {$result['opened']} opened, {$result['settled']} settled, {$result['voided']} voided."); return 0; } }
