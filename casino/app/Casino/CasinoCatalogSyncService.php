<?php

namespace VanguardLTE\Casino;

use Illuminate\Support\Facades\DB;
use VanguardLTE\Casino\Aggregator01\Aggregator01Client;
use VanguardLTE\Casino\Gregmorn\GregmornClient;
use VanguardLTE\Casino\OroPlay\OroPlayClient;
use VanguardLTE\Casino\Providers\Aggregator01Provider;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;
use VanguardLTE\Casino\Providers\GregmornProvider;
use VanguardLTE\Casino\Providers\OroPlayProvider;
use VanguardLTE\Casino\Providers\SmplCoreProvider;
use VanguardLTE\Casino\SmplCore\SmplCoreClient;
use VanguardLTE\Casino\SoftAggregator\SoftAggregatorClient;
use VanguardLTE\Casino\Providers\SoftAggregatorProvider;
use VanguardLTE\Casino\Waija\WaijaClient;
use VanguardLTE\Casino\Providers\WaijaProvider;
use VanguardLTE\Game;

/**
 * Pulls the live game catalogue from the aggregator and wires it to the local
 * lobby.
 *
 * Legacy rows in `games` predate the aggregator integration: they carry a human
 * title but no provider link, so their launch button points at a local folder
 * that no longer exists. This service matches those rows to the aggregator by
 * normalised title and fills in the provider key, the vendor game id used for
 * launch, and the real cover art. Titles with no local row are imported so the
 * full vendor catalogue is playable.
 */
class CasinoCatalogSyncService
{
    /** Fallback cover art shipped with the frontend. */
    private const FALLBACK_ICON = '/frontend/Default/ico/DayofDead.jpg';

    public function __construct(private readonly CasinoProviderRegistry $registry)
    {
    }

