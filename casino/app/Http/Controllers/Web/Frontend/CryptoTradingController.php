<?php

namespace VanguardLTE\Http\Controllers\Web\Frontend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use VanguardLTE\CryptoAsset;
use VanguardLTE\CryptoPosition;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\Services\CryptoTradingService;

class CryptoTradingController extends Controller
{
    public function index(Request $request, CryptoTradingService $crypto)
    {
        $interval = $request->input('interval', 'hourly');
        if (!in_array($interval, array_merge(CryptoTradingService::INTERVALS, ['manual']), true)) $interval = 'hourly';
        $assets = CryptoAsset::where('is_enabled', true)->orderByRaw('market_rank is null, market_rank')->orderBy('market_rank')->get();
        $positions = Auth::check()
            ? CryptoPosition::with(['asset', 'round'])->where('user_id', Auth::id())->latest()->take(12)->get()
            : collect();
        $nextBatch = $crypto->nextBatchStart($interval);
        return view('frontend.Minimal.crypto.index', compact('assets', 'positions', 'interval', 'nextBatch'));
    }

    public function place(Request $request, CryptoTradingService $crypto)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Please sign in to open a virtual position.'], 401);
        $data = $request->validate([
            'asset_id' => 'required|integer',
            'interval' => 'required|in:hourly,daily,weekly,manual',
            'direction' => 'required|in:long,short',
            'stake' => 'required|numeric|min:1|max:1000000',
            'leverage' => 'required|numeric|in:1,2,3',
        ]);
        try {
            $position = $crypto->queuePosition($user, (int) $data['asset_id'], $data['interval'], $data['direction'], (float) $data['stake'], (float) $data['leverage']);
            return response()->json([
                'success' => true,
                'message' => "Queued {$position->direction} {$position->asset->symbol} for {$position->round->round_code}.",
                'position' => ['id' => $position->id, 'round' => $position->round->round_code, 'starts_at' => $position->round->starts_at->toIso8601String()],
                'balance' => number_format((float) $user->balance, 2),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** CDN-safe local cache: it never triggers the licensed provider request. */
    public function cache()
    {
        $assets = CryptoAsset::where('is_enabled', true)->orderByRaw('market_rank is null, market_rank')->orderBy('market_rank')->get(['id', 'provider_id', 'symbol', 'name', 'market_rank', 'price_usd', 'change_24h', 'provider_updated_at', 'updated_at']);
        $payload = ['generated_at' => now('UTC')->toIso8601String(), 'assets' => $assets];
        $etag = '"' . hash('sha256', json_encode($payload)) . '"';
        if (request()->header('If-None-Match') === $etag) return response('', 304)->header('ETag', $etag);
        return response()->json($payload)->header('Cache-Control', 'public, max-age=30, s-maxage=60, stale-while-revalidate=30')->header('ETag', $etag);
    }
}
