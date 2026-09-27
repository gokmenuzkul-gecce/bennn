<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use VanguardLTE\Http\Controllers\Controller;
use Illuminate\Http\Request;
use VanguardLTE\LottoGame;
use VanguardLTE\LottoTicket;
use VanguardLTE\LottoDraw;
use VanguardLTE\User;
use VanguardLTE\Services\LottoDrawService;

class LottoController extends Controller
{
    /**
     * Display Lotto Games & Ticket Management
     */
    public function index()
    {
        $games = LottoGame::orderBy('id', 'desc')->get();
        $tickets = LottoTicket::with('user', 'game', 'draw')->orderByDesc('id')->take(30)->get();
        $draws = LottoDraw::with('game')->orderByDesc('scheduled_for')->take(20)->get();

        return view('liteback.lotto.index', compact('games', 'tickets', 'draws'));
    }

    /**
     * Create New Lotto Game Rule
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'max_number' => 'required|integer|min:10|max:99',
            'pick_count' => 'required|integer|min:3|max:10',
            'entry_fee' => 'required|numeric|min:0',
            'jackpot_pool' => 'required|numeric|min:0',
            'draw_interval' => 'required|in:hourly,daily',
            'draw_time' => 'required|string|max:5',
            'draw_source' => 'required|in:local,promex_api',
            'result_columns' => 'nullable|array',
        ]);

        $slug = \Str::slug($request->input('title'));

        $drawTime = trim((string) $request->input('draw_time'));
        if ($request->input('draw_interval') === 'hourly' && (!ctype_digit($drawTime) || (int) $drawTime > 59)) {
            return back()->withInput()->withErrors(['draw_time' => 'Hourly draws use a minute from 00 to 59.']);
        }
        if ($request->input('draw_interval') === 'daily' && !preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $drawTime)) {
            return back()->withInput()->withErrors(['draw_time' => 'Daily draws use UTC 24-hour time, e.g. 21:00.']);
        }
        LottoGame::create([
            'title' => $request->input('title'),
            'slug' => $slug,
            'max_number' => $request->input('max_number'),
            'pick_count' => $request->input('pick_count'),
            'entry_fee' => $request->input('entry_fee'),
            'jackpot_pool' => $request->input('jackpot_pool', 1000000),
            'draw_interval' => $request->input('draw_interval'),
            'draw_time' => $drawTime,
            'draw_source' => $request->input('draw_source'),
            'result_columns_json' => array_values($request->input('result_columns', LottoDrawService::DEFAULT_RESULT_COLUMNS)),
            'is_active' => true,
        ]);

        return redirect()->route('liteback.lotto.index')->with('success', 'Lotto Game created successfully!');
    }

    /**
     * Toggle Active State of Lotto Game
     */
    public function toggle(Request $request, $game)
    {
        if (!$game instanceof LottoGame) {
            $game = LottoGame::find($game);
        }

        if (!$game) {
            return redirect()->route('liteback.lotto.index')->with('error', 'Lotto Game not found!');
        }

        $game->is_active = !$game->is_active;
        $game->save();

        return redirect()->route('liteback.lotto.index')->with('success', "Lotto Game '{$game->title}' status updated!");
    }

    /**
     * Trigger Manual Draw & Winning Numbers Settlement
     */
    public function draw(Request $request, $game)
    {
        if (!$game instanceof LottoGame) {
            $game = LottoGame::find($game);
        }

        if (!$game) {
            return redirect()->route('liteback.lotto.index')->with('error', 'Lotto Game not found!');
        }

        $drawIdentifier = trim((string) $request->input('round_id'));
        if ($drawIdentifier === '') return back()->withErrors(['round_id' => 'Enter the numeric draw ID or public Round ID to settle.']);
        $draw = LottoDraw::where('lotto_game_id', $game->id)->where(fn ($q) => $q->where('id', $drawIdentifier)->orWhere('round_code', $drawIdentifier))->first();
        if (!$draw) return back()->withErrors(['round_id' => 'That round does not belong to this lotto game.']);
        try {
            $settled = app(LottoDrawService::class)->draw($draw);
            $numbers = implode(', ', $settled->winning_numbers_json ?: []);
            $note = $settled->wasChanged() ? 'settled' : 'was already drawn';
            return redirect()->route('liteback.lotto.index')->with('success', "Round {$settled->round_code} {$note}. Winning numbers: [{$numbers}].");
        } catch (\Throwable $e) {
            return back()->withErrors(['round_id' => $e->getMessage()]);
        }
    }

    public function updateResultColumns(Request $request, LottoGame $game)
    {
        $columns = $request->validate(['result_columns' => 'nullable|array'])['result_columns'] ?? [];
        $allowed = LottoDrawService::DEFAULT_RESULT_COLUMNS;
        $game->result_columns_json = array_values(array_intersect($allowed, $columns));
        $game->save();
        return back()->with('success', "Public result columns updated for {$game->title}.");
    }
}
