<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\SmplCore\SmplCoreClient;

/**
 * smpl core aggregator adapter.
 *
 * smpl core fronts thousands of vendors (slots + live casino) behind one
 * merchant credential set. It does not share the legacy HMAC-SHA256 launch
 * protocol: requests are signed with HMAC-SHA1 (X-Sign header) and sessions are
 * minted by POST /games/init. It is registered so the lobby can launch its games
 * through the same CasinoGameLaunchService and so the catalogue syncs into the
 * lobby.
 *
 * The launch id stored on the game row is the smpl core game UUID.
 */
class SmplCoreProvider extends AbstractCasinoProvider
{
    public const KEY = 'smplcore';

    private ?SmplCoreClient $client = null;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'smpl core';
    }

    public function client(): SmplCoreClient
    {
        return $this->client ??= new SmplCoreClient();
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
     * smpl core signs callbacks with HMAC-SHA1 over the payload (handled by the
     * dedicated SmplCoreWebhookController), so the inherited HMAC-SHA256 signer
     * is unused here.
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
        return $this->client()->init($userCode, $gameId, ['language' => $lang]);
    }

    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array
    {
        return ['url' => $this->launchUrl($userCode, $gameId, $lang)];
    }
}
