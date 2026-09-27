<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use VanguardLTE\CryptoAsset;
use VanguardLTE\CryptoPosition;
use VanguardLTE\CryptoRound;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\Services\CryptoTradingService;

class CryptoTradingController extends Controller
{
    public function index()
    {
        $assets = CryptoAsset::orderByRaw('market_rank is null, market_rank')->orderBy('market_rank')->paginate(50);
        $rounds = CryptoRound::with('asset')->latest()->take(20)->get();
        $positions = CryptoPosition::with(['user', 'asset', 'round'])->latest()->take(25)->get();
        return view('liteback.crypto.index', compact('assets', 'rounds', 'positions'));
    }

    public function refresh(CryptoTradingService $crypto)
    {
        $result = $crypto->refreshCatalog();
        if (($result['success'] ?? false) !== true) return back()->withErrors(['crypto' => $result['message'] ?? 'Crypto catalog refresh failed.']);
        return back()->with('success', "Crypto catalog refreshed. {$result['enabled']} of {$result['count']} currencies are enabled; the initial refresh enables the top 20.");
    }

    public function toggle(CryptoAsset $asset)
    {
        $asset->update(['is_enabled' => !$asset->is_enabled]);
        return back()->with('success', "{$asset->symbol} is now " . ($asset->is_enabled ? 'enabled' : 'disabled') . ' for new positions. Existing queued positions remain protected.');
    }
}
