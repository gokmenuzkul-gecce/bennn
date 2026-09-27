<?php

namespace VanguardLTE\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use VanguardLTE\LottoDraw;
use VanguardLTE\LottoGame;
use VanguardLTE\LottoTicket;
use VanguardLTE\User;

/** A ticket belongs to exactly one scheduled round; drawing that round is idempotent. */
class LottoDrawService
{
    public const DEFAULT_RESULT_COLUMNS = ['round_id', 'scheduled_for', 'numbers', 'my_tickets', 'winners'];

    public function nextRound(LottoGame $game, ?Carbon $from = null): LottoDraw
    {
        $scheduledFor = $this->nextScheduledFor($game, $from ?: now('UTC'));
        $roundCode = $this->roundCode($game, $scheduledFor);

        return LottoDraw::firstOrCreate(
            ['lotto_game_id' => $game->id, 'round_code' => $roundCode],
            [
                'winning_numbers_json' => [],
                'draw_date' => $scheduledFor->toDateString(),
                'scheduled_for' => $scheduledFor,
                'status' => 'scheduled',
                'draw_source' => $game->draw_source ?: 'local',
                'total_tickets' => 0,
                'total_winners' => 0,
                'total_paid' => 0,
            ]
        );
    }

    public function draw(LottoDraw $draw): LottoDraw
    {
        return DB::transaction(function () use ($draw) {
            $draw = LottoDraw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            if ($draw->status === 'drawn') return $draw;

            $game = LottoGame::findOrFail($draw->lotto_game_id);
            if ($game->draw_source !== 'local') {
                throw new \RuntimeException('This round is managed by the licensed Lotto API and cannot be generated locally.');
            }

            $numbers = collect(range(1, $game->max_number))->shuffle()->take($game->pick_count)->sort()->values()->all();
            $tickets = LottoTicket::where('lotto_draw_id', $draw->id)->where('status', 'pending')->lockForUpdate()->get();
            $winners = 0;
            $paid = 0.0;

            foreach ($tickets as $ticket) {
                $picked = (array) $ticket->numbers_json;
                $matches = count(array_intersect($picked, $numbers));
                $payout = $this->payout($game, $matches);
                $ticket->matches_count = $matches;
                $ticket->payout_amount = $payout;
                $ticket->prize_won = $payout;
                $ticket->status = $payout > 0 ? ($matches === $game->pick_count ? 'jackpot_win' : 'won') : 'lost';
                $ticket->save();
                if ($payout > 0) {
                    User::whereKey($ticket->user_id)->increment('balance', $payout);
                    $winners++;
                    $paid += $payout;
                }
            }

            $draw->fill([
                'winning_numbers_json' => $numbers,
                'status' => 'drawn',
                'drawn_at' => now(),
                'total_tickets' => $tickets->count(),
                'total_winners' => $winners,
                'total_paid' => $paid,
                'jackpot_paid' => $paid,
            ])->save();

            return $draw;
        });
    }

    public function nextScheduledFor(LottoGame $game, Carbon $from): Carbon
    {
        $from = $from->copy()->utc();
        $time = trim((string) ($game->draw_time ?: ($game->draw_interval === 'hourly' ? '00' : '00:00')));
        if ($game->draw_interval === 'hourly') {
            $minute = (int) explode(':', $time)[0];
            $candidate = $from->copy()->startOfHour()->setMinute(max(0, min(59, $minute)))->setSecond(0);
            return $candidate->greaterThan($from) ? $candidate : $candidate->addHour();
        }
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);
        $candidate = $from->copy()->startOfDay()->setHour(max(0, min(23, $hour)))->setMinute(max(0, min(59, $minute)))->setSecond(0);
        return $candidate->greaterThan($from) ? $candidate : $candidate->addDay();
    }

    private function roundCode(LottoGame $game, Carbon $scheduledFor): string
    {
        return strtoupper($game->slug) . '-' . $scheduledFor->format('YmdHi') . 'Z';
    }

    private function payout(LottoGame $game, int $matches): float
    {
        if ($matches === $game->pick_count) return (float) $game->jackpot_pool;
        if ($matches === $game->pick_count - 1 && $game->pick_count >= 4) return max((float) $game->jackpot_pool * .1, (float) $game->entry_fee * 500);
        if ($matches === $game->pick_count - 2 && $game->pick_count >= 5) return (float) $game->entry_fee * 50;
        if ($matches === $game->pick_count - 3 && $game->pick_count >= 6) return (float) $game->entry_fee * 5;
        return 0.0;
    }
}
