<?php

namespace VanguardLTE\Http\Controllers\Web\Frontend;

use VanguardLTE\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use VanguardLTE\User;
use VanguardLTE\LottoGame;
use VanguardLTE\LottoTicket;
use VanguardLTE\LottoDraw;
use VanguardLTE\Services\LottoDrawService;
use Illuminate\Support\Facades\DB;

class SocialGamingController extends Controller
{
    /**
     * Display Dynamic Multi-Draw Lotto Hub
     */
    public function lotto(Request $request)
    {
        $user = Auth::user();
        
        $activeGames = LottoGame::where('is_active', true)->get();
        $selectedGameSlug = $request->input('game');
        
        $currentGame = $selectedGameSlug ? $activeGames->firstWhere('slug', $selectedGameSlug) : null;
        $currentGame = $currentGame ?: $activeGames->first();
        
        $recentDraws = collect();
        $userTickets = collect();

        if ($currentGame) {
            $recentDraws = LottoDraw::where('lotto_game_id', $currentGame->id)
                ->where('status', 'drawn')
                ->orderByDesc('scheduled_for')
                ->take(5)
                ->get();

            if ($user) {
                $userTickets = LottoTicket::with('draw')->where('lotto_game_id', $currentGame->id)
                    ->where('user_id', $user->id)
                    ->orderBy('id', 'desc')
                    ->take(10)
                    ->get();
            }
        }

        $nextRound = $currentGame ? app(LottoDrawService::class)->nextRound($currentGame) : null;
        $resultColumns = $currentGame ? ($currentGame->result_columns_json ?: LottoDrawService::DEFAULT_RESULT_COLUMNS) : [];
        $ticketsByDraw = $userTickets->groupBy('lotto_draw_id');

        return view('frontend.Minimal.lotto.index', compact(
            'user', 
            'activeGames', 
            'currentGame', 
            'recentDraws', 
            'userTickets', 
            'nextRound',
            'resultColumns',
            'ticketsByDraw'
        ));
    }

    /**
     * Submit Multi-Draw Lotto Ticket Entry
     */
    public function lottoPlay(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Please log in to purchase lotto tickets!']);
        }

        $gameId = $request->input('lotto_game_id') ?: $request->input('game_id');
        $game = LottoGame::find($gameId);

        if (!$game || !$game->is_active) {
            return response()->json(['success' => false, 'message' => 'Selected Lotto Game is not active!']);
        }

        $numbers = $request->input('numbers', []);
        if (!is_array($numbers) || count($numbers) !== $game->pick_count) {
            return response()->json([
                'success' => false, 
                'message' => "Please select exactly {$game->pick_count} numbers for {$game->title}!"
            ]);
        }

        $numbers = array_map('intval', $numbers);
        if (count(array_unique($numbers)) !== count($numbers)) {
            return response()->json(['success' => false, 'message' => 'Each ticket number must be unique.']);
        }

        // Validate range
        foreach ($numbers as $num) {
            $val = (int)$num;
            if ($val < 1 || $val > $game->max_number) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid number {$num}. Numbers must be between 1 and {$game->max_number}!"
                ]);
            }
        }

        // Check entry fee balance
        if ($user->balance < $game->entry_fee) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient Cedar Coins! Required entry fee is " . number_format($game->entry_fee, 0) . " Coins."
            ]);
        }

        sort($numbers);
        try {
            $ticket = DB::transaction(function () use ($user, $game, $numbers) {
                $freshUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($freshUser->balance < $game->entry_fee) throw new \RuntimeException('Insufficient Cedar Coins.');
                $round = app(LottoDrawService::class)->nextRound($game);
                $freshUser->decrement('balance', $game->entry_fee);
                $ticket = LottoTicket::create([
                    'lotto_game_id' => $game->id,
                    'lotto_draw_id' => $round->id,
                    'user_id' => $freshUser->id,
                    'numbers_json' => $numbers,
                    'draw_date' => $round->scheduled_for->toDateString(),
                    'status' => 'pending',
                ]);
                \VanguardLTE\Services\AffiliateService::recordWagerCommission($freshUser, $game->entry_fee, 'lotto');
                \VanguardLTE\Services\VipService::recordWagerXpAndRakeback($freshUser, $game->entry_fee, 5.0);
                return $ticket->load('draw');
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => "Ticket registered for Round {$ticket->draw->round_code}. Good luck!",
            'ticket_id' => $ticket->id,
            'round_id' => $ticket->draw->round_code,
            'numbers' => $numbers,
            'new_balance' => number_format($user->balance, 0)
        ]);
    }

    /**
     * Display Political & Event Predictions Page
     */
    public function predictions()
    {
        $user = Auth::user();
        
        $predictionMarkets = [
            [
                'id' => 101,
                'category' => 'GEOPOLITICS',
                'title' => 'Will AI Humanoid Robots enter retail homes before 2027?',
                'description' => 'Market resolves YES if at least one commercial humanoid robot achieves 10,000+ consumer home sales before Dec 31, 2026.',
                'yes_percent' => 42,
                'yes_odds' => 2.38,
                'no_odds' => 1.72,
                'total_bets' => '250,000 CEDARS'
            ],
            [
                'id' => 102,
                'category' => 'TECH & SPACE',
                'title' => 'Will Starship achieve uncrewed Mars landing before 2027?',
                'description' => 'Resolves YES if SpaceX lands a Starship vessel payload on Mars surface before Jan 1, 2027.',
                'yes_percent' => 64,
                'yes_odds' => 1.56,
                'no_odds' => 2.75,
                'total_bets' => '500,000 CEDARS'
            ]
        ];

        return view('frontend.Minimal.predictions.index', compact('user', 'predictionMarkets'));
    }

    /**
     * Cast Free Prediction Market Vote
     */
    public function predictionsVote(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Please log in to vote!']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Prediction Vote Registered Successfully!',
            'new_balance' => number_format($user->balance, 0)
        ]);
    }

    /**
     * Refill Free Cedar Coins for User or Guest
     */
    public function refillCoins(Request $request)
    {
        $amount = (float) (function_exists('settings') ? settings('default_refill_amount', 50000) : 50000);
        $user = Auth::user();
        if ($user) {
            $user->increment('balance', $amount);
            return response()->json([
                'success' => true,
                'message' => number_format($amount, 0) . ' Cedar Coins added to your account! 🎉',
                'balance' => number_format($user->balance, 0)
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Guest account refilled with ' . number_format($amount, 0) . ' Cedar Coins! 🎉',
            'balance' => number_format($amount, 0)
        ]);
    }
}
