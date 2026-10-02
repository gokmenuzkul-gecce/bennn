<?php
use Illuminate\Support\Facades\Route;

Route::namespace ('Frontend')->middleware(['siteisclosed', 'checker'])->group(function ()
{

    Route::get('login', ['as' => 'frontend.auth.login', 'uses' => 'Auth\AuthController@getLogin']);

    Route::get('launcher/{game}/{token}', 'Auth\AuthController@apiLogin');

    Route::get('refresh-csrf', function ()
    {
        return csrf_token();
    });

    Route::post('login', ['as' => 'frontend.auth.login.post', 'uses' => 'Auth\AuthController@postLogin']);
    Route::get('logout', ['as' => 'frontend.auth.logout', 'uses' => 'Auth\AuthController@getLogout']);
    Route::post('auth/phone/otp', ['as' => 'frontend.auth.phone.otp', 'uses' => 'Auth\MultiAuthController@postPhoneOtp'])
        ->middleware('throttle:5,1');
    Route::post('auth/phone/verify', ['as' => 'frontend.auth.phone.verify', 'uses' => 'Auth\MultiAuthController@verifyPhoneOtp'])
        ->middleware('throttle:10,1');
    Route::post('auth/profile/update', ['as' => 'frontend.auth.profile.update', 'uses' => 'Auth\MultiAuthController@updateProfile']);

    Route::get('/specauth/{user}', ['as' => 'frontend.user.specauth', 'uses' => 'Auth\AuthController@specauth', ]);

    // Allow registration routes only if registration is enabled.
    if (settings('reg_enabled') || true)
    {

        Route::get('register', ['as' => 'frontend.register', 'uses' => 'Auth\AuthController@getRegister']);

        Route::post('register', ['as' => 'frontend.register.post', 'uses' => 'Auth\AuthController@postRegister']);

    }

    Route::get('register/confirmation/{token}', ['as' => 'frontend.register.confirm-email', 'uses' => 'Auth\AuthController@confirmEmail']);

    if (settings('forgot_password') || true) {

        Route::get('password/remind', [
            'as' => 'frontend.password.remind',
            'uses' => 'Auth\PasswordController@forgotPassword'
        ]);
        Route::post('password/remind', [
            'as' => 'frontend.password.remind.post',
            'uses' => 'Auth\PasswordController@sendPasswordReminder'
        ]);
        Route::get('password/reset/{token}', [
            'as' => 'frontend.password.reset',
            'uses' => 'Auth\PasswordController@getReset'
        ]);
        Route::post('password/reset', [
            'as' => 'frontend.password.reset.post',
            'uses' => 'Auth\PasswordController@postReset'
        ]);
    }

    Route::get('new-license', ['as' => 'frontend.new_license', 'uses' => 'PagesController@new_license']);
    Route::post('new-license', ['as' => 'frontend.new_license.post', 'uses' => 'PagesController@new_license_post']);

    Route::get('license-error', ['as' => 'frontend.page.error_license', 'uses' => 'PagesController@error_license']);

    // jpstv removed

    /**
     * Dashboard
     */

    /*
    Route::get('statistics', [
        'as' => 'frontend.statistics',
        'uses' => 'DashboardController@statistics'
    ]);
    */
    Route::get('subsession', ['as' => 'frontend.subsession', 'uses' => 'GamesController@subsession']);

    /**
     * User Profile
     */

    Route::get('profile', ['as' => 'frontend.profile', 'uses' => 'ProfileController@index']);
    Route::get('profile/activity', ['as' => 'frontend.profile.activity', 'uses' => 'ProfileController@activity']);

    Route::post('profile/details/update', ['as' => 'frontend.profile.update.details', 'uses' => 'ProfileController@updateDetails']);
    Route::post('profile/password/update', ['as' => 'frontend.profile.update.password', 'uses' => 'ProfileController@updatePassword']);
    Route::post('profile/avatar/update', ['as' => 'frontend.profile.update.avatar', 'uses' => 'ProfileController@updateAvatar']);
    Route::post('profile/avatar/update/external', ['as' => 'frontend.profile.update.avatar-external', 'uses' => 'ProfileController@updateAvatarExternal']);

    Route::get('profile/clear_phone', ['as' => 'frontend.clear_phone', 'uses' => 'ProfileController@clear_phone']);

    Route::post('profile/contact', ['as' => 'frontend.profile.contact', 'uses' => 'ProfileController@contact_form']);

    Route::put('profile/login-details/update', ['as' => 'frontend.profile.update.login-details', 'uses' => 'ProfileController@updateLoginDetails']);
    Route::post('profile/two-factor/enable', ['as' => 'frontend.profile.two-factor.enable', 'uses' => 'ProfileController@enableTwoFactorAuth']);
    Route::post('profile/two-factor/disable', ['as' => 'frontend.profile.two-factor.disable', 'uses' => 'ProfileController@disableTwoFactorAuth']);
    Route::get('profile/sessions', ['as' => 'frontend.profile.sessions', 'uses' => 'ProfileController@sessions']);
    Route::delete('profile/sessions/{session}/invalidate', ['as' => 'frontend.profile.sessions.invalidate', 'uses' => 'ProfileController@invalidateSession']);

    Route::get('profile/refunds', ['as' => 'frontend.profile.refunds', 'uses' => 'ProfileController@refunds']);

    Route::get('profile/ajax', ['as' => 'frontend.profile.ajax', 'uses' => 'ProfileController@ajax']);

    Route::get('profile/message', ['as' => 'frontend.profile.message', 'uses' => 'ProfileController@message']);

    Route::get('profile/daily_entry', ['as' => 'frontend.profile.daily_entry', 'uses' => 'ProfileController@daily_entry']);

    Route::get('profile/phone', ['as' => 'frontend.profile.phone', 'uses' => 'ProfileController@phone']);

    Route::get('profile/code', ['as' => 'frontend.profile.code', 'uses' => 'ProfileController@code']);

    Route::get('profile/agree', ['as' => 'frontend.profile.agree', 'uses' => 'ProfileController@agree']);

    Route::get('profile/transactions', ['as' => 'frontend.profile.transactions', 'uses' => 'ProfileController@transactions']);

    Route::get('profile/reward', ['as' => 'frontend.profile.reward', 'uses' => 'ProfileController@reward']);

    Route::get('profile/sms', ['as' => 'frontend.profile.sms', 'uses' => 'ProfileController@sms']);

    Route::get('setlang/{lang}', ['as' => 'frontend.setlang', 'uses' => 'ProfileController@setlang']);

    Route::post('profile/withdraw', ['as' => 'frontend.profile.withdraw', 'uses' => 'ProfileController@withdraw']);

    Route::post('topup/create', ['as' => 'frontend.topup.create', 'uses' => 'TopupController@create']);

    /**
     * Games routes
     */

    Route::get('/', ['as' => 'frontend.game.list', 'uses' => 'GamesController@index']);
    Route::get('/help/{locale?}', ['as' => 'frontend.help', 'uses' => 'HelpController@index'])->where('locale', 'en|fr|es|ru|tr|ar|he');
    Route::get('/faq', ['as' => 'frontend.faq', 'uses' => 'GamesController@faq', ]);

    Route::get('/bonuses', ['as' => 'frontend.bonuses', 'uses' => 'GamesController@bonuses', ]);
    Route::get('/bonus-conditions', ['as' => 'frontend.bonus.conditions', 'uses' => 'GamesController@bonus_conditions', ]);
    Route::get('/progress', ['as' => 'frontend.progress', 'uses' => 'GamesController@progress', ]);
    Route::get('/search', ['as' => 'frontend.game.search', 'uses' => 'GamesController@search']);
    Route::get('/search.json', ['as' => 'frontend.search.json', 'uses' => 'GamesController@search_json']);
    Route::post('balance', ['as' => 'frontend.balance.post', 'uses' => 'GamesController@balanceAdd']);

    /*
    Route::get('games', [
        'as' => 'frontend.game.list',
        'uses' => 'GamesController@index'
    ]);
    */

    Route::get('categories/{category1}', ['as' => 'frontend.game.list.category', 'uses' => 'GamesController@index']);

    Route::get('categories/{category1}/{category2}', ['as' => 'frontend.game.list.category_level2', 'uses' => 'GamesController@index']);

    Route::get('setpage.json', ['as' => 'frontend.category.setpage', 'uses' => 'GamesController@setpage']);

    // Legacy provider launchers request this once from the root domain before
    // constructing a WebSocket. Generate a same-origin, licensed bridge target
    // instead of exposing the retired external socket host/ports.
    Route::get('socket_config.json', [
        'as' => 'frontend.provider.socket_config',
        'uses' => 'ProviderCompatibilityController@socketConfig',
    ]);

    Route::get('game/{game}', ['as' => 'frontend.game.go', 'uses' => 'GamesController@go'])->middleware('game.homebutton');
    Route::get('game/{game}/launch', ['as' => 'frontend.game.launch_json', 'uses' => 'GamesController@launch_json']);
    Route::post('game/{game}/server', ['as' => 'frontend.game.server', 'uses' => 'GamesController@server'])->middleware(\VanguardLTE\Http\Middleware\ProtectGameRequests::class);

    // Optional provider compatibility. Existing physical files bypass Laravel
    // in the root .htaccess, so these routes answer only genuinely missing URLs.
    Route::get('operator_logos/logo_info.js', [
        'as' => 'frontend.provider.operator_logo_info',
        'uses' => 'ProviderCompatibilityController@operatorLogoInfo',
    ]);
    Route::get('gs2c/announcements/unread', [
        'as' => 'frontend.provider.unread_announcements',
        'uses' => 'ProviderCompatibilityController@unreadAnnouncements',
    ]);
    Route::get('gs2c/promo/frb/available', [
        'as' => 'frontend.provider.available_free_rounds',
        'uses' => 'ProviderCompatibilityController@availableFreeRounds',
    ]);

    Route::get('game/{game}/{prego}', ['as' => 'frontend.game.go.prego', 'uses' => 'GamesController@go'])->middleware('game.homebutton');

    Route::get('/game_stat', ['as' => 'frontend.game_stat', 'uses' => 'GamesController@game_stat', ]);

    Route::prefix('payment')->group(function ()
    {
        Route::post('/interkassa/result', ['as' => 'payment.interkassa.result', 'uses' => 'Payment\InterkassaController@index']);
        Route::get('/interkassa/success', ['as' => 'payment.interkassa.success', 'uses' => 'Payment\InterkassaController@success']);
        Route::get('/interkassa/fail', ['as' => 'payment.interkassa.fail', 'uses' => 'Payment\InterkassaController@fail']);
        Route::get('/interkassa/wait', ['as' => 'payment.interkassa.wait', 'uses' => 'Payment\InterkassaController@wait']);

        Route::post('/coinbase/result', ['as' => 'payment.coinbase.result', 'uses' => 'Payment\CoinbaseController@index']);
        Route::get('/coinbase/success', ['as' => 'payment.coinbase.success', 'uses' => 'Payment\CoinbaseController@success']);
        Route::get('/coinbase/fail', ['as' => 'payment.coinbase.fail', 'uses' => 'Payment\CoinbaseController@fail']);

        Route::post('/btcpayserver/result', ['as' => 'payment.btcpayserver.result', 'uses' => 'Payment\BtcPayServerController@index']);
        Route::get('/btcpayserver/redirect', ['as' => 'payment.btcpayserver.redirect', 'uses' => 'Payment\BtcPayServerController@redirect']);

    });

    Route::post('/sms/callback', ['as' => 'sms.callback', 'uses' => 'SMSController@index']);

    // Removed legacy CoinPayment integration (controller missing)

    // Sportsbook Frontend Routes
    Route::get('/sports', ['as' => 'frontend.sports.index', 'uses' => 'SportsbookController@index']);
    Route::post('/sports/bet', ['as' => 'frontend.sports.bet', 'uses' => 'SportsbookController@placeBet']);
    Route::get('/sports/cashout-quote/{betId}', ['as' => 'frontend.sports.cashout_quote', 'uses' => 'SportsbookController@getCashoutQuote']);
    Route::post('/sports/cashout', ['as' => 'frontend.sports.cashout', 'uses' => 'SportsbookController@cashout']);

    // PayPal & Manual Payment paths
    Route::get('payment/paypal/return', ['as' => 'payment.paypal.return', 'uses' => 'TopupController@paypalReturn']);
    Route::get('payment/manual/{intent}', ['as' => 'payment.manual.show', 'uses' => 'TopupController@showManualPayment']);
    Route::post('payment/manual/{intent}/submit', ['as' => 'payment.manual.submit', 'uses' => 'TopupController@submitManualDeposit']);
    Route::post('profile/withdraw', ['as' => 'frontend.profile.withdraw', 'uses' => 'ProfileController@withdraw']);

    // Lotto & Jackpot Zone
    Route::get('/jackpot-zone', ['as' => 'frontend.lotto.index', 'uses' => 'SocialGamingController@lotto']);
    Route::post('/lotto/play', ['as' => 'frontend.lotto.play', 'uses' => 'SocialGamingController@lottoPlay']);
    Route::post('/jackpot-zone/buy-ticket', ['as' => 'frontend.lotto.buy', 'uses' => 'SocialGamingController@lottoPlay']);

    // Future Vote / Prediction Markets
    Route::get('/future-vote', ['as' => 'frontend.predictions.index', 'uses' => 'PredictionsController@index']);
    Route::get('/predictions/search', ['as' => 'frontend.predictions.search', 'uses' => 'PredictionsController@search']);
    Route::post('/predictions/vote', ['as' => 'frontend.predictions.vote', 'uses' => 'PredictionsController@vote']);
    Route::post('/predictions/custom', ['as' => 'frontend.predictions.custom', 'uses' => 'PredictionsController@createCustomMarket']);
    Route::get('/predictions/{marketId}/order-book', ['as' => 'frontend.predictions.order-book', 'uses' => 'PredictionsController@orderBook'])
        ->where('marketId', '[A-Za-z0-9_-]+');

    // Crypto Trading Simulator. Client price reads are from the local CDN-safe cache only.
    Route::get('/crypto-trading', ['as' => 'frontend.crypto.index', 'uses' => 'CryptoTradingController@index']);
    Route::post('/crypto-trading/positions', ['as' => 'frontend.crypto.place', 'uses' => 'CryptoTradingController@place']);
    Route::get('/crypto-trading/cache.json', ['as' => 'frontend.crypto.cache', 'uses' => 'CryptoTradingController@cache']);
    Route::get('/stocks-trading', ['as' => 'frontend.stocks.index', 'uses' => 'StockTradingController@index']);
    Route::post('/stocks-trading/positions', ['as' => 'frontend.stocks.place', 'uses' => 'StockTradingController@place']);
    Route::get('/stocks-trading/cache.json', ['as' => 'frontend.stocks.cache', 'uses' => 'StockTradingController@cache']);

    // 3-Tier Affiliates
    Route::get('/affiliates', ['as' => 'frontend.affiliates.index', 'uses' => 'AffiliateController@index']);
    Route::post('/affiliates/claim', ['as' => 'frontend.affiliates.claim', 'uses' => 'AffiliateController@claim']);

    // VIP Club & Loyalty Vault
    Route::get('/vip', ['as' => 'frontend.vip.index', 'uses' => 'VipController@index']);
    Route::post('/vip/claim-rakeback', ['as' => 'frontend.vip.claim_rakeback', 'uses' => 'VipController@claimRakeback']);
    Route::post('/vip/claim-bonus', ['as' => 'frontend.vip.claim_bonus', 'uses' => 'VipController@claimLevelBonus']);
    Route::post('/vip/claim-level-bonus', ['as' => 'frontend.vip.claim_level_bonus', 'uses' => 'VipController@claimLevelBonus']);

});

