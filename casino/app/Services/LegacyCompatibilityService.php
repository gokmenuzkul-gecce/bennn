<?php

namespace VanguardLTE\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Promex-owned discovery and activation boundary for operator-supplied legacy content. */
final class LegacyCompatibilityService
{
    public const SOURCE_TYPE = 'legacy_compat';
    public const CATEGORY_HREF = 'legacy-compatibility';
    public const OWNED_GAME_CODE = [
        'CedarBlackjack', 'CedarCoinFlip', 'CedarCrash', 'CedarDice', 'CedarGoal',
        'CedarHiLo', 'CedarKeno', 'CedarLimbo', 'CedarMines', 'CedarPlinko',
        'CedarTower', 'CedarTreasure', 'CedarWheel',
    ];

    private string $frontendRoot;
    private string $backendRoot;

    public function __construct(?string $frontendRoot = null, ?string $backendRoot = null)
    {
        $this->frontendRoot = $frontendRoot ?? base_path('../games');
        $this->backendRoot = $backendRoot ?? app_path('Games');
    }

    public function enabled(): bool
    {
        return (string) settings('legacy_compatibility_enabled', config('legacy.enabled_by_default', false) ? '1' : '0') === '1';
    }

    public function setPluginEnabled(bool $enabled): void
    {
        if ($enabled && (LicenseService::getStatus()['status'] ?? '') !== 'active') {
            throw new RuntimeException('An active Promex license is required to enable Legacy Compatibility.');
        }
        if (!$enabled) DB::table('games')->where('source_type', self::SOURCE_TYPE)->update(['view' => 0]);
        settings()->set('legacy_compatibility_enabled', $enabled ? '1' : '0');
        settings()->save();
    }

