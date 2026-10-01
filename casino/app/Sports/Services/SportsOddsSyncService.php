<?php

namespace VanguardLTE\Sports\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use VanguardLTE\Sports\Category;
use VanguardLTE\Sports\League;
use VanguardLTE\Sports\Team;
use VanguardLTE\Sports\Game;
use VanguardLTE\Sports\Market;
use VanguardLTE\Sports\Outcome;
use VanguardLTE\Sports\Providers\Contracts\SportsOddsProvider;
use VanguardLTE\Sports\Providers\SportsProviderRegistry;
use VanguardLTE\SportsMatch;

class SportsOddsSyncService
{
    protected SportsProviderRegistry $providers;

    public function __construct(?SportsProviderRegistry $providers = null)
    {
        $this->providers = $providers ?? app(SportsProviderRegistry::class);
    }

    /** Selected provider key, persisted in settings and resolved through the registry. */
    public function getProvider(): string
    {
        return $this->providers->selectedKey();
    }

    public function provider(): SportsOddsProvider
    {
        return $this->providers->selected();
    }

    public function syncAll(?array $selectedSportKeys = null): array
    {
        $provider = $this->provider();
        $result = $this->importFixtures($provider->fetchFixtures($selectedSportKeys));

        return [
            'provider' => $provider->key(),
            'fixtures' => $result['fixtures'],
            'sports' => $result['sports'],
        ];
    }

    public function availableSports(): array
    {
        return $this->provider()->fetchSports();
    }

    /**
     * Hide every currently active odd without deleting wager or history records.
     */
    public function clearActiveOdds(): array
    {
        return DB::transaction(function (): array {
            $battleOdds = SportsMatch::where('status', 'upcoming')
                ->update(['status' => 'hidden']);

            $gameIds = Game::where('status', 1)->pluck('id');
            $marketIds = $gameIds->isEmpty()
                ? collect()
                : Market::whereIn('game_id', $gameIds)->pluck('id');

            $outcomes = $marketIds->isEmpty()
                ? 0
                : Outcome::whereIn('market_id', $marketIds)
                    ->where('status', 1)
                    ->update(['status' => 0, 'locked' => 1]);

            $markets = $gameIds->isEmpty()
                ? 0
                : Market::whereIn('game_id', $gameIds)
                    ->where('status', 1)
                    ->update(['status' => 0, 'locked' => 1]);

            $games = $gameIds->isEmpty()
                ? 0
                : Game::whereIn('id', $gameIds)->update(['status' => 3]);

            return compact('battleOdds', 'games', 'markets', 'outcomes');
        });
    }

    protected function getBaseUri(): string
    {
        return 'https://api.the-odds-api.com/v4/';
    }

    /**
     * Fetch sports list and adjust leagues.
     */
    public function syncSports(): void
    {
        $provider = $this->provider();
        $this->importFixtures($provider->fetchFixtures());
    }

    /**
     * Fetch games/events for active running leagues.
     */
    public function syncGames(?League $targetLeague = null): void
    {
        $provider = $this->provider();
        $sportKeys = $targetLeague ? [$targetLeague->odds_api_sport_key] : null;
        $this->importFixtures($provider->fetchFixtures($sportKeys));
    }

    /**
     * Fetch odds and save markets/outcomes.
     */
    public function syncOdds(string $type = 'active', ?League $targetLeague = null): void
    {
        $provider = $this->provider();
        $sportKeys = $targetLeague ? [$targetLeague->odds_api_sport_key] : null;
        $this->importFixtures($provider->fetchFixtures($sportKeys));
    }

    protected function saveTeam(League $league, string $teamName): Team
    {
        $team = Team::where('category_id', $league->category_id)->where('name', $teamName)->first();
        if (!$team) {
            $slug = Str::slug($teamName);
            if (Team::where('slug', $slug)->exists()) {
                $slug .= '-' . rand(100, 999);
            }
            $team = Team::create([
                'name' => $teamName,
                'short_name' => $teamName,
                'category_id' => $league->category_id,
                'manually_added' => 0,
                'slug' => $slug,
            ]);
        }
        return $team;
    }

    protected function saveGame(League $league, $event, ?Team $homeTeam, ?Team $awayTeam): Game
    {
        $title = ($homeTeam && $awayTeam) ? "{$homeTeam->name} vs {$awayTeam->name}" : $league->name;
        $slug = Str::slug($title);
        if (Game::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(100, 999);
        }

        $game = Game::create([
            'ods_api_id' => $event->id,
            'team_one_id' => $homeTeam->id ?? 0,
            'team_two_id' => $awayTeam->id ?? 0,
            'league_id' => $league->id,
            'title' => $title,
            'slug' => $slug,
            'bet_start_time' => now(),
            'start_time' => $this->parseCommenceTime($event->commence_time)->format('Y-m-d H:i:s'),
            'is_outright' => $league->has_outrights ?? 0,
            'manually_added' => 0,
            'status' => 1,
        ]);

        $teamsToSync = [];
        if ($homeTeam) $teamsToSync[] = $homeTeam->id;
        if ($awayTeam) $teamsToSync[] = $awayTeam->id;
        if (!empty($teamsToSync)) {
            $game->teams()->syncWithoutDetaching($teamsToSync);
        }

        return $game;
    }

