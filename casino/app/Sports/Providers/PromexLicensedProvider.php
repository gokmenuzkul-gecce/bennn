<?php

namespace VanguardLTE\Sports\Providers;

use Illuminate\Support\Facades\Http;
use VanguardLTE\Services\LicenseService;
use VanguardLTE\Services\PromexInstallationService;
use VanguardLTE\Sports\Providers\Contracts\SportsOddsProvider;

/**
 * The PROMEX Service Hub pre-match feed.
 *
 * Requests are authenticated with the domain-bound installation signature, so
 * no upstream provider key ever lives in the customer application.
 */
class PromexLicensedProvider implements SportsOddsProvider
{
    public const KEY = 'promex';

    private const ODDS_PATH = '/api/service/sports/odds';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'PROMEX Licensed API — included with an active license';
    }

    public function requiresLicense(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return LicenseService::canUseCentralOdds();
    }

    public function configStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'message' => 'Requires an active license that includes sportsbook access.',
            ];
        }

        return [
            'configured' => true,
            'message' => 'Licensed PRE-MATCH feed is available for this installation.',
        ];
    }

    public function testConnectivity(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'PROMEX Sports API requires an active license that includes sportsbook access.',
            ];
        }

        try {
            $fixtures = $this->fetchFixtures();
            return [
                'success' => true,
                'message' => 'PROMEX Sports API is connected through this installation license ('
                    . count($fixtures) . ' PRE-MATCH odds available).',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function fetchFixtures(?array $sportKeys = null): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('PROMEX Sports API access requires an active sportsbook license.');
        }

        $path = self::ODDS_PATH;
        $hubUrl = rtrim((string) config('licensing.hub_url', LicenseService::DEFAULT_SERVER), '/');
        $response = Http::timeout(max(2, (int) config('licensing.hub_timeout', 8)))
            ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
            ->withHeaders(PromexInstallationService::signedHeaders('GET', $path))
            ->get($hubUrl . '/sports/odds');

        $payload = $response->json();
        if (!$response->successful() || !is_array($payload) || !is_array($payload['fixtures'] ?? null)) {
            $message = is_array($payload) && is_string($payload['message'] ?? null)
                ? $payload['message'] : 'The licensed PROMEX pre-match feed is unavailable.';
            throw new \RuntimeException($message);
        }

        $fixtures = [];
        foreach ($payload['fixtures'] as $fixture) {
            $normalized = $this->normalizeFixture($fixture);
            if ($normalized !== null) {
                $fixtures[] = $normalized;
            }
        }

        return $fixtures;
    }

    public function fetchSports(): array
    {
        $sports = [];
        foreach ($this->fetchFixtures() as $fixture) {
            $key = (string) $fixture['sport_key'];
            $sports[$key] = [
                'key' => $key,
                'title' => (string) $fixture['sport_title'],
            ];
        }

        uasort($sports, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));

        return array_values($sports);
    }

    /**
     * @param  mixed  $fixture
     * @return array<string, mixed>|null
     */
    private function normalizeFixture($fixture): ?array
    {
        if (!is_array($fixture)) {
            return null;
        }

        $id = trim((string) ($fixture['id'] ?? ''));
        $sportKey = strtolower(trim((string) ($fixture['sport_key'] ?? '')));
        $home = trim((string) ($fixture['home_team'] ?? ''));
        $away = trim((string) ($fixture['away_team'] ?? ''));
        $commence = (string) ($fixture['commence_time'] ?? '');

        if ($id === '' || $sportKey === '' || $home === '' || $away === '' || $commence === '') {
            return null;
        }

        $markets = [];
        $latest = $this->latestMarket($fixture, 'h2h');
        if ($latest !== null) {
            $draw = collect($latest->outcomes ?? [])->contains(
                static fn ($outcome): bool => strtolower((string) (($outcome['name'] ?? $outcome->name ?? ''))) === 'draw'
            );
            if ($draw) {
                $latest->key = 'h2h_3way';
            }
            $markets[] = $latest;
        }

        return [
            'id' => $id,
            'sport_key' => $sportKey,
            'sport_title' => trim((string) ($fixture['sport_title'] ?? $sportKey)),
            'home_team' => $home,
            'away_team' => $away,
            'commence_time' => $commence,
            'has_outrights' => false,
            'markets' => $markets,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function latestMarket(array $fixture, string $marketKey): ?object
    {
        $market = collect($fixture['bookmakers'] ?? [])
            ->pluck('markets')->flatten(1)->where('key', $marketKey)
            ->sortByDesc('last_update')->first();

        if (is_array($market)) {
            return (object) $market;
        }

        return is_object($market) ? $market : null;
    }
}
