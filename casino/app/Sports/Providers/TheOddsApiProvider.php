<?php

namespace VanguardLTE\Sports\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VanguardLTE\Sports\Providers\Contracts\SportsOddsProvider;

/**
 * Direct integration with The Odds API v4 using the operator's own key.
 *
 * The key stays in the customer database/env; no PROMEX entitlement is needed.
 */
class TheOddsApiProvider implements SportsOddsProvider
{
    public const KEY = 'custom';

    private const BASE_URI = 'https://api.the-odds-api.com/v4/';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'My own The Odds API key';
    }

    public function requiresLicense(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return $this->getApiKey() !== '';
    }

    public function configStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'message' => 'Add your The Odds API key before syncing.',
            ];
        }

        return [
            'configured' => true,
            'message' => 'Using your own The Odds API key (regions: ' . $this->getRegions() . ').',
        ];
    }

    public function testConnectivity(): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'No Odds API key provided.'];
        }

        try {
            $response = Http::timeout(6)->get(self::BASE_URI . 'sports/?apiKey=' . urlencode($apiKey));

            if ($response->successful()) {
                $sports = $response->json();
                $count = is_array($sports) ? count($sports) : 0;
                return [
                    'success' => true,
                    'message' => "API Key is VALID! Connected successfully to The Odds API ({$count} active sports available).",
                ];
            }

            $body = $response->json();
            $message = is_array($body) && isset($body['message']) ? $body['message'] : 'Status ' . $response->status();
            return ['success' => false, 'message' => "API Error from Odds Provider: {$message}"];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    public function fetchFixtures(?array $sportKeys = null): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            throw new \RuntimeException('The Odds API key is not configured.');
        }

        $sportKeys = array_values(array_filter(array_map(
            static fn ($key): string => strtolower(trim((string) $key)),
            $sportKeys ?? []
        )));

        $fixtures = [];
        foreach ($sportKeys as $sportKey) {
            $fixtures = array_merge($fixtures, $this->fetchSportFixtures($sportKey));
        }

        return $fixtures;
    }

    public function fetchSports(): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            throw new \RuntimeException('The Odds API key is not configured.');
        }

        $response = Http::timeout(8)->get(self::BASE_URI . 'sports?apiKey=' . urlencode($apiKey) . '&all=true');
        if ($response->failed()) {
            throw new \RuntimeException('The Odds API request failed: ' . $response->body());
        }

        $sports = $response->json();
        if (!is_array($sports)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $sport): array => [
                'key' => (string) ($sport['key'] ?? ''),
                'title' => (string) ($sport['title'] ?? ($sport['key'] ?? '')),
            ],
            array_filter($sports, static fn ($sport): bool => is_array($sport) && !empty($sport['key']))
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSportFixtures(string $sportKey): array
    {
        $url = self::BASE_URI . "sports/{$sportKey}/odds/?apiKey=" . urlencode($this->getApiKey())
            . '&regions=' . urlencode($this->getRegions())
            . '&markets=' . urlencode($this->getMarkets());

        $response = Http::timeout(8)->get($url);
        if ($response->failed()) {
            Log::error("Failed fetching odds for sport {$sportKey}: " . $response->body());
            return [];
        }

        $events = $response->json();
        if (!is_array($events)) {
            return [];
        }

        $fixtures = [];
        foreach ($events as $event) {
            $normalized = $this->normalizeEvent($event, $sportKey);
            if ($normalized !== null) {
                $fixtures[] = $normalized;
            }
        }

        return $fixtures;
    }

    /**
     * @param  mixed  $event
     * @return array<string, mixed>|null
     */
    private function normalizeEvent($event, string $sportKey): ?array
    {
        if (!is_array($event)) {
            return null;
        }

        $id = trim((string) ($event['id'] ?? ''));
        $home = trim((string) ($event['home_team'] ?? ''));
        $away = trim((string) ($event['away_team'] ?? ''));
        $commence = (string) ($event['commence_time'] ?? '');

        if ($id === '' || $commence === '') {
            return null;
        }

        $markets = [];
        $marketData = collect($event['bookmakers'] ?? [])
            ->pluck('markets')->flatten(1)->where('key', 'h2h')
            ->sortByDesc('last_update')->first();

        if (is_array($marketData)) {
            $markets[] = (object) $marketData;
        }

        return [
            'id' => $id,
            'sport_key' => strtolower(trim((string) ($event['sport_key'] ?? $sportKey))),
            'sport_title' => trim((string) ($event['sport_title'] ?? $sportKey)),
            'home_team' => $home,
            'away_team' => $away,
            'commence_time' => $commence,
            'has_outrights' => (bool) ($event['has_outrights'] ?? false),
            'markets' => $markets,
        ];
    }

    private function getApiKey(): string
    {
        $key = function_exists('settings') ? trim((string) settings('odds_api_key', '')) : '';
        if ($key === '') {
            $key = trim((string) env('THE_ODDS_API_KEY', env('ODDS_API_KEY', '')));
        }

        return $key;
    }

    private function getRegions(): string
    {
        $regions = function_exists('settings') ? settings('ods_api_regions', '') : '';
        if (empty($regions)) {
            $regions = function_exists('settings') ? settings('odds_api_region', 'eu') : 'eu';
        }

        return is_array($regions) ? implode(',', $regions) : (string) $regions;
    }

    private function getMarkets(): string
    {
        $markets = function_exists('settings') ? settings('ods_api_markets', 'h2h') : 'h2h';

        return is_array($markets) ? implode(',', $markets) : (string) $markets;
    }
}