    /**
     * Fetch the raw catalogue for one provider.
     *
     * @return array<int, array{gameid: string, symbol: string, name: string, icon: string, vendor: string}>
     */
    public function fetch(string $providerKey): array
    {
        if ($providerKey === OroPlayProvider::KEY) {
            return $this->fetchOroPlay();
        }

        if ($providerKey === GregmornProvider::KEY) {
            return $this->fetchGregmorn();
        }

        if ($providerKey === SmplCoreProvider::KEY) {
            return $this->fetchSmplCore();
        }

        if ($providerKey === WaijaProvider::KEY) {
            return $this->fetchWaija();
        }

        if ($providerKey === SoftAggregatorProvider::KEY) {
            return $this->fetchSoftAggregator();
        }

        if ($providerKey === Aggregator01Provider::KEY) {
            return $this->fetchAggregator01();
        }

        $provider = $this->registry->make($providerKey);
        $config = $provider->config();

        $scheme = (string) ($config['scheme'] ?? 'https');
        $host = (string) ($config['endpoint'] ?? '');
        $path = (string) ($config['gamelist_path'] ?? '/gamelist');
        $agent = (string) ($config['agent_id'] ?? '');
        $token = (string) ($config['api_token'] ?? '');

        $url = sprintf('%s://%s%s?agentID=%s', $scheme, $host, $path, rawurlencode($agent));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            throw new \RuntimeException("{$providerKey}: gamelist alınamadı (HTTP {$status}).");
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded) || (int) ($decoded['code'] ?? -1) !== 0 || !isset($decoded['data'])) {
            $message = is_array($decoded) ? ($decoded['message'] ?? 'bilinmeyen hata') : 'geçersiz yanıt';
            throw new \RuntimeException("{$providerKey}: gamelist reddedildi ({$message}).");
        }

        $games = [];
        foreach ($decoded['data'] as $row) {
            if (!isset($row['gameid'], $row['name'])) {
                continue;
            }
            // Vendors name the cover field differently: Pragmatic/Amatic use
            // iconurl1..3, Amusnet uses iconurl2, PG Soft uses a single iconurl.
            $icon = $row['iconurl3'] ?? $row['iconurl2'] ?? $row['iconurl1'] ?? $row['iconurl'] ?? '';
            $games[] = [
                'gameid' => (string) $row['gameid'],
                'symbol' => (string) ($row['symbol'] ?? $row['gameid']),
                'name' => trim((string) $row['name']),
                'icon' => (string) $icon,
                'vendor' => (string) ($row['vendorid'] ?? ''),
            ];
        }

        return $games;
    }

    /**
     * Fetch the whole OroPlay catalogue.
     *
     * OroPlay is an aggregator: it exposes vendors via /vendors/list and each
     * vendor's games via /games/list. Every game carries its own vendorCode, so
     * we encode both halves into the launch id as "<vendorCode>:<gameCode>" —
     * the launch endpoint needs both, and the colon survives the game id column.
     *
     * @return array<int, array{gameid: string, symbol: string, name: string, icon: string, vendor: string}>
     */
    private function fetchOroPlay(): array
    {
        $client = new OroPlayClient();

        $vendors = $client->vendors();
        if ($vendors === []) {
            throw new \RuntimeException('oroplay: sağlayıcı listesi alınamadı.');
        }

        $games = [];
        foreach ($vendors as $vendor) {
            $vendorCode = (string) ($vendor['vendorCode'] ?? '');
            if ($vendorCode === '') {
                continue;
            }

            foreach ($client->games($vendorCode, 'tr') as $row) {
                $gameCode = (string) ($row['gameCode'] ?? '');
                $name = trim((string) ($row['gameName'] ?? ''));
                if ($gameCode === '' || $name === '') {
                    continue;
                }

                $games[] = [
                    'gameid' => $vendorCode . ':' . $gameCode,
                    'symbol' => $vendorCode . '_' . $gameCode,
                    'name' => $name,
                    'icon' => (string) ($row['thumbnail'] ?? ''),
                    'vendor' => $vendorCode,
                ];
            }
        }

        return $games;
    }

    /**
     * Fetch the whole Gregmorn Hub catalogue (slots + live tables).
     *
     * The Hub returns one flat list for the account currency; each item carries
     * the vendor name in `provider`, so slots and live-casino tables arrive
     * together. Launch ids are the Hub's own game ids (kept verbatim).
     *
     * @return array<int, array{gameid: string, symbol: string, name: string, icon: string, vendor: string}>
     */
    private function fetchGregmorn(): array
    {
        $client = new GregmornClient();

        $games = [];
        foreach ($client->games() as $row) {
            $id = (string) ($row['id'] ?? '');
            $title = trim((string) ($row['title'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }
            if (array_key_exists('isEnabled', $row) && !$row['isEnabled']) {
                continue;
            }

            $games[] = [
                'gameid' => $id,
                'symbol' => preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?: $id,
                'name' => $title,
                'icon' => (string) ($row['imageUrl'] ?? ''),
                'vendor' => (string) ($row['provider'] ?? ''),
            ];
        }

        return $games;
    }

    /**
     * Fetch the whole smpl core catalogue (slots + live casino).
     *
     * smpl core pages /games; each item carries the game UUID, its display name,
     * cover art and the vendor/category. Live tables arrive in the same list, so
     * a single walk covers everything the merchant account can play. Launch ids
     * are the game UUIDs, kept verbatim.
     *
     * @return array<int, array{gameid: string, symbol: string, name: string, icon: string, vendor: string}>
     */
    private function fetchSmplCore(): array
    {
        $client = new SmplCoreClient();

        $games = [];
        foreach ($client->games() as $row) {
            $id = (string) ($row['uuid'] ?? $row['game_uuid'] ?? $row['id'] ?? '');
            $title = trim((string) ($row['name'] ?? $row['title'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }

            $games[] = [
                'gameid' => $id,
                'symbol' => preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?: $id,
                'name' => $title,
                'icon' => (string) ($row['image'] ?? $row['image_url'] ?? $row['icon'] ?? ''),
                'vendor' => (string) ($row['provider'] ?? $row['vendor'] ?? $row['category'] ?? ''),
                'type' => $this->normalizeType($row['game_type'] ?? $row['type'] ?? ''),
            ];
        }

        return $games;
    }

    /**
     * Fetch the whole Waija (Slotsgateway) catalogue.
     *
     * Waija returns one flat list for the account currency; each row carries
     * id_hash (the launch id, e.g. "softswiss/WildChicago"), the display name,
     * the vendor in `category` and cover art. Slots and live tables arrive in
     * the same list. Launch ids are the id_hash, kept verbatim.
     *
     * @return array<int, array{gameid: string, symbol: string, name: string, icon: string, vendor: string}>
     */
    private function fetchWaija(): array
    {
        $client = new WaijaClient();

        $games = [];
        foreach ($client->games() as $row) {
            $id = (string) ($row['id_hash'] ?? '');
            $title = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }

            $games[] = [
                'gameid' => $id,
                'symbol' => preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?: $id,
                'name' => $title,
                'icon' => (string) ($row['image_square'] ?? $row['image'] ?? $row['image_portrait'] ?? ''),
                'vendor' => (string) ($row['category'] ?? $row['subcategory'] ?? ''),
                'type' => $this->normalizeType($row['game_type'] ?? $row['type'] ?? ''),
            ];
        }

        return $games;
    }

    /**
     * SoftAggregator catalogue.
     *
     * SoftAggregator shares Waija's getGameList shape, so id_hash is the launch
     * id and the same row mapping applies; only the client (base URL, config
     * block) differs.
     *
     * @return array<int, array{gameid: string, symbol: string, name: string, icon: string, vendor: string}>
     */
    private function fetchSoftAggregator(): array
    {
        $client = new SoftAggregatorClient();

        $games = [];
        foreach ($client->games() as $row) {
            $id = (string) ($row['id_hash'] ?? '');
            $title = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }

            $games[] = [
                'gameid' => $id,
                'symbol' => preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?: $id,
                'name' => $title,
                'icon' => (string) ($row['image_square'] ?? $row['image'] ?? $row['image_portrait'] ?? ''),
                'vendor' => (string) ($row['category'] ?? $row['subcategory'] ?? ''),
                'type' => $this->normalizeType($row['game_type'] ?? $row['type'] ?? ''),
            ];
        }

        return $games;
    }

    /**
     * 01.tech Aggregator (A8R) catalogue.
     *
     * The client already flattens the nested providers/games reply and maps each
     * game onto Game::GAME_TYPES (live tables flagged live=true become "live"),
     * so the rows drop straight into the shared sync pipeline.
     *
     * @return array<int, array{gameid: string, symbol: string, name: string, icon: string, vendor: string}>
     */
    private function fetchAggregator01(): array
    {
        $client = new Aggregator01Client();

        $games = [];
        foreach ($client->games() as $row) {
            $id = (string) ($row['gameid'] ?? '');
            $title = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }

            $games[] = [
                'gameid' => $id,
                'symbol' => (string) ($row['symbol'] ?? $id),
                'name' => $title,
                'icon' => (string) ($row['icon'] ?? ''),
                'vendor' => (string) ($row['vendor'] ?? ''),
                'type' => (string) ($row['type'] ?? 'slots'),
            ];
        }

        return $games;
    }

    /**
     * Sync one provider: link legacy rows by title, import the rest.
     *
     * @return array{provider: string, fetched: int, linked: int, created: int, skipped: int, category: string}
     */
    public function sync(string $providerKey, bool $createMissing = true, int $shopId = 1, bool $prune = false): array
    {
        $provider = $this->registry->make($providerKey);
        $games = $this->fetch($providerKey);

        $byTitle = [];
        foreach ($games as $entry) {
            $key = $this->normalize($entry['name']);
            if ($key !== '' && !isset($byTitle[$key])) {
                $byTitle[$key] = $entry;
            }
        }

        $categoryId = $this->ensureCategory($providerKey, $provider->label());
        // Shared type hubs so the lobby can offer a single Slot / Canlı Casino
        // filter across every aggregator, independent of the provider chip.
        $typeCategories = [
            'slots' => $this->ensureCategory('slots', 'Slots'),
            'live' => $this->ensureCategory('live_casino', 'Canlı Casino'),
        ];
        $linked = 0;
        $created = 0;
        $pruned = 0;

        $existing = Game::where('shop_id', $shopId)
            ->where(function ($q) use ($providerKey) {
                $q->whereNull('provider_key')->orWhere('provider_key', $providerKey);
            })
            ->get();

        $claimed = [];
        foreach ($existing as $game) {
            $key = $this->normalize((string) $game->title);
            if ($key === '' || !isset($byTitle[$key])) {
                continue;
            }
            $entry = $byTitle[$key];
            if (isset($claimed[$entry['gameid']]) && $game->provider_key === null) {
                continue;
            }
            $claimed[$entry['gameid']] = true;

            $game->provider_key = $providerKey;
            $game->provider_game_id = $entry['symbol'];
            $game->game_type = $entry['type'] ?? 'slots';
            $game->launch_code = $entry['gameid'];
            $game->icon_url = $entry['icon'] ?: self::FALLBACK_ICON;
            // The vendor still lists it, so it is launchable: re-show rows a
            // previous --prune/--hide-unlinked run had hidden.
            $game->view = 1;
            $game->save();

            $this->attachCategory((int) $game->original_id, $categoryId);
            $type = $entry['type'] ?? 'slots';
            if (isset($typeCategories[$type])) {
                $this->attachCategory((int) $game->original_id, $typeCategories[$type]);
            }
            $linked++;
        }

        if ($createMissing) {
            $nameIndex = $this->nameIndex($shopId);
            foreach ($games as $entry) {
                if (isset($claimed[$entry['gameid']])) {
                    continue;
                }
                $key = $this->normalize($entry['name']);
                // A title already owned by another provider is a different
                // game that merely shares a name (e.g. Golden Ox / GoldenOx),
                // so it must not block this provider's import.
                if (isset($nameIndex[$key]) && $nameIndex[$key] === $providerKey) {
                    continue;
                }
                $game = $this->createGame($entry, $providerKey, $shopId);
                $this->attachCategory((int) $game->original_id, $categoryId);
                $type = $entry['type'] ?? 'slots';
                if (isset($typeCategories[$type])) {
                    $this->attachCategory((int) $game->original_id, $typeCategories[$type]);
                }
                $nameIndex[$key] = $providerKey;
                $created++;
            }
        }

        if ($prune) {
            // Titles the vendor no longer lists would only fail at launch
            // ("undefined game id" / "game is closed"), so hide them from the
            // lobby. The row and its history stay intact.
            $liveSymbols = [];
            $liveNames = [];
            foreach ($games as $entry) {
                $liveSymbols[strtolower($entry['symbol'])] = true;
                $key = $this->normalize($entry['name']);
                if ($key !== '') {
                    $liveNames[$key] = true;
                }
            }

            $stale = Game::where('shop_id', $shopId)
                ->where('provider_key', $providerKey)
                ->where('view', 1)
                ->get();

            foreach ($stale as $game) {
                $symbol = strtolower((string) ($game->provider_game_id ?: $game->name));
                $titleKey = $this->normalize((string) $game->title);
                if (isset($liveSymbols[$symbol]) || ($titleKey !== '' && isset($liveNames[$titleKey]))) {
                    continue;
                }
                $game->view = 0;
                $game->save();
                $pruned++;
            }
        }

        return [
            'provider' => $providerKey,
            'fetched' => count($games),
            'linked' => $linked,
            'created' => $created,
            'skipped' => count($games) - $linked - $created,
            'pruned' => $pruned,
            'category' => $provider->label(),
        ];
    }

    /** @return array<string, array{provider: string, fetched: int, linked: int, created: int, skipped: int, pruned: int, category: string}> */
    public function syncAll(bool $createMissing = true, int $shopId = 1, bool $prune = false): array
    {
        $report = [];
        foreach ($this->registry->keys() as $key) {
            if (!$this->registry->make($key)->isConfigured()) {
                continue;
            }
            try {
                $report[$key] = $this->sync($key, $createMissing, $shopId, $prune);
            } catch (\Throwable $e) {
                // One unreachable provider must not abort the others; record
                // the failure and carry on with the next catalogue.
                $report[$key] = [
                    'provider' => $key,
                    'fetched' => 0,
                    'linked' => 0,
                    'created' => 0,
                    'skipped' => 0,
                    'pruned' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $report;
    }

    /**
     * Hide lobby rows that no aggregator provider owns.
     *
     * These legacy rows carry a local title but no provider link, so their only
     * launch path is the PROMEX-protected local runtime, which refuses to open
     * without an active license. Until one is activated they would only ever
     * answer "game temporarily unavailable", so they are hidden from the lobby.
     * The rows stay intact; set view=1 again once a license is active.
     */
    public function hideUnlinked(int $shopId = 1): int
    {
        return Game::where('shop_id', $shopId)
            ->where('view', 1)
            ->where(function ($q) {
                $q->whereNull('provider_key')->orWhere('provider_key', '');
            })
            ->update(['view' => 0]);
    }

    private function createGame(array $entry, string $providerKey, int $shopId): Game
    {
        $name = $this->uniqueName($entry['symbol'] ?: ('g' . $entry['gameid']), $shopId);

        // Bulk import: skip the admin audit subscriber, which expects every
        // legacy column to be present on the model.
        $game = Game::withoutEvents(static fn (): Game => Game::create([
            'name' => $name,
            'title' => $entry['name'],
            'shop_id' => $shopId,
            'device' => 2,
            'gamebank' => 'slots',
            'view' => 1,
            'source_type' => 'aggregator',
            'provider_key' => $providerKey,
            'provider_game_id' => $entry['symbol'],
            'game_type' => $entry['type'] ?? 'slots',
            'launch_code' => $entry['gameid'],
            'icon_url' => $entry['icon'] ?: self::FALLBACK_ICON,
            'bet' => '0.01, 0.02, 0.05, 0.10, 0.20',
            'denomination' => '1.00',
            'scaleMode' => '',
            'slotViewState' => '',
        ]));

        Game::withoutEvents(static function () use ($game): void {
            $game->original_id = $game->id;
            $game->save();
        });

        return $game;
    }

    private function ensureCategory(string $providerKey, string $label): int
    {
        $existing = DB::table('categories')->where('href', $providerKey)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $position = (int) DB::table('categories')->max('position') + 1;

        return (int) DB::table('categories')->insertGetId([
            'title' => $label,
            'parent' => 0,
            'position' => $position,
            'href' => $providerKey,
            'original_id' => 0,
            'shop_id' => 0,
        ]);
    }

    private function attachCategory(int $originalId, int $categoryId): void
    {
        if ($originalId <= 0) {
            return;
        }

        $exists = DB::table('game_categories')
            ->where('game_id', $originalId)
            ->where('category_id', $categoryId)
            ->exists();

        if (!$exists) {
            DB::table('game_categories')->insert([
                'game_id' => $originalId,
                'category_id' => $categoryId,
            ]);
        }
    }

    /** @return array<string, string|null> normalized title => owning provider key */
    private function nameIndex(int $shopId): array
    {
        $index = [];
        foreach (Game::where('shop_id', $shopId)->get(['title', 'provider_key']) as $game) {
            $key = $this->normalize((string) $game->title);
            if ($key !== '' && !array_key_exists($key, $index)) {
                $index[$key] = $game->provider_key !== null ? (string) $game->provider_key : null;
            }
        }

        return $index;
    }

    private function uniqueName(string $base, int $shopId): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]/', '', $base) ?: 'game';
        $name = $base;
        $suffix = 1;

        while (Game::where('name', $name)->exists()) {
            $name = $base . '_' . $suffix;
            $suffix++;
        }

        return $name;
    }

    private function normalize(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $title) ?? '');
    }

    /**
     * Map an aggregator's raw type onto the fixed Game::GAME_TYPES set.
     *
     * Aggregators ship a normalised `game_type` (slots/live/crash/table/...),
     * but the field is absent on some feeds, so fall back to a best-effort
     * match against the human `type`/`category` label before defaulting to
     * slots.
     */
    private function normalizeType(string $raw): string
    {
        $type = strtolower(trim($raw));
        if ($type === '') {
            return 'slots';
        }

        if (isset(\VanguardLTE\Game::GAME_TYPES[$type])) {
            return $type;
        }

        if (str_contains($type, 'live')) {
            return 'live';
        }
        if (str_contains($type, 'crash')) {
            return 'crash';
        }
        if (str_contains($type, 'table') || str_contains($type, 'roulette') || str_contains($type, 'blackjack') || str_contains($type, 'baccarat')) {
            return 'table';
        }
        if (str_contains($type, 'bingo')) {
            return 'bingo';
        }
        if (str_contains($type, 'keno')) {
            return 'keno';
        }
        if (str_contains($type, 'fish')) {
            return 'fishing';
        }
        if (str_contains($type, 'virtual')) {
            return 'virtual';
        }

        return 'slots';
    }
}
