<?php

namespace VanguardLTE\Http\Controllers\Web\Frontend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Http\Controllers\Controller;

/**
 * Public community surfaces linked from the header: recent winners and
 * player reviews. Both read live data only - nothing is seeded or faked,
 * so an empty database renders an empty state instead of sample rows.
 */
class CommunityController extends Controller
{
    public function winners(Request $request)
    {
        // Real payouts recorded by the provider wallet bridge.
        $payouts = DB::table('casino_wallet_transactions as t')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->where('t.win_amount', '>', 0)
            ->orderByDesc('t.created_at')
            ->limit(60)
            ->get([
                't.user_id', 't.game_id', 't.win_amount', 't.bet_amount',
                't.amount', 't.created_at', 'u.username',
            ]);

        $rows = $payouts->map(function ($r) {
            $name = $r->username ?: 'Oyuncu #' . $r->user_id;
            return [
                'player' => $this->maskName($name),
                'game' => $r->game_id ?: '-',
                'win' => (float) $r->win_amount,
                'bet' => (float) $r->bet_amount,
                'at' => $r->created_at,
            ];
        });

        // Jackpot draws are also a public win source.
        $draws = collect();
        if (DB::getSchemaBuilder()->hasTable('lotto_draws')) {
            $draws = DB::table('lotto_draws')
                ->where('total_winners', '>', 0)
                ->orderByDesc('id')
                ->limit(30)
                ->get(['round_code', 'total_winners', 'created_at'])
                ->map(fn($d) => [
                    'round' => $d->round_code ?: '#',
                    'winners' => (int) $d->total_winners,
                    'at' => $d->created_at,
                ]);
        }

        $stats = [
            'total_paid' => (float) DB::table('casino_wallet_transactions')->sum('win_amount'),
            'payout_count' => $payouts->count(),
            'biggest' => (float) ($payouts->max('win_amount') ?? 0),
        ];

        return view('frontend.Minimal.community.winners', compact('rows', 'draws', 'stats'));
    }

    public function reviews(Request $request)
    {
        // Player feedback stored by the operator. Live rows only.
        $reviews = collect();
        if (DB::getSchemaBuilder()->hasTable('info')) {
            $reviews = DB::table('info as i')
                ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
                ->orderByDesc('i.created_at')
                ->limit(50)
                ->get(['i.title', 'i.text', 'i.created_at', 'u.username'])
                ->map(fn($r) => [
                    'player' => $this->maskName($r->username ?: 'Oyuncu'),
                    'title' => $r->title,
                    'body' => $r->text,
                    'at' => $r->created_at,
                ]);
        }

        $stats = [
            'count' => $reviews->count(),
            'players' => (int) DB::table('users')->where('role_id', 1)->count(),
        ];

        return view('frontend.Minimal.community.reviews', compact('reviews', 'stats'));
    }

    /** Show only the leading characters so public lists never expose full names. */
    private function maskName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Oyuncu';
        }
        $head = mb_substr($name, 0, 2);
        return $head . str_repeat('*', max(2, min(6, mb_strlen($name) - 2)));
    }
}
