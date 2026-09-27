<?php

namespace VanguardLTE\Games;

use Illuminate\Support\Facades\Auth;

#[\AllowDynamicProperties]
class CustomSlotsBaseServer
{
    public function handleRequest($request, $gameObject, $slug, $title, $symbols, $reels)
    {
        $user = Auth::user();
        if (!$user) return json_encode(['status' => 'error', 'message' => 'Sign in to play.']);
        // These five prototypes have unaudited, severely underpaying math and no durable settlement.
        // Keep their artwork available for redesign; never accept a wager on the legacy engine.
        if ($request->input('action', 'spin') !== 'init') {
            return json_encode(['status' => 'error', 'message' => 'This prototype is paused while its game rules and payouts are rebuilt.']);
        }

        $action = $request->input('action', 'spin');
        $lines = 20;
        $betPerLine = max(1, (float)$request->input('bet', 1));
        $totalBet = $betPerLine * $lines;

        if ($action === 'init') {
            return json_encode([
                'status' => 'success',
                'game_title' => $title,
                'slug' => $slug,
                'balance' => number_format($user->balance, 2, '.', ''),
                'currency' => 'CEDARS',
                'lines' => $lines,
                'default_bet' => $betPerLine,
                'symbols' => $symbols
            ]);
        }

    }
}
