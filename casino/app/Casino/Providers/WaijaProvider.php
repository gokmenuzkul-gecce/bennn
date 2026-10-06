<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Waija\WaijaClient;

/**
 * Waija (Slotsgateway) aggregator adapter.
 *
 * Waija fronts 150-250+ vendors (slots + live tables) behind one credential set
 * and does not share the legacy HMAC-SHA256 launch protocol: outbound calls are
 * JSON POSTs carrying api_login/api_password, and a session is minted by
 * createPlayer followed by getGame. It is registered so the lobby launches its
 * games through the same CasinoGameLaunchService and the catalogue syncs in.
 *
 * The launch id stored on the game row is Waija's id_hash (e.g.
 * "softswiss/WildChicago").
 */
class WaijaProvider extends AbstractCasinoProvider
{
    public const KEY = 'waija';

    private ?WaijaClient $client = null;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Waija';
    }

    public function client(): WaijaClient
    {
        return $this->client ??= new WaijaClient();
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
     * Waija signs callbacks with md5(timestamp + saltkey) (handled by the
     * dedicated WaijaWebhookController), so the inherited HMAC-SHA256 signer is
     * unused here.
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

    /** Display nickname shown in the Waija backoffice; optional. */
    private function playerNickname(): ?string
    {
        $configured = (string) ($this->config()['player_nickname'] ?? '');

        return $configured !== '' ? $configured : null;
    }

    /**
     * Password Waija stores for the player. It is never shown to the player (the
     * session is opened server-side), but it must be stable so a relaunch does
     * not invalidate an in-flight session.
     */
    private function playerPassword(): string
    {
        $configured = (string) ($this->config()['player_password'] ?? '');
        if ($configured !== '') {
            return $configured;
        }

        return substr(hash('sha256', 'waija|' . config('app.key')), 0, 24);
    }
}
