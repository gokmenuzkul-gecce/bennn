<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\OroPlay\OroPlayClient;
use VanguardLTE\Casino\Providers\Contracts\CasinoProvider;

/**
 * OroPlay is an aggregator, not a single brand, so it does not share the legacy
 * HMAC launch/callback protocol. It is registered here so it appears in the
 * Liteback provider catalogue and so the lobby can launch its games through the
 * same CasinoGameLaunchService.
 *
 * Launch ids are encoded as "<vendorCode>:<gameCode>" because OroPlay needs both
 * to mint a session URL; the colon is stripped by launchUrl().
 */
class OroPlayProvider extends AbstractCasinoProvider
{
    public const KEY = 'oroplay';

    private ?OroPlayClient $client = null;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'OroPlay';
    }

    public function client(): OroPlayClient
    {
        return $this->client ??= new OroPlayClient();
    }

    /**
     * Surface the aggregator base URL under the generic "endpoint" key so the
     * Liteback catalogue and its edit form render it like every other brand.
     */
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

    /** OroPlay registers three operator endpoints; the admin shows the base. */
    public function callbackUrl(): string
    {
        return $this->client()->callbackUrl();
    }

    public function configStatus(): array
    {
        return $this->client()->configStatus();
    }

    /**
     * OroPlay does not sign wallet callbacks with HMAC; it uses HTTP Basic. The
     * inherited HMAC signer is therefore unused, and verify() checks the Basic
     * credential instead.
     */
    public function sign(array $params, array $order): string
    {
        return $this->client()->basicAuthHeader();
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
        [$vendorCode, $gameCode] = $this->splitGameId($gameId);

        return $this->client()->launchUrl($vendorCode, $gameCode, $userCode, $lang);
    }

    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array
    {
        return ['url' => $this->launchUrl($userCode, $gameId, $lang)];
    }

    /**
     * Split "<vendorCode>:<gameCode>" back into its parts. Rows synced before
     * the encoding existed fall back to a bare game code with no vendor.
     *
     * @return array{0: string, 1: string}
     */
    private function splitGameId(string $gameId): array
    {
        if (str_contains($gameId, ':')) {
            return explode(':', $gameId, 2);
        }

        return ['', $gameId];
    }
}
