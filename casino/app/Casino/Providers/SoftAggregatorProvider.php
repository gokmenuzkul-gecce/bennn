<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\SoftAggregator\SoftAggregatorClient;

/**
 * SoftAggregator adapter.
 *
 * SoftAggregator is a single-API aggregator (40,000+ slots, live dealer, crash
 * and table games from 200+ studios) that shares Waija's operator protocol, so
 * the launch flow is identical: createPlayer followed by getGame, with the
 * catalogue keyed by id_hash. It is registered so the lobby launches its games
 * through the same CasinoGameLaunchService and the catalogue syncs in.
 */
class SoftAggregatorProvider extends AbstractCasinoProvider
{
    public const KEY = 'softaggregator';

    private ?SoftAggregatorClient $client = null;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'SoftAggregator';
    }

    public function client(): SoftAggregatorClient
    {
        return $this->client ??= new SoftAggregatorClient();
    }

    /** Surface the base URL under the generic "endpoint" key for Liteback. */
    public function config(): array
    {
        $config = $this->client()->config();
        $config['endpoint'] = (string) ($config['base_url'] ?? '');

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
     * SoftAggregator signs callbacks with md5(timestamp + saltkey) (handled by
     * the dedicated SoftAggregatorWebhookController), so the inherited
     * HMAC-SHA256 signer is unused here.
     */
    public function sign(array $params, array $order): string
    {
        return '';
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
        return (string) ($this->config()['embed_aspect'] ?? 'auto');
    }

    public function launchUrl(string $userCode, string $gameId, string $lang = 'tr'): string
    {
        // createPlayer is idempotent and must precede any real-money session.
        $this->client()->createPlayer($userCode, $this->playerPassword(), $this->playerNickname());

        return $this->client()->launch($userCode, $this->playerPassword(), $gameId, ['lang' => $lang]);
    }

    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array
    {
        return ['url' => $this->launchUrl($userCode, $gameId, $lang)];
    }

    /** Display nickname shown in the SoftAggregator backoffice; optional. */
    private function playerNickname(): ?string
    {
        $configured = (string) ($this->config()['player_nickname'] ?? '');

        return $configured !== '' ? $configured : null;
    }

    /**
     * Fun-play launch, no player and no wallet involved.
     *
     * @return array{url: string, session_id: string}
     */
    public function demoLaunch(string $gameId, string $lang = 'tr'): array
    {
        return $this->client()->demo($gameId, $lang);
    }

    /**
     * Password SoftAggregator stores for the player. It is never shown to the
     * player (the session is opened server-side), but it must be stable so a
     * relaunch does not invalidate an in-flight session.
     */
    private function playerPassword(): string
    {
        $configured = (string) ($this->config()['player_password'] ?? '');
        if ($configured !== '') {
            return $configured;
        }

        return substr(hash('sha256', 'softaggregator|' . config('app.key')), 0, 24);
    }
}
