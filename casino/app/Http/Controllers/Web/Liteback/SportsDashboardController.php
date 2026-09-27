<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\Sports\Bet;
use VanguardLTE\Sports\CronLog;
use VanguardLTE\Sports\Services\SportsOddsSyncService;

class SportsDashboardController extends Controller
{
    public function index(SportsOddsSyncService $syncService)
    {
        $stats = [
            'total_bets' => Bet::count(),
            'total_stakes' => (float)Bet::sum('stake_amount'),
            'total_payouts' => (float)Bet::where('status', 1)->sum('return_amount'),
        ];
        $stats['net_ggr'] = $stats['total_stakes'] - $stats['total_payouts'];

        $cronLogs = CronLog::orderBy('id', 'desc')->limit(15)->get();
        try {
            $availableSports = $syncService->availableSports();
            $sportsFeedError = null;
        } catch (\Throwable $e) {
            $availableSports = [];
            $sportsFeedError = $e->getMessage();
        }

        return view('liteback.sports.dashboard', compact('stats', 'cronLogs', 'availableSports', 'sportsFeedError'));
    }
    public function runCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string|in:sports:sync:leagues,sports:sync:games,sports:sync:odds,sports:sync:odds-inplay,sports:games:open,sports:events:cleanup,sports:sync:upcoming,sports:sync:all,sports:sync-odds,sports:settle-matches',
            'sports' => 'nullable|array|max:100',
            'sports.*' => ['string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
        ]);

        $command = $request->input('command');

        try {
            $arguments = [];
            if ($command === 'sports:sync:all' && $request->filled('sports')) {
                $arguments['--sports'] = array_values(array_unique($request->input('sports', [])));
            }
            $exitCode = Artisan::call($command, $arguments);
            $output = trim(Artisan::output());
            if ($exitCode !== 0) {
                return redirect()->back()->withErrors("Command [{$command}] failed. Output: {$output}");
            }
            return redirect()->back()->with('success', "Command [{$command}] ran successfully! Output: " . trim($output));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors("Command failed: " . $e->getMessage());
        }
    }

    public function clearActiveOdds(SportsOddsSyncService $syncService)
    {
        $cleared = $syncService->clearActiveOdds();

        return redirect()->back()->with('success', sprintf(
            'Active site odds cleared: %d Battle Odds fixtures, %d games, %d markets, and %d outcomes hidden. Bets and history were preserved. Run a feed sync to restore current odds.',
            $cleared['battleOdds'],
            $cleared['games'],
            $cleared['markets'],
            $cleared['outcomes']
        ));
    }
}
