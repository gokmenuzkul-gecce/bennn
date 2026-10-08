<?php

/*
|--------------------------------------------------------------------------
| Casino game providers (aggregator / seamless wallet)
|--------------------------------------------------------------------------
|
| Every provider below speaks the same seamless-wallet protocol: the vendor
| calls back into our wallet endpoints (GetBalance, Withdraw, Deposit, BetWin,
| RollbackTransaction) and we call their gamelist / userAuth endpoints to
| launch games. Credentials live in .env; operators can override them per
| provider in Liteback (settings table) so they never need a deploy.
|
*/

return [

    // Base URL the vendor must call back. The registered callback in the
    // vendor portal is "<base>/webhooks/aggregator/<slug>/wallet".
    'callback_base' => env('CASINO_CALLBACK_BASE', rtrim(env('APP_URL', ''), '/')),

    // Slug segment in the aggregator callback URL.
    'callback_slug' => env('CASINO_CALLBACK_SLUG', 'gregmorn'),

    // Wallet currency is the site coin; amounts arrive with 2 decimals.
    'currency' => env('CASINO_WALLET_CURRENCY', 'TRY'),

    // Prefix used when deriving the vendor-facing userID from our user id.
    'user_prefix' => env('CASINO_WALLET_USER_PREFIX', 'u'),

    /*
    |--------------------------------------------------------------------------
    | smpl core aggregator (new API)
    |--------------------------------------------------------------------------
    |
    | smpl core replaces the legacy loginxgames aggregator. It speaks a
    | different protocol: HMAC-SHA1 header auth on our outbound calls and a
    | single webhook endpoint the aggregator posts balance/bet/win/refund/
    | rollback actions to. Credentials (Merchant ID + Merchant Key) are issued
    | by the smpl core integration manager.
    |
    */
    'smplcore' => [
        'base_url' => env('SMPL_BASE_URL', 'https://staging.smplcore.net/api/index.php/v1'),
        'merchant_id' => env('SMPL_MERCHANT_ID', ''),
        'merchant_key' => env('SMPL_MERCHANT_KEY', ''),
        'callback_path' => env('SMPL_CALLBACK_PATH', '/webhooks/smplcore/callbacks'),
        'currency' => env('SMPL_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY')),
        'settings_key' => 'casino_provider_smplcore',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gregmorn Hub aggregator (slots + live casino, multi-vendor)
    |--------------------------------------------------------------------------
    |
    | Gregmorn Hub (docs.gregmorn.org) fronts many vendors behind one credential
    | set and speaks its own protocol: POST /auth/login mints a short-lived JWT,
    | GET /users/{user_id}/getUserGames/{currency} lists the catalogue, and
    | POST /games/openGame returns a playable session URL. Wallet callbacks are
    | signed with X-Signature = hex HMAC-SHA256 over the raw JSON body, keyed by
    | the account secret. Credentials are issued per operator account; stage and
    | prod have separate logins, secrets and IP allowlists.
    |
    */
    'gregmorn' => [
        'office_base_url' => env('GREGMORN_OFFICE_BASE_URL', 'https://office-api-dev.gregmorn.org'),
        'client_base_url' => env('GREGMORN_CLIENT_BASE_URL', 'https://client-api-dev.gregmorn.org'),
        'login' => env('GREGMORN_LOGIN', ''),
        'password' => env('GREGMORN_PASSWORD', ''),
        'secret_key' => env('GREGMORN_SECRET_KEY', ''),
        'user_id' => env('GREGMORN_USER_ID', ''),
        'currency' => env('GREGMORN_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY')),
        'callback_path' => env('GREGMORN_CALLBACK_PATH', '/webhooks/gregmorn/callbacks'),
        'exit_url' => env('GREGMORN_EXIT_URL', rtrim(env('APP_URL', ''), '/')),
        'settings_key' => 'casino_provider_gregmorn',
    ],

    /*
    |--------------------------------------------------------------------------
    | OroPlay aggregator (Live Casino, Slot & Mini Game API v1.1.3)
    |--------------------------------------------------------------------------
    |
    | OroPlay speaks a third protocol: outbound calls authenticate with a bearer
    | token minted from clientId/clientSecret (POST /auth/createtoken), while the
    | operator-side seamless-wallet callbacks are authenticated with HTTP Basic
    | (base64 of clientId:clientSecret). One transaction endpoint receives every
    | bet/win (amount<0 = bet, amount>0 = win). Credentials are issued per agent
    | in the OroPlay agent page; the staging client id is prefixed "stg-TRY-".
    |
    */
    'oroplay' => [
        'base_url' => env('OROPLAY_BASE_URL', 'https://api.oroplay.com/api/v2'),
        'client_id' => env('OROPLAY_CLIENT_ID', ''),
        'client_secret' => env('OROPLAY_CLIENT_SECRET', ''),
        'callback_path' => env('OROPLAY_CALLBACK_PATH', '/webhooks/oroplay/api'),
        'currency' => env('OROPLAY_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY')),
        'lobby_url' => env('OROPLAY_LOBBY_URL', rtrim(env('APP_URL', ''), '/')),
        'settings_key' => 'casino_provider_oroplay',
    ],

    /*
    |--------------------------------------------------------------------------
    | Waija (Slotsgateway) aggregator — slots + live, 150-250+ vendors
    |--------------------------------------------------------------------------
    |
    | Waija (documentation.waija.com, formerly Slotsgateway) fronts many vendors
    | behind one credential set. Outbound calls are JSON POSTs to the account base
    | URL carrying api_login/api_password and a "method" (createPlayer,
    | getGameList, getGame). Seamless-wallet callbacks arrive as GETs to our
    | callback URL with action=balance|debit|credit and a key of
    | md5(timestamp + saltkey); the timestamp must be within 30 seconds. Balances
    | on the wire are integer cents.
    |
    */
    'waija' => [
        'base_url' => env('WAIJA_BASE_URL', ''),
        'api_login' => env('WAIJA_API_LOGIN', ''),
        'api_password' => env('WAIJA_API_PASSWORD', ''),
        'salt_key' => env('WAIJA_SALT_KEY', ''),
        'player_password' => env('WAIJA_PLAYER_PASSWORD', ''),
        'player_nickname' => env('WAIJA_PLAYER_NICKNAME', ''),
        // The reference SDK posts form-encoded bodies; keep 'form' unless the
        // account is configured for JSON.
        'request_format' => env('WAIJA_REQUEST_FORMAT', 'form'),
        'callback_path' => env('WAIJA_CALLBACK_PATH', '/webhooks/waija/callbacks'),
        'currency' => env('WAIJA_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY')),
        'signature_window' => (int) env('WAIJA_SIGNATURE_WINDOW', 30),
        'home_url' => env('WAIJA_HOME_URL', rtrim(env('APP_URL', ''), '/')),
        'cashier_url' => env('WAIJA_CASHIER_URL', rtrim(env('APP_URL', ''), '/')),
        // Optional operator brand tag echoed back in launch/demo payloads.
        'branded' => env('WAIJA_BRANDED', ''),
        'settings_key' => 'casino_provider_waija',
    ],

    /*
    |--------------------------------------------------------------------------
    | SoftAggregator — slots + live + crash aggregator, 40k+ games / 200+ studios
    |--------------------------------------------------------------------------
    |
    | SoftAggregator shares Waija's operator protocol (POST {api_login,
    | api_password, method}, {error, response} reply, md5(timestamp+salt_key)
    | wallet callbacks, id_hash launch ids). It differs in the base URL, the
    | callback path and the request encoding: SoftAggregator documents a JSON
    | body while Waija's reference SDK posts form-encoded.
    |
    |   Docs: https://softaggregator.com/docs.html
    |
    | Credentials are issued per site in the operator backend (API integration →
    | Your other sites): api_login, api_password, salt_key and a callback URL.
    */
    'softaggregator' => [
        'base_url' => env('SOFTAGGREGATOR_BASE_URL', 'https://api.softaggregator.com/api/v1'),
        'api_login' => env('SOFTAGGREGATOR_API_LOGIN', ''),
        'api_password' => env('SOFTAGGREGATOR_API_PASSWORD', ''),
        'salt_key' => env('SOFTAGGREGATOR_SALT_KEY', ''),
        'player_password' => env('SOFTAGGREGATOR_PLAYER_PASSWORD', ''),
        'player_nickname' => env('SOFTAGGREGATOR_PLAYER_NICKNAME', ''),
        'request_format' => env('SOFTAGGREGATOR_REQUEST_FORMAT', 'json'),
        'callback_path' => env('SOFTAGGREGATOR_CALLBACK_PATH', '/webhooks/softaggregator/callbacks'),
        'currency' => env('SOFTAGGREGATOR_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY')),
        'signature_window' => (int) env('SOFTAGGREGATOR_SIGNATURE_WINDOW', 30),
        'home_url' => env('SOFTAGGREGATOR_HOME_URL', rtrim(env('APP_URL', ''), '/')),
        'cashier_url' => env('SOFTAGGREGATOR_CASHIER_URL', rtrim(env('APP_URL', ''), '/')),
        // Some studios refuse a launch that omits device; country is enforced
        // for accounts serving a restricted market (ISO 3166 alpha-2).
        'device' => env('SOFTAGGREGATOR_DEVICE', 'desktop'),
        'country' => env('SOFTAGGREGATOR_COUNTRY', 'TR'),
        'branded' => env('SOFTAGGREGATOR_BRANDED', ''),
        'settings_key' => 'casino_provider_softaggregator',
    ],

    /*
    |--------------------------------------------------------------------------
    | 01.tech Aggregator (A8R) — Twirp v7, HMAC-SHA256 body signing
    |--------------------------------------------------------------------------
    |
    | 01.tech Aggregator (docs.aggregator.01.tech) fronts many game providers
    | behind one credential set and uses Twirp Wire Protocol v7: every call is
    | HTTP POST to "<base>/v2/<Service>/<Method>" with an application/json body
    | signed by hex HMAC-SHA256 over the exact raw body, keyed by AUTH_TOKEN and
    | sent in the "X-REQUEST-SIGN" header. The reply is JSON, and errors are
    | Twirp errors carrying meta.api_code.
    |
    | The wallet is seamless: the Aggregator posts Player/Balance, Round/BetWin,
    | Round/Rollback and Round/Finish to the callback URL, each signed the same
    | way. Amounts are decimal strings (18 integer / 12 fractional digits), not
    | cents, so they are processed with BCMath.
    |
    | casino_id is the tenant identifier ("gecce").
    */
    'aggregator01' => [
        // Base URL up to but excluding "/v2"; the client appends the method path.
        'base_url' => env('AGGREGATOR01_BASE_URL', ''),
        // HMAC-SHA256 signing key issued by the Aggregator (AUTH_TOKEN).
        'auth_token' => env('AGGREGATOR01_AUTH_TOKEN', ''),
        // Tenant identifier assigned by the Aggregator.
        'casino_id' => env('AGGREGATOR01_CASINO_ID', 'gecce'),
        'callback_path' => env('AGGREGATOR01_CALLBACK_PATH', '/webhooks/aggregator01/callbacks'),
        'currency' => env('AGGREGATOR01_CURRENCY', env('CASINO_WALLET_CURRENCY', 'TRY')),
        // Default player country/jurisdiction/locale sent on launch.
        'country' => env('AGGREGATOR01_COUNTRY', 'TR'),
        'jurisdiction' => env('AGGREGATOR01_JURISDICTION', 'TR'),
        'locale' => env('AGGREGATOR01_LOCALE', 'tr'),
        // Redirect targets for the launcher (deposit usually equals return).
        'return_url' => env('AGGREGATOR01_RETURN_URL', rtrim(env('APP_URL', ''), '/')),
        'deposit_url' => env('AGGREGATOR01_DEPOSIT_URL', rtrim(env('APP_URL', ''), '/')),
        // Fallbacks used when the local user row lacks an email / birth date.
        'default_email' => env('AGGREGATOR01_DEFAULT_EMAIL', 'player@casino.local'),
        'default_date_of_birth' => env('AGGREGATOR01_DEFAULT_DOB', '1990-01-01T00:00:00Z'),
        'settings_key' => 'casino_provider_aggregator01',
    ],

    'providers' => [

        'pragmatic' => [
            'label' => 'Pragmatic Play',
            'endpoint' => env('PRAGMATIC_ENDPOINT', 'apipk.lxgame.io'),
            'scheme' => 'https',
            'agent_id' => env('PRAGMATIC_AGENT_ID', ''),
            'api_token' => env('PRAGMATIC_API_TOKEN', ''),
            'secret_key' => env('PRAGMATIC_SECRET_KEY', ''),
            'settings_key' => 'casino_provider_pragmatic',
            'launch_path' => '/userauth',
            'gamelist_path' => '/gamelist',
        ],

        'pgsoft' => [
            'label' => 'PG Soft',
            'endpoint' => env('PGSOFT_ENDPOINT', 'ggapi.loginxgamesapi.com'),
            'scheme' => 'https',
            'agent_id' => env('PGSOFT_AGENT_ID', ''),
            'api_token' => env('PGSOFT_API_TOKEN', ''),
            'secret_key' => env('PGSOFT_SECRET_KEY', ''),
            'settings_key' => 'casino_provider_pgsoft',
            'launch_path' => '/userauth',
            'gamelist_path' => '/gamelist',
            // The aggregator answers /userauth with the phone build regardless
            // of caller, so the session is portrait and would render as a narrow
            // strip in the wide desktop frame. Size the iframe to 9:16 instead.
            'embed_aspect' => env('PGSOFT_EMBED_ASPECT', '9:16'),
        ],

        'amatic' => [
            'label' => 'Amatic',
            'endpoint' => env('AMATIC_ENDPOINT', 'apiam.lxgame.io'),
            'scheme' => 'https',
            'agent_id' => env('AMATIC_AGENT_ID', ''),
            'api_token' => env('AMATIC_API_TOKEN', ''),
            'secret_key' => env('AMATIC_SECRET_KEY', ''),
            'settings_key' => 'casino_provider_amatic',
            'launch_path' => '/userauth',
            'gamelist_path' => '/gamelist',
            // The vendor now serves the session without X-Frame-Options, so it
            // can live inside the in-site player alongside the other brands.
            'embeddable' => true,
        ],

        'amusnet' => [
            'label' => 'Amusnet',
            'endpoint' => env('AMUSNET_ENDPOINT', 'api.gitamus.net'),
            'scheme' => 'https',
            'agent_id' => env('AMUSNET_AGENT_ID', ''),
            'api_token' => env('AMUSNET_API_TOKEN', ''),
            'secret_key' => env('AMUSNET_SECRET_KEY', ''),
            'settings_key' => 'casino_provider_amusnet',
            'launch_path' => '/userauth',
            'gamelist_path' => '/gamelist',
        ],

        // OroPlay is an aggregator, not a single brand: one credential set
        // fronts many vendors. Its own client (OroPlayClient) speaks the token/
        // launch protocol; this entry only carries the label + admin toggles so
        // it appears in Liteback alongside the legacy brands.
        'oroplay' => [
            'label' => 'OroPlay',
            'settings_key' => 'casino_provider_oroplay',
            'embeddable' => true,
        ],

        // Waija is also an aggregator (Slotsgateway); WaijaProvider carries the
        // protocol, this entry only supplies the label + admin toggles.
        'waija' => [
            'label' => 'Waija',
            'settings_key' => 'casino_provider_waija',
            'embeddable' => true,
        ],

        // SoftAggregator shares Waija's protocol; SoftAggregatorProvider carries
        // it, this entry only supplies the label + admin toggles.
        'softaggregator' => [
            'label' => 'SoftAggregator',
            'settings_key' => 'casino_provider_softaggregator',
            'embeddable' => true,
        ],

        // 01.tech Aggregator (A8R) is an aggregator; Aggregator01Provider carries
        // its Twirp/HMAC protocol, this entry only supplies the label + toggles.
        'aggregator01' => [
            'label' => '01.tech Aggregator',
            'settings_key' => 'casino_provider_aggregator01',
            'embeddable' => true,
        ],

    ],

];
