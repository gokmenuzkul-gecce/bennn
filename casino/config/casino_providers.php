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

    ],

];