    protected function saveMarketAndOutcomes(League $league, Game $game, $marketData, string $oddsFormat = 'decimal'): void
    {
        $title = $marketData->key;
        $outcomeType = 1;

        if ($marketData->key === 'h2h') {
            $title = 'Head to Head';
        } elseif ($marketData->key === 'h2h_3way') {
            $title = 'Head to Head 3 Way';
        } elseif ($marketData->key === 'spreads') {
            $title = 'Spreads';
            $outcomeType = 2;
        } elseif ($marketData->key === 'totals') {
            $title = 'Totals';
            $outcomeType = 3;
        }

        $market = Market::updateOrCreate(
            ['game_id' => $game->id, 'market_type' => $marketData->key],
            [
                'outcome_type' => $outcomeType,
                'title' => $title,
                'status' => 1,
                'locked' => 0,
                'market_updated_at' => Carbon::parse($marketData->last_update ?? now())->format('Y-m-d H:i:s'),
            ]
        );

        $teams = [];
        foreach ($marketData->outcomes as $outcome) {
            $outcome = (object)$outcome;

            if (!empty($outcome->name) && $game->team_one_id === 0 && $game->team_two_id === 0) {
                $team = Team::firstOrCreate(
                    ['name' => $outcome->name, 'category_id' => $league->category_id],
                    [
                        'short_name' => $outcome->name,
                        'slug' => Str::slug($outcome->name) . '-' . rand(100, 999),
                        'manually_added' => 0,
                    ]
                );
                $teams[] = $team->id;
            }

            $price = (float)$outcome->price;
            if ($oddsFormat === 'american') {
                $price = $this->americanToDecimal($price);
            }

            Outcome::updateOrCreate(
                ['market_id' => $market->id, 'name' => $outcome->name],
                [
                    'odds' => $price,
                    'point' => isset($outcome->point) ? (float)$outcome->point : null,
                    'status' => 1,
                    'locked' => 0,
                ]
            );
        }

        if (!empty($teams)) {
            $game->teams()->syncWithoutDetaching($teams);
        }
    }

    /**
     * Import normalized fixtures from the active provider into Battle Odds and
     * the advanced sportsbook tables. Provider-agnostic: every adapter returns
     * the same fixture shape.
     */
    protected function importFixtures(array $fixtures): array
    {
        $sports = [];
        $fixtureCount = 0;

        foreach ($fixtures as $fixture) {
            if (!is_array($fixture)) {
                continue;
            }

            $sportKey = strtolower(trim((string) ($fixture['sport_key'] ?? '')));
            $sportTitle = trim((string) ($fixture['sport_title'] ?? $sportKey));
            $matchId = trim((string) ($fixture['id'] ?? ''));
            $home = trim((string) ($fixture['home_team'] ?? ''));
            $away = trim((string) ($fixture['away_team'] ?? ''));
            $commenceTime = (string) ($fixture['commence_time'] ?? '');

            if ($sportKey === '' || $matchId === '' || $home === '' || $away === '' || $commenceTime === '') {
                continue;
            }

            $startsAt = Carbon::parse($commenceTime);
            if ($startsAt->lte(now())) {
                continue;
            }

            $league = $this->resolveLeague($sportKey, $sportTitle);
            if (!$league) {
                continue;
            }

            $event = (object) $fixture;
            $homeTeam = $this->saveTeam($league, $home);
            $awayTeam = $this->saveTeam($league, $away);
            $marketData = $this->firstMarket($fixture, 'h2h');

            DB::transaction(function () use ($league, $event, $homeTeam, $awayTeam, $marketData, $startsAt): void {
                $game = Game::where('ods_api_id', $event->id)->first();
                if (!$game) {
                    $game = $this->saveGame($league, $event, $homeTeam, $awayTeam);
                } else {
                    $game->league_id = $league->id;
                    $game->team_one_id = $homeTeam->id;
                    $game->team_two_id = $awayTeam->id;
                    $game->title = $homeTeam->name . ' vs ' . $awayTeam->name;
                    $game->start_time = $this->parseCommenceTime($event->commence_time)->format('Y-m-d H:i:s');
                    $game->status = 1;
                    $game->save();
                    $game->teams()->syncWithoutDetaching([$homeTeam->id, $awayTeam->id]);
                }

                if ($marketData !== null) {
                    $this->saveMarketAndOutcomes($league, $game, $marketData);
                }

                [$homeOdds, $drawOdds, $awayOdds] = $this->simpleOdds($marketData, $homeTeam->name, $awayTeam->name);
                SportsMatch::updateOrCreate(
                    ['match_id' => (string) $event->id],
                    [
                        'sport_key' => (string) $event->sport_key,
                        'sport_title' => (string) ($event->sport_title ?? $event->sport_key),
                        'home_team' => $homeTeam->name,
                        'away_team' => $awayTeam->name,
                        'start_time' => $startsAt->copy()->utc(),
                        'odds_home' => $homeOdds,
                        'odds_draw' => $drawOdds,
                        'odds_away' => $awayOdds,
                        'status' => 'upcoming',
                    ]
                );
            });

            $sports[$sportKey] = true;
            $fixtureCount++;
        }

        if (function_exists('settings')) {
            settings()->set('sports_last_sync', now()->toDateTimeString());
            settings()->set('sports_last_sync_count', $fixtureCount);
            settings()->save();
        }

        return ['fixtures' => $fixtureCount, 'sports' => count($sports)];
    }

