<?php

namespace VanguardLTE\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Discovers and registers first-party games without touching the legacy /games tree. */
final class CedarGameRegistry
{
    public const SOURCE_TYPE = 'cedar_game';
    public const CATEGORY_HREF = 'cedar_remakes';
    public const RETIRED_GAMES = ['CedarHercules'];
    public const CATEGORY_HREFS = ['cedar_games', 'cedar_cards', 'cedar_remakes'];

    public static function root(): string
    {
        return dirname(base_path()) . DIRECTORY_SEPARATOR . 'CedarGames';
    }

    public static function manifest(string $name): ?array
    {
        if (in_array($name, self::RETIRED_GAMES, true)) return null;
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $name)) return null;
        $path = self::root() . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'game.json';
        if (!is_file($path)) return null;
        try {
            $manifest = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($manifest) || ($manifest['name'] ?? null) !== $name || ($manifest['engine'] ?? null) !== 'slot') return null;
        $entry = $manifest['entry'] ?? 'index.html';
        if (!is_string($entry) || !preg_match('/^[A-Za-z0-9._-]+$/D', $entry)) return null;
        if (!is_file(dirname($path) . DIRECTORY_SEPARATOR . $entry)) return null;
        if (isset($manifest['layout'])) {
            $preset = is_array($manifest['layout']) ? ($manifest['layout']['preset'] ?? '') : '';
            if (!in_array($preset, ['classic-3x3', 'standard-5x3', 'tall-5x4', 'wide-6x4'], true)) return null;
        }
        if (isset($manifest['assets'])) {
            if (!is_array($manifest['assets'])) return null;
            foreach (['background', 'mobile_background', 'frame', 'logo'] as $key) {
                $asset = $manifest['assets'][$key] ?? '';
                if ($asset !== '' && !self::validGameFile($name, $asset)) return null;
            }
            foreach (($manifest['assets']['symbols'] ?? []) as $symbol => $asset) {
                if (!ctype_digit((string) $symbol) || !self::validGameFile($name, $asset)) return null;
            }
        }
        if (isset($manifest['audio'])) {
            if (!is_array($manifest['audio'])) return null;
            $mode = $manifest['audio']['mode'] ?? '';
            if (!in_array($mode, ['procedural', 'files'], true)) return null;
            if ($mode === 'files') {
                $files = $manifest['audio']['files'] ?? null;
                $required = ['reel_start', 'reel_loop', 'reel_stop_1', 'reel_stop_2', 'reel_stop_3', 'result_no_win', 'win_small', 'win_medium', 'win_large', 'button_click', 'fast_toggle'];
                if (!is_array($files)) return null;
                foreach ($required as $event) {
                    $asset = $files[$event] ?? null;
                    if (!self::validGameFile($name, $asset, 'ogg')) return null;
                }
                foreach ($files as $event => $asset) {
                    if (!is_string($event) || !preg_match('/^[a-z][a-z0-9_]*$/D', $event) || !self::validGameFile($name, $asset, 'ogg')) return null;
                }
                $tiers = $manifest['audio']['win_tiers'] ?? [];
                if (!is_array($tiers)) return null;
                $medium = (float) ($tiers['medium_multiplier'] ?? 5);
                $large = (float) ($tiers['large_multiplier'] ?? 20);
                if ($medium <= 0 || $large <= $medium) return null;
            }
        }
        $manifest['entry'] = $entry;
        return $manifest;
    }

    private static function validGameFile(string $name, $asset, ?string $extension = null): bool
    {
        $prefix = '/CedarGames/' . $name . '/';
        if (!is_string($asset) || !str_starts_with($asset, $prefix) || str_contains($asset, '..') || !preg_match('#^/[A-Za-z0-9._/-]+$#D', $asset)) return false;
        if ($extension !== null && strtolower((string) pathinfo($asset, PATHINFO_EXTENSION)) !== $extension) return false;
        $relative = str_replace('/', DIRECTORY_SEPARATOR, ltrim($asset, '/'));
        return is_file(dirname(self::root()) . DIRECTORY_SEPARATOR . $relative);
    }

    public static function isRegisteredSlot(string $name): bool
    {
        return is_array(config('cedar_slots.' . $name)) || self::manifest($name) !== null;
    }

    public static function supportsLocalMath(string $name): bool
    {
        $manifest = self::manifest($name);
        if (!$manifest) return false;
        $file = $manifest['math'] ?? 'math.json';
        return is_string($file) && preg_match('/^[A-Za-z0-9._-]+$/D', $file) === 1
            && is_file(self::root() . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $file);
    }

    public static function math(string $name): array
    {
        $manifest = self::manifest($name);
        if (!$manifest) throw new \DomainException('Cedar game manifest is missing or invalid.');
        $file = $manifest['math'] ?? 'math.json';
        if (!is_string($file) || !preg_match('/^[A-Za-z0-9._-]+$/D', $file)) throw new \DomainException('Invalid math file.');
        $path = self::root() . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $file;
        try {
            $math = json_decode((string) file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new \DomainException('Cedar game math is missing or invalid.');
        }
        foreach (['rows', 'reel_weights', 'paylines', 'paytable', 'wild', 'published_rtp'] as $key) {
            if (!array_key_exists($key, $math)) throw new \DomainException("Math field {$key} is missing.");
        }
        if ((float) $math['published_rtp'] > 95.0) throw new \DomainException('Published RTP exceeds the 95% ceiling.');
        if ((int) $math['rows'] < 1 || count($math['reel_weights']) < 3 || count($math['paylines']) < 1) throw new \DomainException('Invalid slot dimensions.');
        foreach ($math['reel_weights'] as $weights) {
            if (!is_array($weights) || array_sum(array_map('intval', $weights)) < 1 || array_sum(array_map('intval', $weights)) > 10000) {
                throw new \DomainException('Each reel weight total must be between 1 and 10,000.');
            }
        }
        $symbolAssets = $manifest['assets']['symbols'] ?? [];
        if ($symbolAssets) {
            foreach ($math['reel_weights'] as $weights) {
                foreach (array_keys($weights) as $symbol) {
                    if (!isset($symbolAssets[(string) $symbol])) throw new \DomainException("Symbol {$symbol} is missing its image asset.");
                }
            }
            if (!isset($symbolAssets[(string) $math['wild']])) throw new \DomainException('The Wild symbol is missing its image asset.');
        }
        $reels = count($math['reel_weights']);
        foreach ($math['paylines'] as $line) {
            if (!is_array($line) || count($line) !== $reels) throw new \DomainException('Every payline must address every reel.');
            foreach ($line as $row) if (!is_int($row) || $row < 0 || $row >= (int) $math['rows']) throw new \DomainException('A payline points outside the grid.');
        }
        return $math;
    }

    public function import(string $names, ?UploadedFile $csv = null): array
    {
        $requested = preg_split('/[\s,;]+/', trim($names), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($csv) {
            $handle = fopen($csv->getRealPath(), 'rb');
            if ($handle) {
                while (($row = fgetcsv($handle)) !== false) {
                    $candidate = trim((string) ($row[0] ?? ''));
                    if ($candidate !== '' && strtolower($candidate) !== 'name') $requested[] = $candidate;
                }
                fclose($handle);
            }
        }
        $requested = array_values(array_unique($requested));
        if (!$requested) throw new \DomainException('Enter at least one CedarGames folder name.');

        return DB::transaction(function () use ($requested) {
            $template = DB::table('games')->where('name', 'CedarWheel')->first()
                ?: DB::table('games')->orderBy('id')->first();
            if (!$template) throw new \DomainException('A game row is required as an import template.');
            $category = DB::table('categories')->where('href', self::CATEGORY_HREF)->value('id');
            if (!$category) $category = DB::table('categories')->insertGetId([
                'title' => 'CEDAR Remakes', 'parent' => 0, 'position' => 3, 'href' => self::CATEGORY_HREF,
                'original_id' => 0, 'shop_id' => 1,
            ]);
            $results = [];
            foreach ($requested as $name) {
                $manifest = self::manifest($name);
                if (!$manifest) { $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Folder or manifest is invalid.']; continue; }
                $existing = DB::table('games')->where('name', $name)->first();
                if (in_array($name, CedarArcadeService::GAMES, true)
                    && DB::table('cedar_rounds')->where('game', $name)->whereIn('status', ['active','pending'])->exists()) {
                    $results[] = ['name'=>$name,'status'=>'skipped','message'=>'Finish existing rounds before changing delivery.'];
                    continue;
                }
                if ($existing && $existing->source_type !== self::SOURCE_TYPE) {
                    $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Name belongs to a legacy/external game.']; continue;
                }
                $values = [
                    'title' => (string) ($manifest['title'] ?? $name), 'label' => 'CEDAR',
                    'source_type' => self::SOURCE_TYPE, 'custom_path' => "/CedarGames/{$name}/{$manifest['entry']}",
                    'delivery_mode' => self::supportsLocalMath($name) ? 'LOCAL' : 'PROMEX_REMOTE',
                    'view' => 1, 'device' => 2, 'updated_at' => now(),
                ];
                if ($existing) {
                    $id = (int) $existing->id;
                    DB::table('games')->where('id', $id)->update($values + ['original_id' => $id]);
                    $status = 'updated';
                } else {
                    $row = (array) $template;
                    unset($row['id']);
                    $row = array_merge($row, $values, ['name' => $name, 'shop_id' => 1, 'original_id' => 0, 'created_at' => now()]);
                    $id = DB::table('games')->insertGetId($row);
                    DB::table('games')->where('id', $id)->update(['original_id' => $id]);
                    $status = 'created';
                }
                DB::table('game_categories')->updateOrInsert(['game_id' => $id, 'category_id' => $category], []);
                $this->publishThumbnail($name, $manifest);
                $results[] = ['name' => $name, 'status' => $status, 'message' => 'Ready in CEDAR Remakes.'];
            }
            return $results;
        }, 3);
    }

    /** Upsert every entitled hosted title from a verified Service Hub catalog. */
    public function syncRemoteCatalog(array $catalog): array
    {
        $games = $catalog['games'] ?? null;
        if (!is_array($games) || !array_is_list($games)) {
            throw new \DomainException('The CEDAR catalog is invalid.');
        }

        return DB::transaction(function () use ($games) {
            $template = DB::table('games')->where('name', 'CedarWheel')->first()
                ?: DB::table('games')->orderBy('id')->first();
            if (!$template) throw new \DomainException('A game row is required as an import template.');

            $results = [];
            foreach ($games as $remote) {
                $name = is_array($remote) ? (string) ($remote['id'] ?? '') : '';
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $name)
                    || in_array($name, self::RETIRED_GAMES, true)
                    || ($remote['availability'] ?? null) !== 'active'
                    || !preg_match('#^/cedar/games/' . preg_quote($name, '#') . '/[A-Za-z0-9._/-]+$#D', (string) ($remote['entry_path'] ?? ''))) {
                    $results[] = ['name' => $name ?: 'unknown', 'status' => 'skipped', 'message' => 'Invalid or retired catalog entry.'];
                    continue;
                }

                $existing = DB::table('games')->where('name', $name)->first();
                if ($existing && !in_array((string) ($existing->source_type ?? ''), [self::SOURCE_TYPE, 'custom_folder'], true)) {
                    $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Name belongs to a legacy/external game.'];
                    continue;
                }

                $categoryHref = (string) (($remote['manifest']['category_href'] ?? '') ?: $this->existingCedarCategory($existing));
                if (!in_array($categoryHref, self::CATEGORY_HREFS, true)) $categoryHref = 'cedar_games';
                $category = $this->ensureCategory($categoryHref);
                $values = [
                    'title' => (string) ($remote['title'] ?? $name),
                    'label' => 'CEDAR',
                    'source_type' => self::SOURCE_TYPE,
                    'custom_path' => (string) $remote['entry_path'],
                    'delivery_mode' => 'PROMEX_REMOTE',
                    'device' => 2,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    $id = (int) $existing->id;
                    DB::table('games')->where('id', $id)->update($values + ['original_id' => $id]);
                    $status = 'updated';
                } else {
                    $row = (array) $template;
                    unset($row['id']);
                    $row = array_merge($row, $values, [
                        'name' => $name, 'shop_id' => 1, 'original_id' => 0, 'view' => 1, 'created_at' => now(),
                    ]);
                    $id = DB::table('games')->insertGetId($row);
                    DB::table('games')->where('id', $id)->update(['original_id' => $id]);
                    $status = 'created';
                }

                DB::table('game_categories')->where('game_id', $id)
                    ->whereIn('category_id', DB::table('categories')->whereIn('href', self::CATEGORY_HREFS)->pluck('id'))
                    ->delete();
                DB::table('game_categories')->updateOrInsert(['game_id' => $id, 'category_id' => $category], []);
                $results[] = ['name' => $name, 'status' => $status, 'message' => 'Licensed hosted game is ready.'];
            }
            return $results;
        }, 3);
    }

    private function existingCedarCategory(?object $game): string
    {
        if (!$game) return 'cedar_games';
        return (string) (DB::table('categories')
            ->join('game_categories', 'categories.id', '=', 'game_categories.category_id')
            ->where('game_categories.game_id', (int) $game->id)
            ->whereIn('categories.href', self::CATEGORY_HREFS)
            ->value('categories.href') ?: 'cedar_games');
    }

    private function ensureCategory(string $href): int
    {
        $titles = ['cedar_games' => 'CEDAR Games', 'cedar_cards' => 'CEDAR Cards', 'cedar_remakes' => 'CEDAR Remakes'];
        $positions = ['cedar_games' => 1, 'cedar_cards' => 2, 'cedar_remakes' => 3];
        $id = DB::table('categories')->where('href', $href)->value('id');
        if ($id) return (int) $id;
        return (int) DB::table('categories')->insertGetId([
            'title' => $titles[$href], 'parent' => 0, 'position' => $positions[$href], 'href' => $href,
            'original_id' => 0, 'shop_id' => 1,
        ]);
    }

    private function publishThumbnail(string $name, array $manifest): void
    {
        $file = $manifest['thumbnail'] ?? 'thumbnail.jpg';
        if (!is_string($file) || !preg_match('/^[A-Za-z0-9._-]+$/D', $file)) return;
        $source = self::root() . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $file;
        if (!is_file($source)) return;
        $target = dirname(base_path()) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'Default' . DIRECTORY_SEPARATOR . 'ico' . DIRECTORY_SEPARATOR . $name . '.jpg';
        File::ensureDirectoryExists(dirname($target));
        if (!is_file($target)) File::copy($source, $target);
    }
}