// Payment webhooks (no auth)
Route::post('payment/webhook/btcpay', [\VanguardLTE\Http\Controllers\Web\Frontend\TopupController::class, 'webhookBtcpay'])->name('payment.webhook.btcpay');
Route::post('payment/webhook/stripe', [\VanguardLTE\Http\Controllers\Web\Frontend\TopupController::class, 'webhookStripe'])->name('payment.webhook.stripe');
Route::post('payment/webhook/xtopay', [\VanguardLTE\Http\Controllers\Web\Frontend\TopupController::class, 'webhookXtopay'])->name('payment.webhook.xtopay');

// Casino seamless-wallet callbacks (no auth, signature verified per provider).
// Registered with the aggregator as <APP_URL>/webhooks/aggregator/<slug>/wallet.
// The operation may arrive in the path (/wallet/BetWin) or in the body
// (action=bet) when the vendor posts everything to the base URL.
Route::post('webhooks/aggregator/{slug}/wallet/{operation?}', [
    \VanguardLTE\Http\Controllers\Web\Webhooks\CasinoWalletController::class, 'handle',
])->where('operation', 'GetBalance|Withdraw|Deposit|BetWin|RollbackTransaction')
    ->name('webhooks.casino.wallet');


/**
 *
 *
 * Liteback (new lightweight admin)
 *
 */
