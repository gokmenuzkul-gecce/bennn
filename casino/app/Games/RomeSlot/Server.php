<?php

namespace VanguardLTE\Games\RomeSlot;

use Illuminate\Support\Facades\Auth;
use VanguardLTE\User;
use VanguardLTE\StatGame;

#[\AllowDynamicProperties]
class Server
{
    public function get($request, $game)
    {
        $user = Auth::user();
        if (!$user) {
            $user = User::first();
            if ($user) {
                Auth::login($user);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
            }
        }

        $action = $request->input('action', 'init');

        if ($action === 'init') {
            return json_encode([
                'status' => 'success',
                'balance' => number_format($user->balance, 2, '.', ''),
                'currency' => 'CEDARS',
                'game_title' => 'Rome Slot'
            ]);
        }

        // Spin Action
        if ($action === 'spin') {
            $totalBet = max(1, (float)$request->input('bet', 20));

            if ($user->balance < $totalBet) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Insufficient Cedar Coins balance!'
                ]);
            }

            // 1. Atomic Bet Deduction
            $user->decrement('balance', $totalBet);

            $totalWin = (float)$request->input('win', 0);

            // 2. Atomic Win Payout
            if ($totalWin > 0) {
                $user->increment('balance', $totalWin);
            }

            // Audit Log
            try {
                StatGame::create([
                    'user_id' => $user->id,
                    'balance' => $user->balance,
                    'bet' => $totalBet,
                    'win' => $totalWin,
                    'game' => 'RomeSlot',
                    'in_game' => 1,
                    'shop_id' => $user->shop_id ?? 1
                ]);
            } catch (\Exception $e) {}

            return json_encode([
                'status' => 'success',
                'total_bet' => number_format($totalBet, 2, '.', ''),
                'total_win' => number_format($totalWin, 2, '.', ''),
                'new_balance' => number_format($user->balance, 2, '.', '')
            ]);
        }

        return json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
}
