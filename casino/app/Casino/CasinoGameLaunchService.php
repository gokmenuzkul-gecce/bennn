<?php

namespace VanguardLTE\Casino;

use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;
use VanguardLTE\Game;
use VanguardLTE\User;

/**
 * Launches an aggregator slot for a signed-in player.
 *
 * The vendor tracks the player by the provider-specific user code we hand it, so
 * the launch URL carries that code and the wallet callbacks come back to the
 * shared webhook endpoint with the same identifier.
 */
class CasinoGameLaunchService
{
    public function __construct(private readonly CasinoProviderRegistry $registry)
    {
    }

    public function isCasinoGame(Game $game): bool
    {
        return !empty($game->provider_key) && $this->registry->has((string) $game->provider_key);
    }

    /**
     * Whether the vendor session can live inside an in-site iframe.
     *
     * Amatic serves its launcher with "X-Frame-Options: SAMEORIGIN", so it must
     * open in its own tab; the others render fine embedded.
     */
    public function canEmbed(string $providerKey): bool
    {
        return (bool) (config('casino_providers.providers.' . $providerKey . '.embeddable') ?? true);
    }

    /**
     * @return array{url: string, provider: string, user_code: string, form?: array{action: string, fields: array<string, string>}}
     */
    public function launch(Game $game, User $user, string $lang = 'tr'): array
    {
        $providerKey = (string) $game->provider_key;
        $provider = $this->registry->make($providerKey);

        if (!$provider->isConfigured()) {
            throw new \RuntimeException(trans('app.casino_provider_not_configured') . ' (' . $provider->label() . ')');
        }

        if (!$this->registry->isEnabled($providerKey)) {
            throw new \RuntimeException(trans('app.casino_provider_disabled') . ' (' . $provider->label() . ')');
        }

        $prefix = (string) config('casino_providers.user_prefix', 'u');
        $userCode = CasinoProviderPlayer::codeFor((int) $user->id, $providerKey, $prefix);

        // The vendor launches by its own numeric game id; provider_game_id (the
        // symbol) is only a fallback for rows synced before launch_code existed.
        $gameId = (string) ($game->launch_code ?: $game->provider_game_id ?: $game->name);

        $payload = $provider->embeddedLaunchPayload($userCode, $gameId, $lang);

        return array_merge($payload, [
            'provider' => $providerKey,
            'user_code' => $userCode,
        ]);
    }
}
