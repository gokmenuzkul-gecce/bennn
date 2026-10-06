<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\Gregmorn\GregmornClient;
use VanguardLTE\Casino\Providers\Contracts\CasinoProvider;

/**
 * Gregmorn Hub is a multi-vendor aggregator (slots + live-casino tables), not a
 * single brand, so it does not share the legacy HMAC launch/callback protocol.
 * It is registered here so it appears in the Liteback provider catalogue and so
 * the lobby can launch its games through CasinoGameLaunchService.
 *
 * Launch ids are the Hub's own game ids (e.g. "integration_a:provider_a:game_001");
 * the player login we hand it is the provider-specific user code.
 */
class GregmornProvider extends AbstractCasinoProvider
{
    public const KEY = 'gregmorn';

    private ?GregmornClient $client = null;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Gregmorn Hub';
    }

    public function client(): GregmornClient
    {
        return $this->client ??= new GregmornClient();
    }

    /**
     * Surface the aggregator office base URL under the generic "endpoint" key so
     * the Liteback catalogue and its edit form render it like every other brand.
     */
    public function config(): array
    {
        $config = $this->client()->config();
        $config['endpoint'] = (string) ($config['office_base_url'] ?? '');

        return $config;
    }

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    public function callbackUrl(): string
    {
        return $this->client()->callbackUrl();
    }

    public function configStatus(): array
    {
        return $this->client()->configStatus();
    }

    /**
     * Gregmorn does not sign wallet callbacks with the legacy ordered-fields
     * HMAC; it signs the raw JSON body instead. The inherited signer is unused
     * and verify() always declines so the shared aggregator webhook never
     * claims a Gregmorn callback.
     */
    public function sign(array $params, array $order): string
    {
        return $this->client()->signBody((string) json_encode($params));
    }

    public function verify(array $payload, array $order): bool
    {
        return false;
    }

    public function signOrder(string $operation): array
    {
        return [];
    }

    public function testConnectivity(): array
    {
        return $this->client()->testConnectivity();
    }

    public function embedAspect(): string
    {
        return 'auto';
    }

    public function launchUrl(string $userCode, string $gameId, string $lang = 'tr'): string
    {
        return $this->client()->openGame($userCode, $gameId, ['language' => $lang]);
    }

    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array
    {
        return ['url' => $this->launchUrl($userCode, $gameId, $lang)];
    }
}
