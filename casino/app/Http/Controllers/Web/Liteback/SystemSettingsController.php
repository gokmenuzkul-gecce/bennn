<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\Services\DeliveryGatewaySettings;
use VanguardLTE\Services\CryptoPriceService;
use VanguardLTE\Services\LicenseService;
use VanguardLTE\Services\PromexInstallationService;

class SystemSettingsController extends Controller
{
    public function index()
    {
        $cedarRounds = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('cedar_rounds')) {
                $cedarRounds = \Illuminate\Support\Facades\DB::table('cedar_rounds')
                    ->orderBy('id', 'desc')
                    ->limit(10)
                    ->get();
            }
        } catch (\Throwable $e) {
            $cedarRounds = [];
        }

        return view('liteback.settings.index', compact('cedarRounds'));
    }

    public function update(Request $request)
    {
        $request->validate([
            // Brand and navigation
            'app_name' => 'required|string|max:60',
            'brand_tagline' => 'nullable|string|max:80',
            'brand_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'cedar_display_name' => 'nullable|string|max:40',
            'coin_display_name' => 'nullable|string|max:40',
            'nav_label_casino' => 'nullable|string|max:40',
            'nav_label_cedar_games' => 'nullable|string|max:40',
            'nav_badge_cedar_games' => 'nullable|string|max:12',
            'nav_label_cedar_slots' => 'nullable|string|max:40',
            'nav_badge_cedar_slots' => 'nullable|string|max:12',
            'nav_label_sportsbook' => 'nullable|string|max:40',
            'nav_label_lotto' => 'nullable|string|max:40',
            'nav_label_predictions' => 'nullable|string|max:40',

            // Store-wide player sign-in methods
            'enable_password_login' => 'required|in:0,1',
            'enable_whatsapp_otp' => 'required|in:0,1',

            // Module toggles
            'enable_casino_slots' => 'required|in:0,1',
            'enable_cedar_originals' => 'required|in:0,1',
            'enable_cedar_remakes' => 'required|in:0,1',
            'enable_sportsbook' => 'required|in:0,1',
            'enable_lotto' => 'required|in:0,1',
            'enable_predictions' => 'required|in:0,1',
            'enable_refill_coins' => 'required|in:0,1',

            // API keys
            'sportsbook_api_provider' => 'required|in:promex,custom',
            'odds_api_key' => 'nullable|string|max:255',
            'odds_api_region' => 'nullable|string|in:eu,us,uk,au',
            'whatsapp_api_token' => 'nullable|string|max:500',
            'whatsapp_delivery_provider' => ['required', \Illuminate\Validation\Rule::in(app()->environment('local', 'testing') ? ['promex', 'custom', 'devmode'] : ['promex', 'custom'])],
            'whatsapp_api_endpoint' => ['nullable', 'required_if:whatsapp_delivery_provider,custom', 'url', 'max:255', 'regex:/^https:\/\//i'],
            'email_delivery_provider' => 'required|in:disabled,brevo,resend,postmark,custom',
            'email_api_token' => 'nullable|string|max:500',
            'email_api_endpoint' => ['nullable', 'required_if:email_delivery_provider,custom', 'url', 'max:255', 'regex:/^https:\/\//i'],
            'email_from_address' => 'nullable|email:rfc,dns|max:255',
            'email_from_name' => 'nullable|string|max:100',
            'polymarket_api_provider' => 'required|in:promex,custom',
            'crypto_prices_provider' => 'required|in:promex,custom',
            'crypto_prices_api_endpoint' => ['nullable', 'required_if:crypto_prices_provider,custom', 'url', 'max:1000', 'regex:/^https:\/\//i'],

            // Economy / Coin Defaults & Cashout Controls
            'default_refill_amount' => 'nullable|numeric|min:100|max:1000000',
            'default_starting_coins' => 'nullable|numeric|min:0|max:1000000',
            'enable_cashout' => 'required|in:0,1',
            'coins_per_dollar' => 'nullable|numeric|min:1|max:100000',
            'min_cashout_coins' => 'nullable|numeric|min:1|max:10000000',
            'cashout_methods' => 'nullable|string|max:1000',

            // CEDAR Originals Settings
            'cedar_max_payout' => 'nullable|numeric|min:1|max:100000000',
            'cedar_crash_house_edge' => 'nullable|numeric|min:5|max:20',
            'cedar_crash_min_bet' => 'nullable|numeric|min:1|max:100000',
            'cedar_crash_max_bet' => 'nullable|numeric|min:10|max:1000000',
            'cedar_crash_max_multiplier' => 'nullable|numeric|min:2|max:10000',
            'cedar_plinko_min_bet' => 'nullable|numeric|min:1|max:100000',
            'cedar_plinko_max_bet' => 'nullable|numeric|min:10|max:1000000',
            'cedar_mines_house_edge' => 'nullable|numeric|min:5|max:20',
            'cedar_mines_min_bet' => 'nullable|numeric|min:1|max:100000',
            'cedar_mines_max_bet' => 'nullable|numeric|min:10|max:1000000',
            'cedar_dice_house_edge' => 'nullable|numeric|min:5|max:20',
            'cedar_dice_min_bet' => 'nullable|numeric|min:1|max:100000',
            'cedar_dice_max_bet' => 'nullable|numeric|min:10|max:1000000',
            'cedar_wheel_min_bet' => 'nullable|numeric|min:1|max:100000',
            'cedar_wheel_max_bet' => 'nullable|numeric|min:10|max:1000000',
            'cedar_remake_brand_name' => 'nullable|string|max:40',
            'cedar_remake_loader_css' => ['nullable', 'string', 'max:255', 'regex:#^/[A-Za-z0-9._/-]+$#'],
            'cedar_remake_theme_css' => ['nullable', 'string', 'max:255', 'regex:#^/[A-Za-z0-9._/-]+$#'],
        ]);

        if ($request->input('enable_password_login') === '0' && $request->input('enable_whatsapp_otp') === '0') {
            return redirect()->back()->withInput()->withErrors([
                'enable_password_login' => 'Keep at least one player sign-in method enabled for the store.',
            ]);
        }

        $keys = [
            'app_name',
            'brand_tagline',
            'cedar_display_name',
            'coin_display_name',
            'nav_label_casino',
            'nav_label_cedar_games',
            'nav_badge_cedar_games',
            'nav_label_cedar_slots',
            'nav_badge_cedar_slots',
            'nav_label_sportsbook',
            'nav_label_lotto',
            'nav_label_predictions',
            'enable_password_login',
            'enable_whatsapp_otp',
            'enable_casino_slots',
            'enable_cedar_originals',
            'enable_cedar_remakes',
            'enable_sportsbook',
            'enable_lotto',
            'enable_predictions',
            'enable_refill_coins',
            'sportsbook_api_provider',
            'odds_api_key',
            'odds_api_region',
            'whatsapp_api_token',
            'whatsapp_delivery_provider',
            'whatsapp_api_endpoint',
            'email_delivery_provider',
            'email_api_endpoint',
            'email_from_address',
            'email_from_name',
            'polymarket_api_provider',
            'crypto_prices_provider',
            'crypto_prices_api_endpoint',
            'default_refill_amount',
            'default_starting_coins',
            'enable_cashout',
            'coins_per_dollar',
            'min_cashout_coins',
            'cashout_methods',
            'cedar_crash_house_edge',
            'cedar_max_payout',
            'cedar_crash_min_bet',
            'cedar_crash_max_bet',
            'cedar_crash_max_multiplier',
            'cedar_plinko_min_bet',
            'cedar_plinko_max_bet',
            'cedar_mines_house_edge',
            'cedar_mines_min_bet',
            'cedar_mines_max_bet',
            'cedar_dice_house_edge',
            'cedar_dice_min_bet',
            'cedar_dice_max_bet',
            'cedar_wheel_min_bet',
            'cedar_wheel_max_bet',
            'cedar_remake_brand_name',
            'cedar_remake_loader_css',
            'cedar_remake_theme_css',
        ];

        foreach (array_diff($keys, ['whatsapp_api_token']) as $k) {
            settings()->set($k, $request->input($k, ''));
        }
        if ($request->hasFile('brand_logo')) {
            $path = $request->file('brand_logo')->store('branding', 'public');
            settings()->set('brand_logo_path', $path);
        }
        DeliveryGatewaySettings::storeSecret('whatsapp', (string) $request->input('whatsapp_api_token', ''));
        DeliveryGatewaySettings::storeSecret('email', (string) $request->input('email_api_token', ''));

        if ($request->input('whatsapp_delivery_provider') === 'custom'
            && trim((string) $request->input('whatsapp_api_token', '')) === ''
            && !DeliveryGatewaySettings::hasSecret('whatsapp')) {
            return redirect()->back()->withInput()->withErrors(['whatsapp_api_token' => 'Add a token for the custom WhatsApp provider.']);
        }
        if (in_array($request->input('email_delivery_provider'), ['brevo', 'resend', 'postmark', 'custom'], true)
            && trim((string) $request->input('email_api_token', '')) === ''
            && !DeliveryGatewaySettings::hasSecret('email')) {
            return redirect()->back()->withInput()->withErrors(['email_api_token' => 'Add an API token for the selected email provider.']);
        }
        if ($request->input('email_delivery_provider') !== 'disabled'
            && trim((string) $request->input('email_from_address', '')) === '') {
            return redirect()->back()->withInput()->withErrors(['email_from_address' => 'Add the verified sender email for the selected provider.']);
        }
        settings()->save();

        return redirect()->back()->with('success', 'System settings and module controls updated successfully.');
    }

    /**
     * Manual Trigger: Flush all framework caches
     */
    public function clearCache()
    {
        try {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            return redirect()->back()->with('success', 'All system caches (Views, Application Cache, Routes) cleared successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors('Failed to clear caches: ' . $e->getMessage());
        }
    }

    /**
     * Manual Trigger: Test the selected sportsbook data provider.
     */
    public function testOddsApi(Request $request)
    {
        $provider = $request->validate([
            'provider' => 'required|in:promex,custom',
            'api_key' => 'nullable|string|max:255',
        ])['provider'];

        if ($provider === 'promex') {
            if (!LicenseService::canUseCentralOdds()) {
                return response()->json([
                    'success' => false,
                    'message' => 'PROMEX Sports API requires an active license that includes sportsbook access.',
                ], 403);
            }

            try {
                $path = '/api/service/sports/odds';
                $hubUrl = rtrim((string) config('licensing.hub_url', LicenseService::DEFAULT_SERVER), '/');
                $resp = Http::timeout(max(2, (int) config('licensing.hub_timeout', 8)))
                    ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
                    ->withHeaders(PromexInstallationService::signedHeaders('GET', $path))
                    ->get($hubUrl . '/sports/odds');

                $payload = $resp->json();
                if ($resp->successful() && is_array($payload)) {
                    $fixtures = is_array($payload['fixtures'] ?? null) ? $payload['fixtures'] : [];
                    return response()->json([
                        'success' => true,
                        'message' => 'PROMEX Sports API is connected through this installation license (' . count($fixtures) . ' PRE-MATCH odds available).',
                    ]);
                }

                $message = is_array($payload) && is_string($payload['message'] ?? null)
                    ? $payload['message']
                    : 'The licensed sports service returned HTTP ' . $resp->status() . '.';
                return response()->json(['success' => false, 'message' => $message], $resp->status());
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'The PROMEX Sports API could not be reached from this installation.',
                ], 503);
            }
        }

        $apiKey = $request->input('api_key') ?: settings('odds_api_key');

        if (empty($apiKey)) {
            return response()->json(['success' => false, 'message' => 'No Odds API key provided.']);
        }

        try {
            $resp = Http::timeout(6)->get("https://api.the-odds-api.com/v4/sports/?apiKey={$apiKey}");

            if ($resp->successful()) {
                $sports = $resp->json();
                $count = is_array($sports) ? count($sports) : 0;
                return response()->json([
                    'success' => true,
                    'message' => "API Key is VALID! Connected successfully to The Odds API ({$count} active sports available).",
                    'data' => array_slice($sports, 0, 5)
                ]);
            }

            $body = $resp->json();
            $msg = $body['message'] ?? 'Status ' . $resp->status();
            return response()->json(['success' => false, 'message' => "API Error from Odds Provider: {$msg}"]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    /** Verify the licensed Hub cache or the operator's custom crypto feed. */
    public function testCryptoPricesApi(Request $request, CryptoPriceService $prices)
    {
        $data = $request->validate([
            'provider' => 'required|in:promex,custom',
            'endpoint' => ['nullable', 'url', 'max:1000', 'regex:/^https:\/\//i'],
        ]);
        $result = $prices->markets($data['provider'], $data['endpoint'] ?? null);
        if (($result['success'] ?? false) !== true) return response()->json($result, 503);
        return response()->json([
            'success' => true,
            'message' => ($data['provider'] === 'promex' ? 'PROMEX Crypto Prices is connected through this installation license (' : 'Custom crypto-price provider is connected (') . count($result['coins'] ?? []) . ' coins available).',
        ]);
    }

    /**
     * Manual Trigger: Test Polymarket Gamma API connectivity
     */
    public function testPolymarketApi(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:promex,custom',
        ]);

        $url = $request->input('provider') === 'custom'
            ? (settings('polymarket_api_url') ?: 'https://gamma-api.polymarket.com/events')
            : 'https://gamma-api.polymarket.com/events';

        try {
            $resp = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'CasinoDuLiban/SocialGaming'])
                ->get($url, [
                    'limit' => 5,
                    'active' => 'true',
                    'closed' => 'false'
                ]);

            if ($resp->successful()) {
                $events = $resp->json();
                $count = is_array($events) ? count($events) : 0;
                $sample = (!empty($events) && isset($events[0]['title'])) ? '"' . $events[0]['title'] . '"' : 'Active Events';
                return response()->json([
                    'success' => true,
                    'message' => "Polymarket Gamma API is ONLINE! Successfully fetched {$count} live markets. (e.g. {$sample})",
                    'data' => array_slice($events, 0, 5)
                ]);
            }

            return response()->json(['success' => false, 'message' => "API returned HTTP status " . $resp->status()]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Manual Trigger: Ingest sports odds immediately
     */
    public function syncOddsNow()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('sports:sync-odds');
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
            return redirect()->back()->with('success', $output ?: 'Sports odds synced successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors('Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Manual Trigger: Run match settlement immediately
     */
    public function runSettlementNow()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('sports:settle-matches');
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
            return redirect()->back()->with('success', $output ?: 'Sports wagers settlement completed!');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors('Settlement failed: ' . $e->getMessage());
        }
    }

    /**
     * Manual Trigger: Execute Lotto draw immediately
     */
    public function drawLottoNow()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('casino:draw-lotto');
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
            return redirect()->back()->with('success', $output ?: 'Lotto draw executed successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors('Lotto draw failed: ' . $e->getMessage());
        }
    }
}
