<?php

namespace VanguardLTE\Sports\Providers\Contracts;

/**
 * Contract for every sportsbook odds source.
 *
 * Implementations translate a vendor-specific feed into one normalized fixture
 * shape so the sync service never branches on a provider name. Fixtures are
 * arrays with these keys:
 *
 *  - id            (string) vendor event identifier
 *  - sport_key     (string) vendor sport/league identifier
 *  - sport_title   (string) human readable sport name
 *  - home_team     (string)
 *  - away_team     (string)
 *  - commence_time (string) ISO-8601 start time
 *  - has_outrights (bool)
 *  - markets       (array) list of ['key' => string, 'last_update' => string, 'outcomes' => [['name','price','point']]]
 */
interface SportsOddsProvider
{
    /** Stable machine identifier persisted in settings (e.g. "promex"). */
    public function key(): string;

    /** Operator-facing label rendered in Liteback. */
    public function label(): string;

    /** Whether this provider depends on a PROMEX license entitlement. */
    public function requiresLicense(): bool;

    /** Whether the provider has everything it needs to run right now. */
    public function isConfigured(): bool;

    /**
     * Human readable configuration state for the admin panel.
     *
     * @return array{configured: bool, message: string}
     */
    public function configStatus(): array;

    /**
     * Verify connectivity without importing anything.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnectivity(): array;

    /**
     * Fetch normalized fixtures, optionally limited to specific sport keys.
     *
     * @param  array<int, string>|null  $sportKeys
     * @return array<int, array<string, mixed>>
     */
    public function fetchFixtures(?array $sportKeys = null): array;

    /**
     * Discover the sport/league catalog available from this provider.
     *
     * @return array<int, array{key: string, title: string}>
     */
    public function fetchSports(): array;
}
