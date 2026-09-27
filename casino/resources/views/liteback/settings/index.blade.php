@extends('liteback.layout')

@section('title', 'Liteback - System Controls & API Keys')
@section('page_title', 'System Controls & API Integrations')

@section('content')
    <div class="row">
        <!-- Main Settings Form -->
        <div class="col-lg-8">
            <form method="post" action="{{ route('liteback.settings.update') }}" enctype="multipart/form-data">
                @csrf

                <!-- Brand & Navigation -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-palette mr-2 text-success"></i>Brand & Navigation</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">These labels change the public lobby and navigation only. Cedar game IDs, game URLs, wallet records, and licensing remain unchanged.</p>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Brand name</label>
                                <input type="text" name="app_name" class="form-control" maxlength="60" value="{{ settings('app_name', 'Casino du Liban') }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Tagline</label>
                                <input type="text" name="brand_tagline" class="form-control" maxlength="80" value="{{ settings('brand_tagline', 'Social Gaming') }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Logo</label>
                                <input type="file" name="brand_logo" class="form-control-file" accept="image/png,image/jpeg,image/webp">
                                <small class="form-text text-muted">PNG, JPG, or WebP up to 2 MB. Leave blank to keep the current logo.</small>
                                @if(settings('brand_logo_path'))
                                    <img src="{{ asset('storage/' . settings('brand_logo_path')) }}" alt="Current logo" class="mt-2 rounded border" style="height:48px;max-width:180px;object-fit:contain">
                                @endif
                            </div>
                            <div class="form-group col-md-3">
                                <label class="font-weight-bold">Cedar display name</label>
                                <input type="text" name="cedar_display_name" class="form-control" maxlength="40" value="{{ settings('cedar_display_name', 'CEDAR') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="font-weight-bold">Coin label</label>
                                <input type="text" name="coin_display_name" class="form-control" maxlength="40" value="{{ settings('coin_display_name', 'Cedar Coins') }}">
                            </div>
                        </div>
                        <hr>
                        <div class="form-row">
                            <div class="form-group col-md-4"><label class="small font-weight-bold">Casino menu label</label><input type="text" name="nav_label_casino" class="form-control" maxlength="40" value="{{ settings('nav_label_casino', 'Casino Slots') }}"></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">Games menu label</label><input type="text" name="nav_label_cedar_games" class="form-control" maxlength="40" value="{{ settings('nav_label_cedar_games', 'CEDAR Games') }}"></div>
                            <div class="form-group col-md-2"><label class="small font-weight-bold">Games badge</label><input type="text" name="nav_badge_cedar_games" class="form-control" maxlength="12" value="{{ settings('nav_badge_cedar_games', 'HOT') }}"></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">Slots menu label</label><input type="text" name="nav_label_cedar_slots" class="form-control" maxlength="40" value="{{ settings('nav_label_cedar_slots', 'CEDAR Slots') }}"></div>
                            <div class="form-group col-md-2"><label class="small font-weight-bold">Slots badge</label><input type="text" name="nav_badge_cedar_slots" class="form-control" maxlength="12" value="{{ settings('nav_badge_cedar_slots', '') }}"></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">Sports menu label</label><input type="text" name="nav_label_sportsbook" class="form-control" maxlength="40" value="{{ settings('nav_label_sportsbook', 'Battle Odds') }}"></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">Jackpot menu label</label><input type="text" name="nav_label_lotto" class="form-control" maxlength="40" value="{{ settings('nav_label_lotto', 'Jackpot Zone') }}"></div>
                            <div class="form-group col-md-4"><label class="small font-weight-bold">Predictions menu label</label><input type="text" name="nav_label_predictions" class="form-control" maxlength="40" value="{{ settings('nav_label_predictions', 'Future Vote') }}"></div>
                        </div>
                    </div>
                </div>

                <!-- Store-wide Player Sign-in Methods -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 font-weight-bold">
                            <i class="fas fa-sign-in-alt mr-2 text-success"></i> Store-wide Player Sign-in Methods
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Choose which sign-in methods players can use anywhere in this store. Enable either method or both; at least one must stay enabled.</p>

                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="custom-control custom-switch border p-3 rounded h-100">
                                    <input type="hidden" name="enable_whatsapp_otp" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchWhatsapp" name="enable_whatsapp_otp" value="1" {{ settings('enable_whatsapp_otp', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchWhatsapp">
                                        WhatsApp verification code
                                    </label>
                                    <small class="text-muted d-block mt-1">Lets players sign in password-free with their WhatsApp number and a 6-digit code. Turning this off disables phone-code sign-in and phone-based account creation; it does not change any passwords.</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="custom-control custom-switch border p-3 rounded h-100">
                                    <input type="hidden" name="enable_password_login" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchPasswordLogin" name="enable_password_login" value="1" {{ settings('enable_password_login', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchPasswordLogin">
                                        Username, email, or phone + password
                                    </label>
                                    <small class="text-muted d-block mt-1">Lets players use their existing credentials. Turning off WhatsApp code sign-in leaves this method working when it remains enabled.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Module Killswitches & Feature Toggles -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 font-weight-bold">
                            <i class="fas fa-toggle-on mr-2 text-success"></i> Module Killswitches & Public Visibility
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Instantly enable or disable modules across the frontend lobby, navigation menus, and mobile bottom dock.</p>
                        
                        <div class="row">
                            <!-- Casino Slots -->
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch border p-3 rounded">
                                    <input type="hidden" name="enable_casino_slots" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchSlots" name="enable_casino_slots" value="1" {{ settings('enable_casino_slots', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchSlots">
                                        Casino Slots Grid
                                    </label>
                                    <small class="text-muted d-block mt-1">Show/hide 1,000+ arcade & video slots.</small>
                                </div>
                            </div>

                            <!-- CEDAR Originals -->
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch border p-3 rounded">
                                    <input type="hidden" name="enable_cedar_originals" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchCedar" name="enable_cedar_originals" value="1" {{ settings('enable_cedar_originals', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchCedar">
                                        CEDAR Originals (Crash, Plinko, Mines, Dice, Wheel)
                                    </label>
                                    <small class="text-muted d-block mt-1">Show/hide custom proprietary games.</small>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch border p-3 rounded">
                                    <input type="hidden" name="enable_cedar_remakes" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchCedarRemakes" name="enable_cedar_remakes" value="1" {{ settings('enable_cedar_remakes', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchCedarRemakes">CEDAR Remakes</label>
                                    <small class="text-muted d-block mt-1">Enable games registered from the isolated /CedarGames runtime.</small>
                                </div>
                            </div>

                            <!-- Sportsbook -->
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch border p-3 rounded">
                                    <input type="hidden" name="enable_sportsbook" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchSports" name="enable_sportsbook" value="1" {{ settings('enable_sportsbook', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchSports">
                                        Battle Odds Sportsbook
                                    </label>
                                    <small class="text-muted d-block mt-1">Match cards, 1X2 odds & betslip.</small>
                                </div>
                            </div>

                            <!-- Lotto -->
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch border p-3 rounded">
                                    <input type="hidden" name="enable_lotto" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchLotto" name="enable_lotto" value="1" {{ settings('enable_lotto', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchLotto">
                                        Cedar Lotto Jackpot Zone
                                    </label>
                                    <small class="text-muted d-block mt-1">3D ball selector & multi-draw jackpot.</small>
                                </div>
                            </div>

                            <!-- Predictions -->
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch border p-3 rounded">
                                    <input type="hidden" name="enable_predictions" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchPredictions" name="enable_predictions" value="1" {{ settings('enable_predictions', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchPredictions">
                                        Future Vote Prediction Markets
                                    </label>
                                    <small class="text-muted d-block mt-1">Polymarket-style YES/NO voting cards.</small>
                                </div>
                            </div>

                            <!-- Free Refill Button -->
                            <div class="col-md-12 mb-2">
                                <div class="custom-control custom-switch border p-3 rounded bg-light">
                                    <input type="hidden" name="enable_refill_coins" value="0">
                                    <input type="checkbox" class="custom-control-input" id="switchRefill" name="enable_refill_coins" value="1" {{ settings('enable_refill_coins', '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark" for="switchRefill">
                                        Public Coin Refill Button (+50,000)
                                    </label>
                                    <small class="text-muted d-block mt-1">Allow guest and logged-in players to self-refill free coins from the topbar and bottom dock.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- External API Integrations -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0 font-weight-bold">
                            <i class="fas fa-key mr-2 text-warning"></i> External API Keys & Providers
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-primary py-2 small" role="note">
                            <strong>Launch faster with PROMEX:</strong> managed API access is included at no extra API cost with an active license, so you do not need to buy, configure, or monitor separate provider keys. Prefer your own provider? Select Custom below.
                        </div>
                        <!-- Odds API -->
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="font-weight-bold text-primary"><i class="fas fa-satellite-dish mr-1"></i> Sportsbook Data & Automation</h6>
                            <p class="text-muted small mb-2">Choose the licensed PROMEX feed or connect your own The Odds API account.</p>
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold">Provider</label>
                                <select id="sportsbookApiProvider" name="sportsbook_api_provider" class="form-control">
                                    <option value="promex" {{ settings('sportsbook_api_provider', 'promex') === 'promex' ? 'selected' : '' }}>PROMEX Licensed API — free with active license (recommended)</option>
                                    <option value="custom" {{ settings('sportsbook_api_provider', 'promex') === 'custom' ? 'selected' : '' }}>My own The Odds API key</option>
                                </select>
                                <small id="promexSportsbookHint" class="text-muted">Uses the licensed PROMEX PRE-MATCH odds feed. No upstream API key is stored in the customer app.</small>
                            </div>
                            <div id="customOddsApiFields" class="form-row align-items-center">
                                <div class="form-group col-md-7 mb-2">
                                    <label class="small font-weight-bold">API Key</label>
                                    <input type="password" id="oddsApiKeyInput" name="odds_api_key" class="form-control" placeholder="Enter your Odds API Key" value="{{ settings('odds_api_key', '') }}">
                                </div>
                                <div class="form-group col-md-3 mb-2">
                                    <label class="small font-weight-bold">Default Region</label>
                                    <select name="odds_api_region" class="form-control">
                                        <option value="eu" {{ settings('odds_api_region', 'eu') == 'eu' ? 'selected' : '' }}>Europe (eu)</option>
                                        <option value="us" {{ settings('odds_api_region') == 'us' ? 'selected' : '' }}>United States (us)</option>
                                        <option value="uk" {{ settings('odds_api_region') == 'uk' ? 'selected' : '' }}>United Kingdom (uk)</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2 mb-2 d-flex align-items-end">
                                    <button type="button" id="btnTestOddsApi" class="btn btn-outline-info btn-block" title="Verify provider connectivity">
                                        <i class="fas fa-plug mr-1"></i> Test
                                    </button>
                                </div>
                            </div>
                            <button type="button" id="btnTestPromexSportsApi" class="btn btn-outline-info btn-sm" title="Verify licensed provider connectivity">
                                <i class="fas fa-plug mr-1"></i> Test PROMEX API
                            </button>
                            <div id="oddsApiStatus" class="small mt-1 d-none"></div>
                        </div>

                        <!-- Crypto Prices -->
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="font-weight-bold text-warning"><i class="fas fa-coins mr-1"></i> Crypto Market Prices</h6>
                            <p class="text-muted small mb-2">PROMEX uses the licensed hourly Hub cache. Custom accepts an operator-owned HTTPS endpoint returning CoinGecko-compatible market records.</p>
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold">Provider</label>
                                <select id="cryptoPricesProvider" name="crypto_prices_provider" class="form-control">
                                    <option value="promex" {{ settings('crypto_prices_provider', 'promex') === 'promex' ? 'selected' : '' }}>PROMEX Licensed API — active license required</option>
                                    <option value="custom" {{ settings('crypto_prices_provider', 'promex') === 'custom' ? 'selected' : '' }}>My Custom Crypto API</option>
                                </select>
                                <small id="promexCryptoHint" class="text-muted">Returns a normalized ranked market cache and includes required data attribution.</small>
                            </div>
                            <div id="customCryptoApiFields" class="form-row align-items-end">
                                <div class="form-group col-md-9 mb-2">
                                    <label class="small font-weight-bold">Custom HTTPS API URL</label>
                                    <input type="url" id="cryptoPricesEndpoint" name="crypto_prices_api_endpoint" class="form-control" placeholder="https://provider.example/coins/markets?..." value="{{ settings('crypto_prices_api_endpoint', '') }}">
                                </div>
                                <div class="form-group col-md-3 mb-2"><button type="button" id="btnTestCryptoApi" class="btn btn-outline-info btn-block"><i class="fas fa-plug mr-1"></i> Test</button></div>
                            </div>
                            <button type="button" id="btnTestPromexCryptoApi" class="btn btn-outline-info btn-sm"><i class="fas fa-plug mr-1"></i> Test PROMEX API</button>
                            <div id="cryptoApiStatus" class="small mt-1 d-none"></div>
                        </div>

                        <!-- WhatsApp Gateway -->
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="font-weight-bold text-success"><i class="fab fa-whatsapp mr-1"></i> WhatsApp Delivery</h6>
                            <p class="text-warning">Development simulation exposes test codes on the login screen and sends no messages. Use only on private local/test installations; production blocks it. This selection replaces the old WHATSAPP_MODE environment override.</p>
                            <p class="text-muted small">PROMEX delivery is included with an active license. Choose Custom to send through your own HTTPS gateway.</p>
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold">Provider</label>
                                <select id="whatsappDeliveryProvider" name="whatsapp_delivery_provider" class="form-control">
                                    <option value="promex" {{ settings('whatsapp_delivery_provider', 'promex') === 'promex' ? 'selected' : '' }}>PROMEX Licensed API — free with active license (recommended)</option>
                                    <option value="custom" {{ settings('whatsapp_delivery_provider', 'promex') === 'custom' ? 'selected' : '' }}>My Custom API</option>
                                    @if(app()->environment('local', 'testing') || settings('whatsapp_delivery_provider') === 'devmode')
                                    <option value="devmode" {{ settings('whatsapp_delivery_provider') === 'devmode' ? 'selected' : '' }}>Development simulation — no WhatsApp sent (local/testing only)</option>
                                    @endif
                                </select>
                                <small id="promexWhatsappHint" class="text-muted">PROMEX sends only its approved verification template with the generated six-digit OTP. Your app cannot supply message text.</small>
                            </div>
                            <div id="customWhatsappApiFields" class="form-row">
                                <div class="form-group col-md-6 mb-2">
                                    <label class="small font-weight-bold">Custom Gateway HTTPS URL</label>
                                    <input type="url" name="whatsapp_api_endpoint" class="form-control" placeholder="https://api.gateway.com/send" value="{{ settings('whatsapp_api_endpoint', '') }}">
                                </div>
                                <div class="form-group col-md-6 mb-2">
                                    <label class="small font-weight-bold">Custom Bearer Token</label>
                                    <input type="password" name="whatsapp_api_token" class="form-control" placeholder="{{ \VanguardLTE\Services\DeliveryGatewaySettings::hasSecret('whatsapp') ? 'Saved — leave blank to keep it' : 'Bearer token or API secret' }}" value="" autocomplete="new-password">
                                </div>
                            </div>
                        </div>

                        <!-- Email Gateway -->
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="font-weight-bold text-primary"><i class="fas fa-envelope mr-1"></i> Transactional Email Delivery</h6>
                            <p class="text-muted small">Connect your own verified transactional-email account. PROMEX never receives your email token or sends your email.</p>
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold">Provider</label>
                                <select id="emailDeliveryProvider" name="email_delivery_provider" class="form-control">
                                    <option value="disabled" {{ settings('email_delivery_provider', 'disabled') === 'disabled' ? 'selected' : '' }}>Not configured — email is off</option>
                                    <option value="brevo" {{ settings('email_delivery_provider', 'disabled') === 'brevo' ? 'selected' : '' }}>Brevo — recommended free transactional email</option>
                                    <option value="resend" {{ settings('email_delivery_provider', 'disabled') === 'resend' ? 'selected' : '' }}>Resend — developer-friendly transactional email</option>
                                    <option value="postmark" {{ settings('email_delivery_provider', 'disabled') === 'postmark' ? 'selected' : '' }}>Postmark — transactional deliverability</option>
                                    <option value="custom" {{ settings('email_delivery_provider', 'disabled') === 'custom' ? 'selected' : '' }}>Custom compatible HTTPS API</option>
                                </select>
                                <small id="emailProviderHint" class="text-muted">Brevo uses its transactional-email API. Verify your sender domain before sending OTP or welcome email.</small>
                            </div>
                            <div id="emailProviderFields" class="form-row">
                                <div class="form-group col-md-6 mb-2">
                                    <label class="small font-weight-bold">Verified sender email</label>
                                    <input id="emailFromAddress" type="email" name="email_from_address" class="form-control" placeholder="hello@yourdomain.com" value="{{ settings('email_from_address', '') }}">
                                </div>
                                <div class="form-group col-md-6 mb-2">
                                    <label class="small font-weight-bold">Sender name</label>
                                    <input type="text" name="email_from_name" class="form-control" maxlength="100" placeholder="Your brand" value="{{ settings('email_from_name', settings('app_name', '')) }}">
                                </div>
                                <div id="customEmailApiEndpoint" class="form-group col-md-6 mb-2 d-none">
                                    <label class="small font-weight-bold">Custom Email Router HTTPS URL</label>
                                    <input type="url" name="email_api_endpoint" class="form-control" placeholder="https://api.mail-router.com/send" value="{{ settings('email_api_endpoint', '') }}">
                                </div>
                                <div class="form-group col-md-6 mb-2">
                                    <label id="emailTokenLabel" class="small font-weight-bold">Brevo API Key</label>
                                    <input type="password" name="email_api_token" class="form-control" placeholder="{{ \VanguardLTE\Services\DeliveryGatewaySettings::hasSecret('email') ? 'Saved — leave blank to keep it' : 'Bearer token or API secret' }}" value="" autocomplete="new-password">
                                </div>
                            </div>
                        </div>

                        <!-- Polymarket Gamma API (Prediction Markets) -->
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="font-weight-bold text-info mb-0">
                                    <i class="fas fa-chart-pie mr-1"></i> Polymarket Gamma API (Prediction Markets)
                                </h6>
                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> FREE with active license</span>
                            </div>
                            <p class="text-muted small mb-2">Use the licensed PROMEX feed for real-time prediction markets, YES/NO probabilities, and order books, or connect a compatible custom API.</p>
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold">Provider</label>
                                <select id="polymarketApiProvider" name="polymarket_api_provider" class="form-control">
                                    <option value="promex" {{ settings('polymarket_api_provider', 'promex') === 'promex' ? 'selected' : '' }}>PROMEX Licensed API — free with active license (recommended)</option>
                                    <option value="custom" {{ settings('polymarket_api_provider', 'promex') === 'custom' ? 'selected' : '' }}>My private prediction-market API</option>
                                </select>
                                <small id="promexPolymarketHint" class="text-muted">Uses the managed official Gamma feed. No API key or endpoint configuration is required in this app.</small>
                            </div>
                            <div id="customPolymarketApiFields" class="alert alert-dark border mb-2" role="status">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <strong><i class="fas fa-lock mr-1 text-info"></i> Private provider connection</strong>
                                        <div class="small text-muted">Connection details are managed securely by the service and are not displayed in this control panel.</div>
                                    </div>
                                    <button type="button" id="btnTestPolyApi" class="btn btn-outline-info btn-sm" title="Test private prediction-market provider">
                                        <i class="fas fa-plug mr-1"></i> Test Custom API
                                    </button>
                                </div>
                            </div>
                            <button type="button" id="btnTestPromexPolyApi" class="btn btn-outline-info btn-sm" title="Test licensed prediction-market feed">
                                <i class="fas fa-plug mr-1"></i> Test PROMEX API
                            </button>
                            <div id="polyApiStatus" class="small mt-1 d-none"></div>
                        </div>

                        <!-- Coin Economy Defaults -->
                        <div class="mb-4">
                            <h6 class="font-weight-bold text-dark"><i class="fas fa-coins mr-1 text-warning"></i> Virtual Economy & Coin Defaults</h6>
                            <div class="form-row">
                                <div class="form-group col-md-6 mb-2">
                                    <label class="small font-weight-bold">Single Refill Amount (Coins)</label>
                                    <input type="number" step="100" name="default_refill_amount" class="form-control" value="{{ settings('default_refill_amount', '1000') }}">
                                </div>
                                <div class="form-group col-md-6 mb-2">
                                    <label class="small font-weight-bold">New Registered Player Starting Balance</label>
                                    <input type="number" step="100" name="default_starting_coins" class="form-control" value="{{ settings('default_starting_coins', '1000') }}">
                                    <small class="text-muted">e.g. 1,000 Coins = $10.00 play balance at 100 points/$1</small>
                                </div>
                            </div>
                        </div>

                        <!-- Prize Redemption & Cashout Controls -->
                        <div class="mb-4 p-3 rounded border bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-hand-holding-usd mr-1 text-success"></i> Prize Redemption & Cashout Controls</h6>
                                <span class="badge badge-{{ settings('enable_cashout', '1') == '1' ? 'success' : 'secondary' }}">
                                    {{ settings('enable_cashout', '1') == '1' ? 'Active' : 'Disabled' }}
                                </span>
                            </div>
                            <p class="text-muted small mb-3">Control whether players can request manual prize cashouts, exchange rates, and limits.</p>

                            <div class="form-row">
                                <div class="form-group col-md-4 mb-2">
                                    <label class="small font-weight-bold">Cashout Module Enabled</label>
                                    <select name="enable_cashout" class="form-control">
                                        <option value="1" {{ settings('enable_cashout', '1') == '1' ? 'selected' : '' }}>Enabled (Show in User Profile)</option>
                                        <option value="0" {{ settings('enable_cashout', '1') == '0' ? 'selected' : '' }}>Disabled (Hidden Platform-Wide)</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4 mb-2">
                                    <label class="small font-weight-bold">Exchange Rate (Points per $1.00 USD)</label>
                                    <div class="input-group">
                                        <input type="number" step="1" min="1" name="coins_per_dollar" class="form-control font-weight-bold" value="{{ settings('coins_per_dollar', '100') }}">
                                        <div class="input-group-append"><span class="input-group-text">Pts = $1.00</span></div>
                                    </div>
                                    <small class="text-muted">100 points = $1.00 (1 cent = 1 point)</small>
                                </div>
                                <div class="form-group col-md-4 mb-2">
                                    <label class="small font-weight-bold">Minimum Cashout Limit (Points)</label>
                                    <div class="input-group">
                                        <input type="number" step="100" min="100" name="min_cashout_coins" class="form-control" value="{{ settings('min_cashout_coins', '2000') }}">
                                        <div class="input-group-append"><span class="input-group-text">Points</span></div>
                                    </div>
                                    <small class="text-muted">2,000 Pts = $20.00 USD minimum</small>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">Allowed Withdrawal Methods (Comma-separated)</label>
                                <input type="text" name="cashout_methods" class="form-control" value="{{ settings('cashout_methods', 'USDT (TRC-20), Whish Money, OMT, Bank Transfer, PayPal, Cash Agent') }}">
                                <small class="text-muted">Shown in player withdrawal method dropdown</small>
                            </div>
                        </div>

                        <hr>

                        <!-- CEDAR Originals Settings -->
                        <div>
                            <h6 class="font-weight-bold text-dark"><i class="fas fa-rocket mr-1 text-danger"></i> CEDAR Originals & Provably Fair Controls</h6>
                            <p class="text-muted small mb-3">Tune the cryptographic mathematical engine, house edge, and wagering limits for Crash, Plinko, and Mines.</p>

                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="font-weight-bold text-dark mb-2"><i class="fas fa-palette mr-1"></i> Shared CEDAR Remake Branding</div>
                                <div class="form-row">
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">Provider Name</label>
                                        <input type="text" name="cedar_remake_brand_name" class="form-control" value="{{ settings('cedar_remake_brand_name', 'CEDAR') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">Global Loader CSS Path</label>
                                        <input type="text" name="cedar_remake_loader_css" class="form-control" value="{{ settings('cedar_remake_loader_css', '/CedarGames/_runtime/loader.css') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">Global Theme CSS Path</label>
                                        <input type="text" name="cedar_remake_theme_css" class="form-control" value="{{ settings('cedar_remake_theme_css', '/CedarGames/_runtime/cedar-slot.css') }}">
                                    </div>
                                </div>
                                <small class="text-muted">Same-origin paths only. One change updates every game under /CedarGames; a manifest may add one game-specific theme layer.</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="font-weight-bold">Maximum payout per Cedar round (Coins)</label>
                                <input type="number" name="cedar_max_payout" min="1" max="100000000" step="0.01" required class="form-control" value="{{ settings('cedar_max_payout', '1000000') }}">
                                <small class="text-muted">Applies to new rounds. Existing rounds retain their original rules. Wheel tables return at most 99% before payout rounding, caps and rewards. Crash is a solo game.</small>
                            </div>
                            <!-- CedarCrash -->
                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="font-weight-bold text-primary mb-2"><i class="fas fa-chart-line mr-1"></i> CedarCrash</div>
                                <div class="form-row">
                                    <div class="form-group col-md-3 mb-2">
                                        <label class="small font-weight-bold">House Edge %</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="5" max="20" name="cedar_crash_house_edge" class="form-control" value="{{ max(5, (float) settings('cedar_crash_house_edge', '5.0')) }}">
                                            <div class="input-group-append"><span class="input-group-text">%</span></div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-3 mb-2">
                                        <label class="small font-weight-bold">Min Bet</label>
                                        <input type="number" step="any" min="1" max="100000" name="cedar_crash_min_bet" class="form-control" value="{{ settings('cedar_crash_min_bet', '10') }}">
                                    </div>
                                    <div class="form-group col-md-3 mb-2">
                                        <label class="small font-weight-bold">Max Bet</label>
                                        <input type="number" step="any" min="10" max="1000000" name="cedar_crash_max_bet" class="form-control" value="{{ settings('cedar_crash_max_bet', '50000') }}">
                                    </div>
                                    <div class="form-group col-md-3 mb-2">
                                        <label class="small font-weight-bold">Max Multiplier Cap</label>
                                        <div class="input-group">
                                            <input type="number" step="any" min="2" max="10000" name="cedar_crash_max_multiplier" class="form-control" value="{{ settings('cedar_crash_max_multiplier', '1000.0') }}">
                                            <div class="input-group-append"><span class="input-group-text">x</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CedarPlinko -->
                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="font-weight-bold text-success mb-2"><i class="fas fa-circle-notch mr-1"></i> CedarPlinko</div>
                                <div class="form-row">
                                    <div class="form-group col-md-6 mb-2">
                                        <label class="small font-weight-bold">Min Bet (Coins)</label>
                                        <input type="number" step="any" min="1" max="100000" name="cedar_plinko_min_bet" class="form-control" value="{{ settings('cedar_plinko_min_bet', '10') }}">
                                    </div>
                                    <div class="form-group col-md-6 mb-2">
                                        <label class="small font-weight-bold">Max Bet (Coins)</label>
                                        <input type="number" step="any" min="10" max="1000000" name="cedar_plinko_max_bet" class="form-control" value="{{ settings('cedar_plinko_max_bet', '50000') }}">
                                    </div>
                                </div>
                            </div>

                            <!-- CedarMines -->
                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="font-weight-bold text-warning mb-2"><i class="fas fa-gem mr-1"></i> CedarMines</div>
                                <div class="form-row">
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">House Edge %</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="5" max="20" name="cedar_mines_house_edge" class="form-control" value="{{ max(5, (float) settings('cedar_mines_house_edge', '5.0')) }}">
                                            <div class="input-group-append"><span class="input-group-text">%</span></div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">Min Bet (Coins)</label>
                                        <input type="number" step="any" min="1" max="100000" name="cedar_mines_min_bet" class="form-control" value="{{ settings('cedar_mines_min_bet', '10') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">Max Bet (Coins)</label>
                                        <input type="number" step="any" min="10" max="1000000" name="cedar_mines_max_bet" class="form-control" value="{{ settings('cedar_mines_max_bet', '50000') }}">
                                    </div>
                                </div>
                            </div>

                            <!-- CedarDice -->
                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="font-weight-bold text-info mb-2"><i class="fas fa-dice-d20 mr-1"></i> CedarDice (Slider 0.00 – 99.99)</div>
                                <div class="form-row">
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">House Edge %</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="5" max="20" name="cedar_dice_house_edge" class="form-control" value="{{ max(5, (float) settings('cedar_dice_house_edge', '5.0')) }}">
                                            <div class="input-group-append"><span class="input-group-text">%</span></div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">Min Bet (Coins)</label>
                                        <input type="number" step="any" min="1" max="100000" name="cedar_dice_min_bet" class="form-control" value="{{ settings('cedar_dice_min_bet', '10') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label class="small font-weight-bold">Max Bet (Coins)</label>
                                        <input type="number" step="any" min="10" max="1000000" name="cedar_dice_max_bet" class="form-control" value="{{ settings('cedar_dice_max_bet', '50000') }}">
                                    </div>
                                </div>
                            </div>

                            <!-- CedarWheel -->
                            <div class="p-3 bg-light rounded border">
                                <div class="font-weight-bold text-danger mb-2"><i class="fas fa-dharmachakra mr-1"></i> CedarWheel (Fortune Multipliers up to 49.5x)</div>
                                <div class="form-row">
                                    <div class="form-group col-md-6 mb-2">
                                        <label class="small font-weight-bold">Min Bet (Coins)</label>
                                        <input type="number" step="any" min="1" max="100000" name="cedar_wheel_min_bet" class="form-control" value="{{ settings('cedar_wheel_min_bet', '10') }}">
                                    </div>
                                    <div class="form-group col-md-6 mb-2">
                                        <label class="small font-weight-bold">Max Bet (Coins)</label>
                                        <input type="number" step="any" min="10" max="1000000" name="cedar_wheel_max_bet" class="form-control" value="{{ settings('cedar_wheel_max_bet', '50000') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-success font-weight-bold px-4">
                            <i class="fas fa-save mr-1"></i> Save System Settings
                        </button>
                    </div>
                </div>
            </form>
            <div class="card mt-3">
                <div class="card-header font-weight-bold">Recent Cedar rounds</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Round</th><th>Player</th><th>Game</th><th>Status</th><th>Wager</th><th>Paid</th></tr></thead>
                    <tbody>@forelse($cedarRounds ?? [] as $round)
                    <tr><td><small>{{ $round->id }}</small></td><td>{{ $round->user_id }}</td><td>{{ $round->game }}</td><td>{{ $round->status }}</td><td>{{ $round->wager }}</td><td>{{ $round->win }}</td></tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-2">No recent Cedar rounds found.</td></tr>
                    @endforelse</tbody>
                </table></div>
                <div class="card-footer text-muted small">Crash disconnects are settled by the Laravel scheduler every minute. Active rounds retain the settings accepted at bet time.</div>
            </div>
        </div>

        <!-- Sidebar Actions & System Utilities -->
        <div class="col-lg-4">
            <!-- Manual Operations & Sync Card -->
            <div class="card mb-4 shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-bolt mr-2"></i> Manual Operations</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Execute live server routines immediately without waiting for background cron schedules.</p>
                    
                    <!-- Flush Cache -->
                    <form method="post" action="{{ route('liteback.settings.clear_cache') }}" class="mb-3" onsubmit="return confirm('Flush all application caches now?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-block font-weight-bold">
                            <i class="fas fa-trash-alt mr-1"></i> Flush System Caches
                        </button>
                        <small class="text-muted d-block mt-1">Clears Blade templates, route caches, and Redis/file cache.</small>
                    </form>

                    <hr>

                    <!-- Manual Odds Sync -->
                    <form method="post" action="{{ route('liteback.settings.sync_odds_now') }}" class="mb-3">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-block font-weight-bold">
                            <i class="fas fa-sync mr-1"></i> Pull Sports Odds Now
                        </button>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Auto: Every 30 min</small>
                            <span class="badge badge-light border text-primary">
                                <i class="far fa-clock mr-1"></i> {{ settings('last_odds_sync_at') ? \Carbon\Carbon::parse(settings('last_odds_sync_at'))->diffForHumans() : 'Never' }}
                            </span>
                        </div>
                    </form>

                    <!-- Manual Settle Bets -->
                    <form method="post" action="{{ route('liteback.settings.settle_matches_now') }}" class="mb-3">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-block font-weight-bold">
                            <i class="fas fa-check-double mr-1"></i> Run Bet Auto-Settlement
                        </button>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Auto: Every 5 min</small>
                            <span class="badge badge-light border text-success">
                                <i class="far fa-clock mr-1"></i> {{ settings('last_sports_settlement_at') ? \Carbon\Carbon::parse(settings('last_sports_settlement_at'))->diffForHumans() : 'Never' }}
                            </span>
                        </div>
                    </form>

                    <!-- Manual Trigger Lotto Draw -->
                    <form method="post" action="{{ route('liteback.settings.draw_lotto_now') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-block font-weight-bold">
                            <i class="fas fa-ticket-alt mr-1"></i> Trigger Lotto Draw Now
                        </button>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Auto: Hourly</small>
                            <span class="badge badge-light border text-warning">
                                <i class="far fa-clock mr-1"></i> {{ settings('last_lotto_draw_at') ? \Carbon\Carbon::parse(settings('last_lotto_draw_at'))->diffForHumans() : 'Never' }}
                            </span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- System Info Widget -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-info-circle mr-1 text-info"></i> Server Environment</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0 small">
                        <tr>
                            <td class="font-weight-bold">PHP Version</td>
                            <td class="text-right">{{ phpversion() }}</td>
                        </tr>
                        <tr>
                            <td class="font-weight-bold">Laravel Core</td>
                            <td class="text-right">{{ app()->version() }}</td>
                        </tr>
                        <tr>
                            <td class="font-weight-bold">Database</td>
                            <td class="text-right">{{ config('database.default') }} ({{ config('database.connections.' . config('database.default') . '.database') }})</td>
                        </tr>
                        <tr>
                            <td class="font-weight-bold">Server Host</td>
                            <td class="text-right">{{ request()->getHost() }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    const toggleSportsbookProvider = function() {
        const isPromex = $('#sportsbookApiProvider').val() === 'promex';
        $('#customOddsApiFields').toggleClass('d-none', isPromex);
        $('#btnTestPromexSportsApi').toggleClass('d-none', !isPromex);
        $('#promexSportsbookHint').toggleClass('d-none', !isPromex);
    };

    const testSportsbookProvider = function(provider) {
        const apiKey = $('#oddsApiKeyInput').val();
        const statusDiv = $('#oddsApiStatus');
        
        statusDiv.removeClass('d-none alert-success alert-danger').addClass('alert alert-info py-2').text('Testing selected sportsbook provider...');

        $.post('{{ route('liteback.settings.test_odds_api') }}', {
            _token: '{{ csrf_token() }}',
            provider: provider,
            api_key: apiKey
        }, function(res) {
            if (res.success) {
                statusDiv.removeClass('alert-info alert-danger').addClass('alert-success').html('<i class="fas fa-check-circle mr-1"></i> ' + res.message);
            } else {
                statusDiv.removeClass('alert-info alert-success').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle mr-1"></i> ' + res.message);
            }
        }).fail(function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Network failure while testing API endpoint.';
            statusDiv.removeClass('alert-info alert-success').addClass('alert-danger').text(message);
        });
    };

    $('#sportsbookApiProvider').on('change', toggleSportsbookProvider);
    $('#btnTestOddsApi').on('click', function() { testSportsbookProvider('custom'); });
    $('#btnTestPromexSportsApi').on('click', function() { testSportsbookProvider('promex'); });
    toggleSportsbookProvider();

    const toggleCryptoPricesProvider = function() {
        const isPromex = $('#cryptoPricesProvider').val() === 'promex';
        $('#customCryptoApiFields, #btnTestPromexCryptoApi, #promexCryptoHint').each(function() { $(this).toggleClass('d-none', this.id === 'customCryptoApiFields' ? isPromex : !isPromex); });
    };
    const testCryptoPricesProvider = function(provider) {
        const status = $('#cryptoApiStatus');
        status.removeClass('d-none alert-success alert-danger').addClass('alert alert-info py-2').text('Testing selected crypto-price provider...');
        $.post('{{ route('liteback.settings.test_crypto_prices_api') }}', {_token: '{{ csrf_token() }}', provider: provider, endpoint: $('#cryptoPricesEndpoint').val()}, function(res) {
            status.removeClass('alert-info alert-danger').addClass('alert-success').html('<i class="fas fa-check-circle mr-1"></i> ' + res.message);
        }).fail(function(xhr) { status.removeClass('alert-info alert-success').addClass('alert-danger').text(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Network failure while testing API endpoint.'); });
    };
    $('#cryptoPricesProvider').on('change', toggleCryptoPricesProvider);
    $('#btnTestCryptoApi').on('click', function() { testCryptoPricesProvider('custom'); });
    $('#btnTestPromexCryptoApi').on('click', function() { testCryptoPricesProvider('promex'); });
    toggleCryptoPricesProvider();

    const togglePolymarketProvider = function() {
        const isPromex = $('#polymarketApiProvider').val() === 'promex';
        $('#customPolymarketApiFields').toggleClass('d-none', isPromex);
        $('#btnTestPromexPolyApi').toggleClass('d-none', !isPromex);
        $('#promexPolymarketHint').toggleClass('d-none', !isPromex);
    };

    const testPolymarketProvider = function(provider) {
        const statusDiv = $('#polyApiStatus');
        statusDiv.removeClass('d-none alert-success alert-danger').addClass('alert alert-info py-2').text('Testing selected prediction-market provider...');

        $.post('{{ route('liteback.settings.test_polymarket_api') }}', {
            _token: '{{ csrf_token() }}',
            provider: provider
        }, function(res) {
            if (res.success) {
                statusDiv.removeClass('alert-info alert-danger').addClass('alert-success').html('<i class="fas fa-check-circle mr-1"></i> ' + res.message);
            } else {
                statusDiv.removeClass('alert-info alert-success').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle mr-1"></i> ' + res.message);
            }
        }).fail(function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Network failure while testing API endpoint.';
            statusDiv.removeClass('alert-info alert-success').addClass('alert-danger').text(message);
        });
    };

    $('#polymarketApiProvider').on('change', togglePolymarketProvider);
    $('#btnTestPolyApi').on('click', function() { testPolymarketProvider('custom'); });
    $('#btnTestPromexPolyApi').on('click', function() { testPolymarketProvider('promex'); });
    togglePolymarketProvider();

    const toggleWhatsappProvider = function() {
        const isPromex = $('#whatsappDeliveryProvider').val() === 'promex';
        $('#customWhatsappApiFields').toggleClass('d-none', isPromex);
        $('#promexWhatsappHint').toggleClass('d-none', !isPromex);
    };
    $('#whatsappDeliveryProvider').on('change', toggleWhatsappProvider);
    toggleWhatsappProvider();

    const emailProviderDetails = {
        brevo: {token: 'Brevo API Key', hint: 'Brevo uses its transactional-email API. Verify your sender domain before sending OTP or welcome email.'},
        resend: {token: 'Resend API Key', hint: 'Resend sends from a verified domain. Its API token stays encrypted in this installation.'},
        postmark: {token: 'Postmark Server Token', hint: 'Postmark needs a confirmed sender signature and its server-level token.'},
        custom: {token: 'Custom Bearer Token', hint: 'Your custom endpoint receives the approved transactional email payload over HTTPS.'},
        disabled: {token: 'Provider token', hint: 'Email delivery is off until you choose and configure a provider.'}
    };
    const toggleEmailProvider = function() {
        const provider = $('#emailDeliveryProvider').val();
        const details = emailProviderDetails[provider] || emailProviderDetails.brevo;
        $('#emailTokenLabel').text(details.token);
        $('#emailProviderHint').text(details.hint);
        $('#customEmailApiEndpoint').toggleClass('d-none', provider !== 'custom');
        $('#emailProviderFields').toggleClass('d-none', provider === 'disabled');
        $('#emailFromAddress').prop('required', provider !== 'disabled');
    };
    $('#emailDeliveryProvider').on('change', toggleEmailProvider);
    toggleEmailProvider();
});
</script>
@endsection
