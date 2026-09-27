<?php

namespace VanguardLTE\Console\Commands\Sports;

use VanguardLTE\Sports\Services\SportsOddsSyncService;
use Illuminate\Support\Facades\Artisan;

class SyncAll extends BaseSportsCommand
{
    protected $signature = 'sports:sync:all {--sports=* : Import only these provider sport keys}';
    protected $description = 'Run full sequence sportsbook synchronization (leagues, games, odds, open, cleanup)';
    protected $jobAlias = 'sync_all';

    protected $syncService;

    public function __construct(SportsOddsSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    protected function handleCommand()
    {
        if ($this->syncService->getProvider() === 'promex') {
            $this->info('Fetching the licensed aggregate PRE-MATCH feed...');
            $result = $this->syncService->syncAll((array) $this->option('sports'));
            $this->info("Imported {$result['fixtures']} fixtures across {$result['sports']} sports. All included sports and leagues are enabled.");
            return;
        }

        $this->info("Step 1/5: Syncing categories & leagues...");
        $this->syncService->syncSports();

        $this->info("Step 2/5: Syncing games/fixtures for enabled leagues...");
        $this->syncService->syncGames();

        $this->info("Step 3/5: Syncing odds...");
        $this->syncService->syncOdds('active');

        $this->info("Step 4/5: Opening games for betting...");
        Artisan::call('sports:games:open');

        $this->info("Step 5/5: Cleaning up expired/completed events...");
        Artisan::call('sports:events:cleanup');
    }
}