    protected function firstMarket(array $fixture, string $marketKey): ?object
    {
        foreach (($fixture['markets'] ?? []) as $market) {
            $market = (object) $market;
            if (($market->key ?? null) === $marketKey) {
                return $market;
            }
        }

        return null;
    }

    protected function simpleOdds(?object $market, string $home, string $away): array
    {
        $homeOdds = 1.90;
        $drawOdds = null;
        $awayOdds = 1.90;
        foreach (($market->outcomes ?? []) as $rawOutcome) {
            $outcome = (object) $rawOutcome;
            $name = (string) ($outcome->name ?? '');
            if ($name === $home) {
                $homeOdds = (float) $outcome->price;
            } elseif ($name === $away) {
                $awayOdds = (float) $outcome->price;
            } elseif (strtolower($name) === 'draw') {
                $drawOdds = (float) $outcome->price;
            }
        }
        return [$homeOdds, $drawOdds, $awayOdds];
    }

    /**
     * Fetch upcoming odds globally across active categories.
     */
    public function syncUpcomingOdds(): void
    {
        $this->syncOdds('active');
    }

    /**
     * Resolve a league by its odds_api_sport_key, creating it if it doesn't exist.
     */
    protected function resolveLeague(string $sportKey, string $sportTitle): ?League
    {
        $prefix = explode('_', $sportKey)[0];
        $groupName = [
            'americanfootball' => 'American Football',
            'aussierules' => 'Aussie Rules',
            'baseball' => 'Baseball',
            'basketball' => 'Basketball',
            'boxing' => 'Boxing',
            'cricket' => 'Cricket',
            'handball' => 'Handball',
            'icehockey' => 'Ice Hockey',
            'mma' => 'Mixed Martial Arts',
            'rugbyleague' => 'Rugby League',
            'soccer' => 'Soccer',
            'tennis' => 'Tennis',
        ][$prefix] ?? ucfirst($prefix);

        $category = Category::where('odds_api_name', $groupName)->orWhere('name', $groupName)->first();
        if (!$category) {
            $category = Category::create([
                'name' => $groupName,
                'odds_api_name' => $groupName,
                'slug' => Str::slug($groupName),
                'status' => 1,
            ]);
        } elseif (!$category->status) {
            $category->status = 1;
            $category->save();
        }

        $league = League::where('odds_api_sport_key', $sportKey)->first();
        if ($league) {
            $league->category_id = $category->id;
            $league->name = $sportTitle;
            $league->short_name = $sportTitle;
            $league->api_status = 1;
            $league->status = 1;
            $league->save();
            return $league;
        }

        $slug = Str::slug($sportKey);
        if (League::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(100, 999);
        }

        return League::create([
            'odds_api_sport_key' => $sportKey,
            'category_id'        => $category->id,
            'name'               => $sportTitle,
            'short_name'         => $sportTitle,
            'slug'               => $slug,
            'has_outrights'      => 0,
            'api_status'         => 1,
            'status'             => 1,
            'manually_added'     => 0,
        ]);
    }

    /**
     * Convert American odds to Decimal.
     */
    protected function americanToDecimal(float $american): float
    {
        if ($american > 0) {
            return round(($american / 100) + 1, 2);
        } elseif ($american < 0) {
            return round((100 / abs($american)) + 1, 2);
        }
        return 1.0;
    }

    /**
     * Parse the commence time and shift it if in sandbox mode.
     */
    protected function parseCommenceTime(string $timeStr): Carbon
    {
        $commenceTime = Carbon::parse($timeStr);
        $baseUri = $this->getBaseUri();

        if (strpos($baseUri, 'sandbox') !== false) {
            // Shift June 2nd sandbox date to current month/day
            $sandboxBase = Carbon::parse('2026-06-02 00:00:00');
            $daysDiff = $sandboxBase->diffInDays(Carbon::today(), false);
            $shiftedTime = $commenceTime->copy()->addDays($daysDiff);
            if ($shiftedTime->isPast()) {
                $shiftedTime->addDays(2);
            }
            $commenceTime = $shiftedTime;
        }

        return $commenceTime->timezone(config('app.timezone', 'Europe/Berlin'));
    }
}
