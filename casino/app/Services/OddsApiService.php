<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\Log;
use VanguardLTE\Sports\Providers\Contracts\SportsOddsProvider;
use VanguardLTE\Sports\Providers\SportsProviderRegistry;
use VanguardLTE\SportsMatch;

/**
 * Thin Battle Odds importer.
 *
 * All provider-specific behaviour (PROMEX Hub vs. a direct The Odds API key)
 * lives behind {@see SportsOddsProvider}; this class only persists the
 * normalized fixtures into the legacy Battle Odds table.
 */
class OddsApiService
{
    protected SportsProviderRegistry $providers;

    public function __construct(?SportsProviderRegistry $providers = null)
    {
        $this->providers = $providers ?? app(SportsProviderRegistry::class);
    }

    /**
     * Import upcoming pre-match fixtures for Battle Odds from the active provider.
     */
    public function syncUpcomingFixtures(): array
    {
        $provider = $this->providers->selected();

        if (!$provider->isConfigured()) {
            return $this->failedResult($provider->configStatus()['message']);
        }

        try {
            $fixtures = $provider->fetchFixtures();
        } catch (\Throwable $e) {
            Log::warning("[OddsApiService] {$provider->key()} feed unavailable: " . $e->getMessage());

            return $this->failedResult($e->getMessage());
        }

        $synced = 0;
        foreach ($fixtures as $fixture) {
            if ($this->saveFixture($fixture)) {
                $synced++;
            }
        }

        if ($synced > 0 && function_exists('settings')) {
            settings(['sports_last_sync' => now()->toDateTimeString()]);
        }

        return [
            'success' => true,
            'provider' => $provider->key(),
            'synced_count' => $synced,
            'synced_at' => now()->toDateTimeString(),
        ];
    }

    protected function failedResult(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'synced_count' => 0,
            'synced_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Persist one normalized fixture into the Battle Odds table.
     *
     * @param  array<string, mixed>  $fixture
     */
    protected function saveFixture(array $fixture): bool
    {
        $matchId = $fixture['id'] ?? null;
        if (!$matchId) {
            return false;
        }

        $homeTeam = $fixture['home_team'] ?? 'Home Team';
        $awayTeam = $fixture['away_team'] ?? 'Away Team';
        $sportKey = (string) ($fixture['sport_key'] ?? 'soccer_epl');
        $sportTitle = (string) ($fixture['sport_title'] ?? 'Match');
        $startTime = $fixture['commence_time'] ?? now()->addHours(2)->toIso8601String();

        [$oddsHome, $oddsDraw, $oddsAway] = $this->extractHeadToHead($fixture, $homeTeam, $awayTeam);

        SportsMatch::updateOrCreate(
            ['match_id' => $matchId],
            [
                'sport_key' => $sportKey,
                'sport_title' => $sportTitle,
                'home_team' => $homeTeam,
                'away_team' => $awayTeam,
                'start_time' => \Carbon\Carbon::parse($startTime)->setTimezone('UTC'),
                'odds_home' => $oddsHome,
                'odds_draw' => str_contains($sportKey, 'soccer') ? $oddsDraw : null,
                'odds_away' => $oddsAway,
                'status' => 'upcoming',
            ]
        );

        return true;
    }

    /**
     * Read decimal head-to-head prices from a normalized fixture.
     *
     * @param  array<string, mixed>  $fixture
     * @return array{0: float, 1: float|null, 2: float}
     */
    protected function extractHeadToHead(array $fixture, string $homeTeam, string $awayTeam): array
    {
        $oddsHome = 1.90;
        $oddsDraw = 3.20;
        $oddsAway = 1.90;

        $market = collect($fixture['markets'] ?? [])
            ->map(static fn ($market) => (object) $market)
            ->firstWhere('key', 'h2h');

        if ($market === null) {
            return [$oddsHome, $oddsDraw, $oddsAway];
        }

        foreach ($market->outcomes ?? [] as $outcome) {
            $outcome = (object) $outcome;
            $name = (string) ($outcome->name ?? '');
            $price = (float) ($outcome->price ?? 0);

            if ($name === $homeTeam) {
                $oddsHome = $price;
            } elseif ($name === $awayTeam) {
                $oddsAway = $price;
            } elseif (strtolower($name) === 'draw') {
                $oddsDraw = $price;
            }
        }

        return [$oddsHome, $oddsDraw, $oddsAway];
    }
}
