<?php

namespace VanguardLTE\Console\Commands;

use Illuminate\Console\Command;
use VanguardLTE\Services\CryptoTradingService;

class ProcessCryptoRounds extends Command
{
    protected $signature = 'crypto:process-rounds';
    protected $description = 'Refresh the licensed crypto cache and open, settle, or void due virtual-position rounds';

    public function handle(CryptoTradingService $crypto): int
    {
        $result = $crypto->processDueRounds();
        $this->info("Crypto rounds: {$result['opened']} opened, {$result['settled']} settled, {$result['voided']} voided.");
        return 0;
    }
}
