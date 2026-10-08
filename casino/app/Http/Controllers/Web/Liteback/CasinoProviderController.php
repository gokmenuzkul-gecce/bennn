<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Aggregator01\Aggregator01FreespinsService;
use VanguardLTE\Casino\Models\CasinoFreespin;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\User;

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
            // OroPlay (aggregator) credentials.
            'base_url' => 'nullable|string|max:190',
            'client_id' => 'nullable|string|max:190',
            'client_secret' => 'nullable|string|max:190',
            // Gregmorn Hub (aggregator) credentials.
            'office_base_url' => 'nullable|string|max:190',
            'client_base_url' => 'nullable|string|max:190',
            'login' => 'nullable|string|max:190',
            'password' => 'nullable|string|max:190',
            'user_id' => 'nullable|string|max:190',
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
        foreach ([
            'endpoint', 'agent_id', 'api_token', 'secret_key',
            'base_url', 'client_id', 'client_secret',
            'office_base_url', 'client_base_url', 'login', 'password', 'user_id',
        ] as $field) {
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

    /** Free spins console: list campaigns and issue/cancel new ones. */
    public function freespins(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $campaigns = CasinoFreespin::query()
            ->when($search !== '', function ($query) use ($search) {
                $userId = User::where('username', $search)->value('id');
                $query->where(function ($q) use ($search, $userId) {
                    $q->where('issue_id', 'like', "%{$search}%")
                        ->orWhere('game_id', 'like', "%{$search}%");
                    if ($userId) {
                        $q->orWhere('user_id', $userId);
                    }
                });
            })
            ->latest('id')
            ->paginate(25);

        $usernames = User::whereIn('id', $campaigns->pluck('user_id')->unique()->filter())
            ->pluck('username', 'id');

        $games = DB::table('games')
            ->where('provider_key', Aggregator01FreespinsService::PROVIDER_KEY)
            ->orderBy('name')
            ->get(['name', 'provider_game_id', 'game_type']);

        return view('liteback.casino.freespins', compact('campaigns', 'usernames', 'games', 'search'));
    }

    /** Issue a free spins campaign to a user. */
    public function issueFreespins(Request $request, Aggregator01FreespinsService $service)
    {
        $data = $request->validate([
            'username' => 'required|string|max:191',
            'game_id' => 'required|string|max:64',
            'quantity' => 'required|integer|min:1|max:1000',
            'bet_amount' => 'required|string|max:32',
            'valid_until' => 'required|date',
        ]);

        $user = User::where('username', $data['username'])->first();
        if (!$user) {
            return redirect()->back()->with('error', 'Kullanici bulunamadi.');
        }

        $result = $service->issue($user, $data);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /** Cancel an issued free spins campaign. */
    public function cancelFreespins($id, Aggregator01FreespinsService $service)
    {
        $campaign = CasinoFreespin::findOrFail($id);
        $result = $service->cancel($campaign);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
