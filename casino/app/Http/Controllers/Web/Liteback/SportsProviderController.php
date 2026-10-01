<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\Sports\Providers\SportsProviderRegistry;

/**
 * Adapter-driven sportsbook provider management for Liteback.
 *
 * The page lists every registered provider from the registry, shows whether it
 * is ready, lets the operator pick the active one and run a connectivity test.
 */
class SportsProviderController extends Controller
{
    protected SportsProviderRegistry $providers;

    public function __construct(SportsProviderRegistry $providers)
    {
        $this->providers = $providers;
    }

    public function index()
    {
        $catalog = $this->providers->catalog();
        $lastSync = function_exists('settings') ? settings('sports_last_sync') : null;
        $lastSyncCount = function_exists('settings') ? settings('sports_last_sync_count') : null;

        return view('liteback.sports.providers', compact('catalog', 'lastSync', 'lastSyncCount'));
    }

    public function select(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|string|max:40',
        ]);

        if (!$this->providers->has($data['provider'])) {
            return redirect()->back()->withErrors('Unknown sportsbook provider selected.');
        }

        settings()->set('sportsbook_api_provider', $data['provider']);
        settings()->save();

        return redirect()->back()->with('success', 'Active sportsbook provider updated.');
    }

    public function test(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|string|max:40',
        ]);

        if (!$this->providers->has($data['provider'])) {
            return response()->json(['success' => false, 'message' => 'Unknown sportsbook provider.'], 422);
        }

        $result = $this->providers->make($data['provider'])->testConnectivity();

        return response()->json($result);
    }
}