    public function requiresCompatibility(string $game): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $game) === 1
            && !in_array($game, self::OWNED_GAME_CODE, true)
            && $this->confinedFile($this->backendRoot, $game . '/Server.php') !== null;
    }

    /** Return metadata only. Content is never copied, uploaded, or published. */
    public function discover(): array
    {
        if (!is_dir($this->frontendRoot)) return [];
        $rows = [];
        foreach (new \DirectoryIterator($this->frontendRoot) as $item) {
            if (!$item->isDir() || $item->isDot() || $item->isLink()) continue;
            $name = $item->getFilename();
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $name)) continue;
            if (in_array($name, self::OWNED_GAME_CODE, true)) continue;
            $entry = $this->entryFor($name);
            if ($entry === null) continue;
            $server = $this->confinedFile($this->backendRoot, $name . '/Server.php');
            $slotSettings = $this->confinedFile($this->backendRoot, $name . '/SlotSettings.php');
            $sample = file_get_contents($this->frontendRoot . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $entry, false, null, 0, 32768);
            $rows[$name] = [
                'name' => $name,
                'entry' => $entry,
                'frontend' => true,
                'backend' => $server !== null,
                'slot_settings' => $slotSettings !== null,
                'bridge_hint' => is_string($sample) && (stripos($sample, 'mock-websocket.js') !== false || stripos($sample, 'WebSocket') !== false),
            ];
        }
        ksort($rows, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($rows);
    }

    public function import(string $names, bool $rightsAttested, ?UploadedFile $csv = null, ?int $attestedBy = null): array
    {
        if (!$this->enabled()) throw new RuntimeException('Enable Legacy Compatibility before importing local games.');
        if (!$rightsAttested) throw new RuntimeException('Confirm that you have the rights to use the selected local game content.');
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
        if (!$requested) throw new RuntimeException('Select at least one discovered legacy game.');
        $discovered = [];
        foreach ($this->discover() as $row) $discovered[$row['name']] = $row;

        return DB::transaction(function () use ($requested, $discovered, $attestedBy): array {
            $template = DB::table('games')->orderBy('id')->first();
            if (!$template) throw new RuntimeException('A game row is required as an import template.');
            $category = DB::table('categories')->where('href', self::CATEGORY_HREF)->value('id');
            if (!$category) $category = DB::table('categories')->insertGetId([
                'title' => 'Legacy Compatibility', 'parent' => 0, 'position' => 99,
                'href' => self::CATEGORY_HREF, 'original_id' => 0, 'shop_id' => 1,
            ]);
            $results = [];
            foreach ($requested as $name) {
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $name) || !isset($discovered[$name])) {
                    $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Local frontend was not discovered.'];
                    continue;
                }
                $candidate = $discovered[$name];
                if (!$candidate['backend']) {
                    $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Matching local Server.php is missing.'];
                    continue;
                }
                $existing = DB::table('games')->where('name', $name)->first();
                if ($existing && ($existing->source_type ?? '') === CedarGameRegistry::SOURCE_TYPE) {
                    $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Name belongs to a Cedar game.'];
                    continue;
                }
                $values = [
                    'title' => (string) ($existing->title ?? trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', $name))),
                    'label' => 'LEGACY', 'source_type' => self::SOURCE_TYPE, 'delivery_mode' => PromexGameDeliveryService::LOCAL,
                    'custom_path' => '/games/' . $name . '/' . $candidate['entry'],
                    'legacy_rights_attested_at' => now(), 'legacy_rights_attested_by' => $attestedBy,
                    'view' => 0, 'device' => 2, 'updated_at' => now(),
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
                $results[] = ['name' => $name, 'status' => $status, 'message' => 'Imported disabled; activate explicitly after testing.'];
            }
            return $results;
        }, 3);
    }

    public function setGameEnabled(object $game, bool $enabled): void
    {
        if (($game->source_type ?? '') !== self::SOURCE_TYPE) throw new RuntimeException('This is not a Legacy Compatibility game.');
        if ($enabled) $this->assertPlayable($game, false);
        DB::table('games')->where('id', $game->id)->update(['view' => $enabled ? 1 : 0]);
    }

    /** Keep Apache's per-title CDN proxy marker synchronized with delivery_mode. */
    public function setRemoteDelivery(object $game, bool $remote): void
    {
        if (($game->source_type ?? '') !== self::SOURCE_TYPE
            || !preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', (string) ($game->name ?? ''))) {
            throw new RuntimeException('This is not a valid Legacy Compatibility game.');
        }
        $root = storage_path('app/promex-legacy-remote');
        $marker = $root . DIRECTORY_SEPARATOR . $game->name . '.remote';
        if (!$remote) {
            if (is_file($marker) && !unlink($marker)) {
                throw new RuntimeException('Could not disable Promex CDN delivery for this game.');
            }
            return;
        }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('Could not create the private CDN delivery state directory.');
        }
        if (file_put_contents($marker, "promex-remote\n", LOCK_EX) === false) {
            throw new RuntimeException('Could not enable Promex CDN delivery for this game.');
        }
        @chmod($marker, 0600);
    }

    public function assertPlayable(object $game, bool $requireVisible = true): void
    {
        if (!$this->enabled()) throw new RuntimeException('Legacy Compatibility is disabled.');
        if (($game->source_type ?? '') !== self::SOURCE_TYPE || empty($game->legacy_rights_attested_at)) {
            throw new RuntimeException('Legacy game rights have not been attested.');
        }
        if ($requireVisible && (int) ($game->view ?? 0) !== 1) throw new RuntimeException('This legacy game is disabled.');
        if (($game->delivery_mode ?? PromexGameDeliveryService::LOCAL) === PromexGameDeliveryService::REMOTE
            && !LicenseService::canUseCdnGames()) {
            throw new RuntimeException('An active Promex CDN game entitlement is required.');
        }
        $name = (string) $game->name;
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $name)
            || $this->entryFor($name) === null
            || $this->confinedFile($this->backendRoot, $name . '/Server.php') === null) {
            throw new RuntimeException('Required operator-supplied legacy files are missing.');
        }
        if ((LicenseService::getStatus()['status'] ?? '') !== 'active' || !LicenseService::canPlayGame((string) $game->name)) {
            throw new RuntimeException('An active game license is required.');
        }
    }

    public function launchPath(object $game): string
    {
        $this->assertPlayable($game);
        $entry = $this->entryFor((string) $game->name);
        if ($entry === null) throw new RuntimeException('Legacy game entry is missing.');
        return '/games/' . $game->name . '/' . $entry;
    }

    private function entryFor(string $name): ?string
    {
        foreach (['index.html', 'index.htm', 'desktop/index.html', 'desktop/index.htm', 'mobile/index.html', 'mobile/index.htm'] as $relative) {
            if ($this->confinedFile($this->frontendRoot, $name . '/' . $relative) !== null) {
                return $relative;
            }
        }
        return null;
    }

    private function confinedFile(string $root, string $relative): ?string
    {
        $rootPath = realpath($root);
        $filePath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (!is_string($rootPath) || !is_string($filePath) || !is_file($filePath)) return null;
        $prefix = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($filePath, $prefix) ? $filePath : null;
    }
}
