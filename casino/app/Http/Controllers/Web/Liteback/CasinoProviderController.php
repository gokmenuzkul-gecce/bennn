<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;
use VanguardLTE\Http\Controllers\Controller;

/**
 * Casino game provider management for Liteback.
 *
 * Operators can review each aggregator brand, edit its credentials, enable or
 * disable it, run a reachability test and inspect the wallet ledger produced by
 * the seamless-wallet callbacks.
 */
class CasinoProviderController extends Controller
{
    protected CasinoProviderRegistry $registry;

    public function __construct(CasinoProviderRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function index()
    {
        $catalog = $this->registry->catalog();

        $stats = [
            'transactions' => DB::table('casino_wallet_transactions')->count(),
            'players' => DB::table('casino_provider_players')->count(),
            'volume' => (float) DB::table('casino_wallet_transactions')->sum('amount'),
        ];

        return view('liteback.casino.providers', compact('catalog', 'stats'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|string|max:40',
            'endpoint' => 'nullable|string|max:190',
            'agent_id' => 'nullable|string|max:120',
            'api_token' => 'nullable|string|max:190',
            'secret_key' => 'nullable|string|max:190',
        ]);

        if (!$this->registry->has($data['provider'])) {
            return redirect()->back()->withErrors(trans('app.casino_provider_unknown'));
        }

        $provider = $this->registry->make($data['provider']);
        $settingsKey = $provider->config()['settings_key'] ?? ('casino_provider_' . $data['provider']);

        // Keep existing values for blank fields so secrets are never wiped by
        // an operator who only edits one of them.
        $stored = settings($settingsKey);
        $current = is_string($stored) ? (json_decode($stored, true) ?: []) : [];
        foreach (['endpoint', 'agent_id', 'api_token', 'secret_key'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $current[$field] = $data[$field];
            }
        }

        settings()->set($settingsKey, json_encode($current));
        settings()->save();

        return redirect()->route('liteback.casino.providers')->with('success', $provider->label() . ' ayarları kaydedildi.');
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|string|max:40',
        ]);

        if (!$this->registry->has($data['provider'])) {
            return redirect()->back()->withErrors(trans('app.casino_provider_unknown'));
        }

        $key = 'casino_provider_enabled_' . $data['provider'];
        $enabled = (string) settings($key, '1') === '1';
        settings()->set($key, $enabled ? '0' : '1');
        settings()->save();

        return redirect()->route('liteback.casino.providers')
            ->with('success', $this->registry->make($data['provider'])->label() . ($enabled ? ' devre dışı bırakıldı.' : ' etkinleştirildi.'));
    }

    public function test(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|string|max:40',
        ]);

        if (!$this->registry->has($data['provider'])) {
            return response()->json(['success' => false, 'message' => trans('app.casino_provider_unknown')], 422);
        }

        return response()->json($this->registry->make($data['provider'])->testConnectivity());
    }

    public function transactions()
    {
        $transactions = DB::table('casino_wallet_transactions')
            ->orderByDesc('id')
            ->paginate(50);

        $providerLabels = [];
        foreach ($this->registry->keys() as $key) {
            $providerLabels[$key] = $this->registry->make($key)->label();
        }

        return view('liteback.casino.transactions', compact('transactions', 'providerLabels'));
    }
}
