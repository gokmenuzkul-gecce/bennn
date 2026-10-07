<?php

namespace VanguardLTE\Casino\SoftAggregator;

use VanguardLTE\Casino\Waija\WaijaClient;

/**
 * SoftAggregator aggregator client.
 *
 * SoftAggregator speaks the exact same operator protocol as Waija/Slotsgateway:
 * one POST endpoint, api_login/api_password plus a "method" (getGameList,
 * createPlayer, getGame, getGameDemo, ...), a {error, response|message} reply
 * where error 0 is success, and md5(timestamp + salt_key) signed GET callbacks
 * for the seamless wallet. The only differences are the config block, the
 * callback path and the base URL:
 *
 *   Base URL : https://api.softaggregator.com/api/v1
 *   Docs     : https://softaggregator.com/docs.html
 *
 * It fronts 40,000+ slots, live dealer and crash games from 200+ studios and
 * provisions TRY alongside the usual fiat currencies.
 */
class SoftAggregatorClient extends WaijaClient
{
    protected function label(): string
    {
        return 'softaggregator';
    }

    protected function configKey(): string
    {
        return 'softaggregator';
    }

    protected function defaultSettingsKey(): string
    {
        return 'casino_provider_softaggregator';
    }

    protected function defaultCallbackPath(): string
    {
        return '/webhooks/softaggregator/callbacks';
    }
}
