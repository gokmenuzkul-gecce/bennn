<?php

namespace VanguardLTE\Console\Commands;

use Illuminate\Console\Command;
use VanguardLTE\LottoGame;
use VanguardLTE\LottoDraw;
use VanguardLTE\Services\LottoDrawService;

class DrawLotto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'casino:draw-lotto {--round= : Numeric draw ID or public Round ID} {--game= : Specific lotto game slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute automated draw for active multi-draw lotto games';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $round = $this->option('round');
        $query = LottoDraw::query()->where('status', 'scheduled')->where('scheduled_for', '<=', now());
        if ($round) {
            $query = LottoDraw::query()->where(function ($q) use ($round) {
                $q->where('id', $round)->orWhere('round_code', $round);
            });
        } elseif ($this->option('game')) {
            $query->whereHas('game', fn ($q) => $q->where('slug', $this->option('game')));
        }
        $draws = $query->orderBy('scheduled_for')->get();
        if ($draws->isEmpty()) { $this->info('No eligible scheduled lotto rounds found.'); return 0; }
        $service = app(LottoDrawService::class);
        foreach ($draws as $draw) {
            $settled = $service->draw($draw);
            $this->info("Round {$settled->round_code} is {$settled->status}; {$settled->total_winners} winners.");
        }
        return 0;
    }
}
