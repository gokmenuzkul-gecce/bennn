<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\Aggregator01\Aggregator01Client;
use VanguardLTE\Casino\Providers\Contracts\CasinoProvider;

/**
 * 01.tech Aggregator (A8R) adapter.
 *
 * A8R is a multi-provider aggregator (slots + live tables) behind one credential
 * set and does not share the legacy ordered-fields HMAC protocol: it speaks Twirp
 * v7 with a raw-body HMAC-SHA256 signature, and a session is minted by
 * casino_a8r.Launcher/Real. It is registered so the lobby launches its games
 * through CasinoGameLaunchService and the catalogue syncs in.
 *
 * The launch id stored on the game row is A8R's game id ("id" in the gamelist,
 * e.g. "PlatinumLightning"), combined with the provider as "<provider>:<id>" on
 * the wire. The player_id we hand it is the provider-specific user code.
 */
class Aggregator01Provider extends AbstractCasinoProvider
{
    public const KEY = 'aggregator01';

    private ?Aggregator01Client $client = null;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return '01.tech Aggregator';
    }

    public function client(): Aggregator01Client
    {
        return $this->client ??= new Aggregator01Client();
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
     * A8R signs the raw JSON body, not ordered fields, so the inherited signer is
     * unused; the dedicated Aggregator01WebhookController verifies callbacks.
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
        return (string) ($this->config()['embed_aspect'] ?? 'auto');
    }

    public function launchUrl(string $userCode, string $gameId, string $lang = 'tr'): string
    {
        return $this->client()->launchReal($gameId, $userCode, [], ['locale' => $lang]);
    }

    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array
    {
        return ['url' => $this->launchUrl($userCode, $gameId, $lang)];
    }

    /**
     * Fun-play launch, no player and no wallet involved.
     *
     * @return array{url: string}
     */
    public function demoLaunch(string $gameId, string $lang = 'tr'): array
    {
        return ['url' => $this->client()->launchDemo($gameId, ['locale' => $lang])];
    }
}
