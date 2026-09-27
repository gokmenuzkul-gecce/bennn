<?php

namespace VanguardLTE\Services;

use RuntimeException;

final class PromexGameDeliveryService
{
    public const LOCAL = 'LOCAL';
    public const REMOTE = 'PROMEX_REMOTE';

    public function __construct(private readonly ?PromexCedarCatalogService $catalog = null) {}

    public function catalogById(): array
    {
        $payload = ($this->catalog ?? new PromexCedarCatalogService())->catalog();
        $games = [];
        foreach ($payload['games'] as $game) $games[$game['id']] = $game;
        return $games;
    }

    public function assertSelectable(object $game, string $mode, ?array $catalog = null): void
    {
        if (!in_array($mode, [self::LOCAL, self::REMOTE], true)) throw new RuntimeException('Invalid game delivery mode.');
        if (($game->source_type ?? '') === LegacyCompatibilityService::SOURCE_TYPE) {
            if (empty($game->legacy_rights_attested_at)) {
                throw new RuntimeException('Legacy game rights have not been attested.');
            }
            if ($mode === self::REMOTE && !LicenseService::canUseCdnGames()) {
                throw new RuntimeException('An active Promex CDN game entitlement is required.');
            }
            return;
        }
        if (($game->source_type ?? '') !== CedarGameRegistry::SOURCE_TYPE) {
            if ($mode === self::REMOTE) throw new RuntimeException('Only Promex Cedar games can use Promex Remote delivery.');
            return;
        }
        if ($mode === self::LOCAL && !CedarGameRegistry::supportsLocalMath((string) $game->name)) {
            throw new RuntimeException('This protected Cedar game has no local math package.');
        }
        if ($mode === self::REMOTE) {
            $catalog ??= $this->catalogById();
            if (!isset($catalog[$game->name])) throw new RuntimeException('This installation is not entitled to the remote game.');
        }
    }

    public function effectivelyAvailable(object $game, array $catalog): bool
    {
        if ((int) ($game->view ?? 0) !== 1) return false;
        if (($game->delivery_mode ?? self::LOCAL) !== self::REMOTE) return true;
        if (($game->source_type ?? '') === LegacyCompatibilityService::SOURCE_TYPE) {
            return !empty($game->legacy_rights_attested_at) && LicenseService::canUseCdnGames();
        }
        return ($game->source_type ?? '') === CedarGameRegistry::SOURCE_TYPE && isset($catalog[$game->name]);
    }
}
