<?php

namespace VanguardLTE\Console\Commands;

use Illuminate\Console\Command;
use VanguardLTE\Services\OddsApiService;

class SyncSportsOdds extends Command
{
    protected $signature = 'sports:sync-odds {--force : Force sync regardless of timing}';
    protected $description = 'Import licensed pre-match fixtures and odds into Battle Odds';

    public function handle(OddsApiService $oddsApi)
    {
        $this->info('Starting PRE-MATCH sports odds import...');
        $result = $oddsApi->syncUpcomingFixtures();
        $this->info("Sportsbook Sync Complete! Fixtures updated: {$result['synced_count']} at {$result['synced_at']}");
        return 0;
    }
}