Route::prefix('liteback')
    ->middleware(['auth', 'checker'])
    ->namespace('Liteback')
    ->group(function () {
        Route::get('/help/{locale?}', ['as' => 'liteback.help', 'uses' => 'HelpController@index'])->where('locale', 'en|fr|es|ru|tr|ar|he');
        Route::get('/', ['as' => 'liteback.users.index', 'uses' => 'UserController@index']);
        Route::post('/users/{user}/balance', ['as' => 'liteback.users.balance', 'uses' => 'UserController@adjustBalance']);
        Route::post('/users/{user}/toggle-status', ['as' => 'liteback.users.toggle_status', 'uses' => 'UserController@toggleStatus']);
        Route::get('/users/{user}/detail', ['as' => 'liteback.users.detail', 'uses' => 'UserController@detail']);
        Route::post('/users', ['as' => 'liteback.users.store', 'uses' => 'UserController@store']);
        Route::delete('/users/{user}', ['as' => 'liteback.users.delete', 'uses' => 'UserController@destroy']);
        Route::get('/games', ['as' => 'liteback.games.index', 'uses' => 'GameController@index']);
        Route::get('/games/inactive', ['as' => 'liteback.games.inactive', 'uses' => 'GameController@inactive']);
        Route::get('/cedar', ['as' => 'liteback.cedar.index', 'uses' => 'GameController@cedar']);
        Route::get('/cedar/inactive', ['as' => 'liteback.cedar.inactive', 'uses' => 'GameController@cedarInactive']);
        Route::post('/cedar/sync', ['as' => 'liteback.cedar.sync', 'uses' => 'GameController@syncCedarCatalog']);
        Route::delete('/games/{game}', ['as' => 'liteback.games.delete', 'uses' => 'GameController@destroy']);
        Route::post('/games/{game}/deactivate', ['as' => 'liteback.games.deactivate', 'uses' => 'GameController@deactivate']);
        Route::post('/games/{game}/activate', ['as' => 'liteback.games.activate', 'uses' => 'GameController@activate']);
        Route::post('/games/{game}/toggle-view', ['as' => 'liteback.games.toggle_view', 'uses' => 'GameController@toggleView']);
        Route::post('/games/{game}/update-params', ['as' => 'liteback.games.update_params', 'uses' => 'GameController@updateParams']);
        Route::post('/games/bulk-provider-toggle', ['as' => 'liteback.games.bulk_provider_toggle', 'uses' => 'GameController@bulkProviderToggle']);
        Route::post('/games/bulk-action', ['as' => 'liteback.games.bulk_action', 'uses' => 'GameController@bulkAction']);
        Route::post('/games/bulk-delivery', ['as' => 'liteback.games.bulk_delivery', 'uses' => 'GameController@bulkDelivery']);
        Route::post('/games/{game}/update-source', ['as' => 'liteback.games.update_source', 'uses' => 'GameController@updateSource']);
        Route::post('/games/manual', ['as' => 'liteback.games.store_manual', 'uses' => 'GameController@storeManualGame']);
        Route::post('/games/cedar-import', ['as' => 'liteback.games.import_cedar', 'uses' => 'GameController@importCedarGames']);
        Route::post('/games/legacy-plugin', ['as' => 'liteback.games.legacy_plugin', 'uses' => 'GameController@toggleLegacyPlugin']);
        Route::post('/games/legacy-import', ['as' => 'liteback.games.import_legacy', 'uses' => 'GameController@importLegacyGames']);
        Route::post('/games/{game}/delivery', ['as' => 'liteback.games.delivery', 'uses' => 'GameController@updateDelivery']);
        Route::get('/profile/password', ['as' => 'liteback.profile.password', 'uses' => 'ProfileController@editPassword']);
        Route::post('/profile/password', ['as' => 'liteback.profile.password.update', 'uses' => 'ProfileController@updatePassword']);
        Route::post('/profile/phone/send', ['as' => 'liteback.profile.phone.send', 'uses' => 'ProfileController@sendPhoneCode'])->middleware('throttle:5,1');
        Route::post('/profile/phone/verify', ['as' => 'liteback.profile.phone.verify', 'uses' => 'ProfileController@verifyPhoneCode'])->middleware('throttle:10,1');

        // Sportsbook admin routes
        Route::prefix('sports')->group(function () {
            Route::get('/', ['as' => 'liteback.sports.dashboard', 'uses' => 'SportsDashboardController@index']);
            Route::post('/commands', ['as' => 'liteback.sports.commands.run', 'uses' => 'SportsDashboardController@runCommand']);
            Route::post('/odds/clear-active', ['as' => 'liteback.sports.odds.clear_active', 'uses' => 'SportsDashboardController@clearActiveOdds']);

            Route::get('/categories', ['as' => 'liteback.sports.categories', 'uses' => 'SportsControlController@categories']);
            Route::post('/categories', ['as' => 'liteback.sports.categories.store', 'uses' => 'SportsControlController@storeCategory']);
            Route::post('/categories/{category}/toggle', ['as' => 'liteback.sports.categories.toggle', 'uses' => 'SportsControlController@toggleCategory']);

            Route::post('/leagues/{league}/toggle', ['as' => 'liteback.sports.leagues.toggle', 'uses' => 'SportsControlController@toggleLeague']);
            Route::post('/leagues/{league}/toggle-api', ['as' => 'liteback.sports.leagues.toggle-api', 'uses' => 'SportsControlController@toggleLeagueApi']);

            Route::get('/games', ['as' => 'liteback.sports.games', 'uses' => 'SportsControlController@games']);
            Route::post('/games', ['as' => 'liteback.sports.games.store', 'uses' => 'SportsControlController@storeGame']);
            Route::post('/games/{game}/toggle', ['as' => 'liteback.sports.games.toggle', 'uses' => 'SportsControlController@toggleGame']);

            Route::get('/settlements', ['as' => 'liteback.sports.settlements', 'uses' => 'SportsSettlementController@index']);
            Route::post('/settlements/{outcome}/settle', ['as' => 'liteback.sports.settlements.settle', 'uses' => 'SportsSettlementController@settle']);
            Route::post('/settlements/{bet}/refund', ['as' => 'liteback.sports.settlements.refund', 'uses' => 'SportsSettlementController@refundBet']);

            Route::get('/settings', ['as' => 'liteback.sports.settings', 'uses' => 'SportsControlController@settings']);
            Route::post('/settings', ['as' => 'liteback.sports.settings.update', 'uses' => 'SportsControlController@updateSettings']);
            Route::get('/providers', ['as' => 'liteback.sports.providers', 'uses' => 'SportsProviderController@index']);
            Route::post('/providers/select', ['as' => 'liteback.sports.providers.select', 'uses' => 'SportsProviderController@select']);
            Route::post('/providers/test', ['as' => 'liteback.sports.providers.test', 'uses' => 'SportsProviderController@test']);
        });

        // Casino game provider (seamless wallet) admin routes
        Route::prefix('casino')->group(function () {
            Route::get('/providers', ['as' => 'liteback.casino.providers', 'uses' => 'CasinoProviderController@index']);
            Route::post('/providers/update', ['as' => 'liteback.casino.providers.update', 'uses' => 'CasinoProviderController@update']);
            Route::post('/providers/toggle', ['as' => 'liteback.casino.providers.toggle', 'uses' => 'CasinoProviderController@toggle']);
            Route::post('/providers/test', ['as' => 'liteback.casino.providers.test', 'uses' => 'CasinoProviderController@test']);
            Route::get('/transactions', ['as' => 'liteback.casino.transactions', 'uses' => 'CasinoProviderController@transactions']);
        });

        // Payments Admin routes
        Route::prefix('payments')->group(function () {
            Route::get('/manual', ['as' => 'liteback.payments.manual.index', 'uses' => 'ManualDepositsController@index']);
            Route::post('/manual/{deposit}/approve', ['as' => 'liteback.payments.manual.approve', 'uses' => 'ManualDepositsController@approve']);
            Route::post('/manual/{deposit}/reject', ['as' => 'liteback.payments.manual.reject', 'uses' => 'ManualDepositsController@reject']);

            Route::get('/settings', ['as' => 'liteback.payments.settings', 'uses' => 'PaymentSettingsController@index']);
            Route::post('/settings', ['as' => 'liteback.payments.settings.update', 'uses' => 'PaymentSettingsController@update']);
        });

        // Player Cashout & Withdrawals Admin routes
        Route::prefix('withdrawals')->group(function () {
            Route::get('/', ['as' => 'liteback.withdrawals.index', 'uses' => 'WithdrawalController@index']);
            Route::post('/{id}/approve', ['as' => 'liteback.withdrawals.approve', 'uses' => 'WithdrawalController@approve']);
            Route::post('/{id}/reject', ['as' => 'liteback.withdrawals.reject', 'uses' => 'WithdrawalController@reject']);
        });

        // Lotto Admin routes
        Route::prefix('lotto')->group(function () {
            Route::get('/', ['as' => 'liteback.lotto.index', 'uses' => 'LottoController@index']);
            Route::post('/', ['as' => 'liteback.lotto.store', 'uses' => 'LottoController@store']);
            Route::post('/{game}/toggle', ['as' => 'liteback.lotto.toggle', 'uses' => 'LottoController@toggle']);
            Route::post('/{game}/draw', ['as' => 'liteback.lotto.draw', 'uses' => 'LottoController@draw']);
            Route::post('/{game}/result-columns', ['as' => 'liteback.lotto.result_columns', 'uses' => 'LottoController@updateResultColumns']);
        });

        // Prediction Markets Admin routes
        Route::prefix('predictions')->group(function () {
            Route::get('/', ['as' => 'liteback.predictions.index', 'uses' => 'PredictionController@index']);
            Route::post('/', ['as' => 'liteback.predictions.store', 'uses' => 'PredictionController@store']);
            Route::post('/{id}/settle', ['as' => 'liteback.predictions.settle', 'uses' => 'PredictionController@settle']);
        });

        // Crypto Trading Simulator operator controls
        Route::prefix('crypto')->group(function () {
            Route::get('/', ['as' => 'liteback.crypto.index', 'uses' => 'CryptoTradingController@index']);
            Route::post('/refresh', ['as' => 'liteback.crypto.refresh', 'uses' => 'CryptoTradingController@refresh']);
            Route::post('/assets/{asset}/toggle', ['as' => 'liteback.crypto.assets.toggle', 'uses' => 'CryptoTradingController@toggle']);
        });
        Route::prefix('stocks')->group(function () {
            Route::get('/', ['as' => 'liteback.stocks.index', 'uses' => 'StockTradingController@index']);
            Route::post('/refresh', ['as' => 'liteback.stocks.refresh', 'uses' => 'StockTradingController@refresh']);
            Route::post('/assets/{asset}/toggle', ['as' => 'liteback.stocks.assets.toggle', 'uses' => 'StockTradingController@toggle']);
        });

        // Multi-Tier Affiliate Admin routes
        Route::prefix('affiliates')->group(function () {
            Route::get('/', ['as' => 'liteback.affiliates.index', 'uses' => 'AffiliateController@index']);
            Route::post('/settings', ['as' => 'liteback.affiliates.settings', 'uses' => 'AffiliateController@updateRates']);
        });

        // VIP Club & Loyalty Rakeback Admin routes
        Route::prefix('vip')->group(function () {
            Route::get('/', ['as' => 'liteback.vip.index', 'uses' => 'VipController@index']);
            Route::post('/settings', ['as' => 'liteback.vip.settings', 'uses' => 'VipController@updateSettings']);
            Route::post('/users/{user}/tier', ['as' => 'liteback.vip.users.tier', 'uses' => 'VipController@setUserTier']);
        });

        // System Settings & Global API Controls
        Route::prefix('settings')->group(function () {
            Route::get('/', ['as' => 'liteback.settings.index', 'uses' => 'SystemSettingsController@index']);
            Route::post('/', ['as' => 'liteback.settings.update', 'uses' => 'SystemSettingsController@update']);
            Route::post('/clear-cache', ['as' => 'liteback.settings.clear_cache', 'uses' => 'SystemSettingsController@clearCache']);
            Route::post('/test-odds-api', ['as' => 'liteback.settings.test_odds_api', 'uses' => 'SystemSettingsController@testOddsApi']);
            Route::post('/test-crypto-prices-api', ['as' => 'liteback.settings.test_crypto_prices_api', 'uses' => 'SystemSettingsController@testCryptoPricesApi']);
            Route::post('/test-polymarket-api', ['as' => 'liteback.settings.test_polymarket_api', 'uses' => 'SystemSettingsController@testPolymarketApi']);
            Route::post('/sync-odds-now', ['as' => 'liteback.settings.sync_odds_now', 'uses' => 'SystemSettingsController@syncOddsNow']);
            Route::post('/settle-matches-now', ['as' => 'liteback.settings.settle_matches_now', 'uses' => 'SystemSettingsController@runSettlementNow']);
            Route::post('/draw-lotto-now', ['as' => 'liteback.settings.draw_lotto_now', 'uses' => 'SystemSettingsController@drawLottoNow']);
        });

        // Store, Add-ons & License Hub
        Route::prefix('store')->group(function () {
            Route::get('/', ['as' => 'liteback.store.index', 'uses' => 'StoreController@index']);
            Route::post('/license', ['as' => 'liteback.store.update_license', 'uses' => 'StoreController@updateLicense']);
            Route::post('/refresh', ['as' => 'liteback.store.refresh_license', 'uses' => 'StoreController@refreshLicense']);
            Route::post('/archives-drive-access', ['as' => 'liteback.store.archives_drive_access', 'uses' => 'StoreController@requestArchivesDriveAccess']);
            Route::post('/install', ['as' => 'liteback.store.install_pack', 'uses' => 'StoreController@installPack']);
            Route::post('/apply-update', ['as' => 'liteback.store.apply_update', 'uses' => 'StoreController@applyUpdate']);
        });
        Route::prefix('maintenance')->group(function () {
            Route::get('/', ['as' => 'liteback.maintenance.index', 'uses' => 'MaintenanceController@index']);
            Route::post('/backup', ['as' => 'liteback.maintenance.backup', 'uses' => 'MaintenanceController@backup']);
            Route::get('/backup/{name}', ['as' => 'liteback.maintenance.backup.download', 'uses' => 'MaintenanceController@downloadBackup']);
            Route::post('/fetch', ['as' => 'liteback.maintenance.fetch', 'uses' => 'MaintenanceController@fetch']);
            Route::post('/install', ['as' => 'liteback.maintenance.install', 'uses' => 'MaintenanceController@install']);
            Route::get('/package', ['as' => 'liteback.maintenance.package', 'uses' => 'MaintenanceController@package']);
            Route::post('/verify-legacy', ['as' => 'liteback.maintenance.verify-legacy', 'uses' => 'MaintenanceController@verifyLegacy']);
        });
    });
