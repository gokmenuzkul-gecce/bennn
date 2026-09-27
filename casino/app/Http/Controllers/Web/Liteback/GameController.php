<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\Http\Controllers\Controller;

class GameController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderLibrary($request, false);
    }

    public function cedar(Request $request)
    {
        return $this->renderLibrary($request, true);
    }

    public function cedarInactive(Request $request)
    {
        $request->merge(['status' => 'disabled']);
        return $this->renderLibrary($request, true);
    }

    private function renderLibrary(Request $request, bool $isCedarLibrary)
    {
        $perPage = (int) $request->input('per_page', 25);
        $term = trim((string) $request->input('q', ''));
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $status = $request->input('status', 'all'); // all, active, disabled

        // 1. Fetch all providers / categories with game stats using ID + original_id mapping
        $aliasPrefix = DB::getTablePrefix();
        $providerQuery = DB::table('categories as c')
            ->join('game_categories as gc', 'c.id', '=', 'gc.category_id')
            ->join('games as g', function ($join) {
                $join->on('gc.game_id', '=', 'g.id')
                    ->orOn('gc.game_id', '=', 'g.original_id');
            })
            ->select('c.id', 'c.title')
            ->selectRaw("COUNT(DISTINCT {$aliasPrefix}g.id) as total_games")
            ->selectRaw("SUM(CASE WHEN {$aliasPrefix}g.view = 1 THEN 1 ELSE 0 END) as active_games")
            ->selectRaw("SUM(CASE WHEN {$aliasPrefix}g.view = 0 THEN 1 ELSE 0 END) as disabled_games")
            ->groupBy('c.id', 'c.title')
            ->havingRaw("COUNT(DISTINCT {$aliasPrefix}g.id) > 0")
            ->orderByDesc('total_games');
        $isCedarLibrary
            ? $providerQuery->whereIn('c.href', \VanguardLTE\Services\CedarGameRegistry::CATEGORY_HREFS)
            : $providerQuery->whereNotIn('c.href', \VanguardLTE\Services\CedarGameRegistry::CATEGORY_HREFS);
        $this->applyLibraryScope($providerQuery, $isCedarLibrary, 'g');
        $providers = $providerQuery->get();

        // 2. Build games query
        $query = DB::table('games')->select(
            'games.id',
            'games.original_id',
            'games.name',
            'games.title',
            'games.view',
            'games.bet',
            'games.denomination',
            'games.bids',
            'games.stat_in',
            'games.stat_out',
            'games.current_rtp',
            'games.shop_id',
            'games.source_type',
            'games.delivery_mode',
            'games.custom_path',
            'games.legacy_rights_attested_at',
            'games.legacy_rights_attested_by'
        );
        $this->applyLibraryScope($query, $isCedarLibrary);

        if ($categoryId) {
            $targetIds = DB::table('game_categories')->where('category_id', $categoryId)->pluck('game_id')->toArray();
            $query->where(function ($q) use ($targetIds) {
                $q->whereIn('games.id', $targetIds)
                    ->orWhere(function ($sub) use ($targetIds) {
                        $sub->where('games.original_id', '>', 0)
                            ->whereIn('games.original_id', $targetIds);
                    });
            });
        }

        if ($status === 'active') {
            $query->where('games.view', 1);
        } elseif ($status === 'disabled') {
            $query->where('games.view', 0);
        }

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('games.title', 'like', '%' . $term . '%')
                    ->orWhere('games.name', 'like', '%' . $term . '%')
                    ->orWhere('games.id', $term);
            });
        }

        $games = $query->orderByDesc('games.id')
            ->paginate($perPage)
            ->appends($request->only(['q', 'category_id', 'status', 'per_page']));

        // 3. Attach category names to each game in current page
        $gameIds = $games->pluck('id')->toArray();
        $gameOrigIds = $games->pluck('original_id')->filter(function ($id) {
            return (int) $id > 0;
        })->toArray();
        $allLookups = array_unique(array_merge($gameIds, $gameOrigIds));

        if (!empty($allLookups)) {
            $gameCats = DB::table('game_categories')
                ->join('categories', 'game_categories.category_id', '=', 'categories.id')
                ->whereIn('game_categories.game_id', $allLookups)
                ->select('game_categories.game_id', 'categories.title')
                ->get();

            foreach ($games as $g) {
                $matchedTitles = $gameCats->filter(function ($row) use ($g) {
                    return (int) $row->game_id === (int) $g->id ||
                        ((int) $g->original_id > 0 && (int) $row->game_id === (int) $g->original_id);
                })->pluck('title')->unique()->values()->toArray();

                $g->category_names = $matchedTitles;
            }
        }

        $activeQuery = DB::table('games');
        $disabledQuery = DB::table('games');
        $this->applyLibraryScope($activeQuery, $isCedarLibrary);
        $this->applyLibraryScope($disabledQuery, $isCedarLibrary);
        $totalActive = $activeQuery->where('view', 1)->count();
        $totalDisabled = $disabledQuery->where('view', 0)->count();
        $remoteCatalog = [];
        $remoteCatalogError = null;
        if ($isCedarLibrary) {
            try {
                $remoteCatalog = (new \VanguardLTE\Services\PromexGameDeliveryService())->catalogById();
            } catch (\Throwable $e) {
                $remoteCatalogError = 'Promex Remote catalog is unavailable.';
            }
        }
        $legacyService = new \VanguardLTE\Services\LegacyCompatibilityService();
        $legacyEnabled = $legacyService->enabled();
        $legacyCdnAvailable = \VanguardLTE\Services\LicenseService::canUseCdnGames();
        try {
            $legacyDiscovered = \Illuminate\Support\Facades\Cache::remember(
                'legacy-compatibility:discovery:v1', 60, fn () => $legacyService->discover()
            );
        } catch (\Throwable) {
            $legacyDiscovered = $legacyService->discover();
        }

        return view('liteback.games.index', [
            'games' => $games,
            'providers' => $providers,
            'term' => $term,
            'selectedCategory' => $categoryId,
            'selectedStatus' => $status,
            'totalActive' => $totalActive,
            'totalDisabled' => $totalDisabled,
            'inactive' => false,
            'remoteCatalog' => $remoteCatalog,
            'remoteCatalogError' => $remoteCatalogError,
            'legacyEnabled' => $legacyEnabled,
            'legacyCdnAvailable' => $legacyCdnAvailable,
            'legacyDiscovered' => $legacyDiscovered,
            'isCedarLibrary' => $isCedarLibrary,
        ]);
    }

    public function inactive(Request $request)
    {
        return redirect()->route('liteback.games.index', ['status' => 'disabled']);
    }

    public function syncCedarCatalog()
    {
        try {
            $catalog = (new \VanguardLTE\Services\PromexCedarCatalogService())->catalog();
            $results = (new \VanguardLTE\Services\CedarGameRegistry())->syncRemoteCatalog($catalog);
        } catch (\Throwable $e) {
            return redirect()->route('liteback.cedar.index')->withErrors('CEDAR catalog sync failed: ' . $e->getMessage());
        }

        $ready = count(array_filter($results, fn (array $row): bool => in_array($row['status'], ['created', 'updated'], true)));
        $skipped = count($results) - $ready;
        return redirect()->route('liteback.cedar.index')
            ->with('success', "CEDAR catalog synchronized: {$ready} ready, {$skipped} skipped.")
            ->with('cedar_import_results', $results);
    }

    /**
     * 1-Click Game Visibility Toggle (view 1 <-> 0)
     */
    public function toggleView(Request $request, $gameId)
    {
        $game = DB::table('games')->where('id', $gameId)->first();
        if (!$game) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Game not found.'], 404);
            }
            return redirect()->back()->withErrors('Game not found.');
        }

        $newView = ((int) $game->view === 1) ? 0 : 1;
        if (($game->source_type ?? '') === \VanguardLTE\Services\LegacyCompatibilityService::SOURCE_TYPE) {
            try {
                (new \VanguardLTE\Services\LegacyCompatibilityService())->setGameEnabled($game, $newView === 1);
            } catch (\RuntimeException $e) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                }
                return redirect()->back()->withErrors($e->getMessage());
            }
        } else {
            DB::table('games')->where('id', $gameId)->update(['view' => $newView]);
        }

        $statusLabel = $newView === 1 ? 'Activated' : 'Disabled';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'view' => $newView,
                'message' => "Game '{$game->title}' is now {$statusLabel}.",
            ]);
        }

        return redirect()->back()->with('success', "Game '{$game->title}' is now {$statusLabel}.");
    }

    /**
     * Bulk Provider / Category Killswitch (Enable or Disable all games in provider)
     */
    public function bulkProviderToggle(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'action' => 'required|in:enable,disable',
        ]);

        $catId = (int) $request->input('category_id');
        $action = $request->input('action');
        $newView = ($action === 'enable') ? 1 : 0;

        $category = DB::table('categories')->where('id', $catId)->first();
        $targetIds = DB::table('game_categories')->where('category_id', $catId)->pluck('game_id')->toArray();

        if (empty($targetIds)) {
            $msg = "No games linked to provider '{$category->title}'.";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }
            return redirect()->back()->withErrors($msg);
        }

        $targets = DB::table('games')
            ->where(function ($q) use ($targetIds) {
                $q->whereIn('games.id', $targetIds)
                    ->orWhere(function ($sub) use ($targetIds) {
                        $sub->where('games.original_id', '>', 0)
                            ->whereIn('games.original_id', $targetIds);
                    });
            })->get();
        [$affected, $skipped] = $this->applyVisibility($targets, $newView === 1);

        $statusWord = ($newView === 1) ? 'ENABLED' : 'DISABLED';
        $msg = "Provider '{$category->title}': {$statusWord} {$affected} games; {$skipped} skipped.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count' => $affected,
                'category_id' => $catId,
                'action' => $action,
                'message' => $msg,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Bulk Action on checked games (Enable / Disable)
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'game_ids' => 'required|array',
            'game_ids.*' => 'integer',
            'action' => 'required|in:enable,disable',
        ]);

        $gameIds = $request->input('game_ids', []);
        $action = $request->input('action');
        $newView = ($action === 'enable') ? 1 : 0;

        [$affected, $skipped] = $this->applyVisibility(DB::table('games')->whereIn('id', $gameIds)->get(), $newView === 1);

        $statusWord = ($newView === 1) ? 'enabled' : 'disabled';
        $msg = "Successfully {$statusWord} {$affected} selected games; {$skipped} skipped.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count' => $affected,
                'message' => $msg,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function updateDelivery(Request $request, $gameId)
    {
        $request->validate(['delivery_mode' => 'required|in:LOCAL,PROMEX_REMOTE']);
        $game = DB::table('games')->where('id', $gameId)->first();
        if (!$game) return redirect()->back()->withErrors('Game not found.');
        $mode = (string) $request->input('delivery_mode');
        try {
            (new \VanguardLTE\Services\PromexGameDeliveryService())->assertSelectable($game, $mode);
            if (($game->source_type ?? '') === \VanguardLTE\Services\LegacyCompatibilityService::SOURCE_TYPE) {
                (new \VanguardLTE\Services\LegacyCompatibilityService())->setRemoteDelivery(
                    $game,
                    $mode === \VanguardLTE\Services\PromexGameDeliveryService::REMOTE
                );
            }
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
        DB::table('games')->where('id', $gameId)->update(['delivery_mode' => $mode]);
        return redirect()->back()->with('success', "Delivery for '{$game->title}' changed to {$mode}.");
    }

    public function bulkDelivery(Request $request)
    {
        $request->validate([
            'game_ids' => 'required|array', 'game_ids.*' => 'integer',
            'delivery_mode' => 'required|in:LOCAL,PROMEX_REMOTE',
        ]);
        $mode = (string) $request->input('delivery_mode');
        $games = DB::table('games')->whereIn('id', $request->input('game_ids', []))->get();
        $delivery = new \VanguardLTE\Services\PromexGameDeliveryService();
        try {
            $needsCedarCatalog = $mode === \VanguardLTE\Services\PromexGameDeliveryService::REMOTE
                && $games->contains(fn ($game): bool => ($game->source_type ?? '') === \VanguardLTE\Services\CedarGameRegistry::SOURCE_TYPE);
            $catalog = $needsCedarCatalog ? $delivery->catalogById() : [];
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
        $eligible = [];
        foreach ($games as $game) {
            try {
                $delivery->assertSelectable($game, $mode, $catalog);
                if (($game->source_type ?? '') === \VanguardLTE\Services\LegacyCompatibilityService::SOURCE_TYPE) {
                    (new \VanguardLTE\Services\LegacyCompatibilityService())->setRemoteDelivery(
                        $game,
                        $mode === \VanguardLTE\Services\PromexGameDeliveryService::REMOTE
                    );
                }
                $eligible[] = $game->id;
            } catch (\RuntimeException) {
                // Bulk changes skip games that cannot safely use the selected delivery mode.
            }
        }
        if ($eligible) DB::table('games')->whereIn('id', $eligible)->update(['delivery_mode' => $mode]);
        $skipped = count($games) - count($eligible);
        return redirect()->back()->with('success', count($eligible) . " games changed to {$mode}; {$skipped} skipped.");
    }

    /**
     * Update Bet Limits & Denomination Parameters
     */
    public function updateParams(Request $request, $gameId)
    {
        $request->validate([
            'bet' => 'required|string|max:255',
            'denomination' => 'nullable|numeric|min:0.01',
        ]);

        $game = DB::table('games')->where('id', $gameId)->first();
        if (!$game) {
            return redirect()->back()->withErrors('Game not found.');
        }
        $update = [
            'bet' => trim($request->input('bet')),
        ];

        if ($request->filled('denomination')) {
            $update['denomination'] = (float) $request->input('denomination');
        }

        DB::table('games')->where('id', $gameId)->update($update);

        return redirect()->back()->with('success', "Limits for game '{$game->title}' updated successfully.");
    }

    /**
     * Update Game Source & Host Location (Default / Custom Folder / External URL)
     */
    public function updateSource(Request $request, $gameId)
    {
        $request->validate([
            'source_type' => 'required|in:default,custom_folder,external_url,cedar_game',
            'custom_path' => 'nullable|string|max:500',
        ]);

        $game = DB::table('games')->where('id', $gameId)->first();
        if (!$game) {
            return redirect()->back()->withErrors('Game not found.');
        }
        if (($game->source_type ?? '') === \VanguardLTE\Services\LegacyCompatibilityService::SOURCE_TYPE) {
            return redirect()->back()->withErrors('Use Legacy Compatibility controls for this game source.');
        }

        $sourceType = $request->input('source_type', 'default');
        $customPath = trim((string) $request->input('custom_path', '')) ?: null;
        if ($sourceType === \VanguardLTE\Services\CedarGameRegistry::SOURCE_TYPE) {
            $manifest = \VanguardLTE\Services\CedarGameRegistry::manifest($game->name);
            if (!$manifest) return redirect()->back()->withErrors('This game has no valid /CedarGames manifest.');
            $customPath = "/CedarGames/{$game->name}/{$manifest['entry']}";
        }

        DB::table('games')->where('id', $gameId)->update([
            'source_type' => $sourceType,
            'delivery_mode' => $sourceType === \VanguardLTE\Services\CedarGameRegistry::SOURCE_TYPE
                ? ($game->delivery_mode ?? 'LOCAL') : 'LOCAL',
            'custom_path' => $customPath,
        ]);

        return redirect()->back()->with('success', "Source configuration for '{$game->title}' updated successfully.");
    }

    /**
     * Register a New Custom / Manual Game
     */
    public function storeManualGame(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'name' => 'required|string|max:100|alpha_dash|unique:games,name',
            'category_id' => 'required|integer|exists:categories,id',
            'source_type' => 'required|in:default,custom_folder,external_url,cedar_game',
            'custom_path' => 'nullable|string|max:500',
            'bet' => 'nullable|string|max:100',
            'denomination' => 'nullable|numeric|min:0.01',
        ]);

        $shopId = 1;
        $gameId = DB::table('games')->insertGetId([
            'name' => trim($request->input('name')),
            'title' => trim($request->input('title')),
            'shop_id' => $shopId,
            'source_type' => $request->input('source_type', 'default'),
            'custom_path' => trim((string) $request->input('custom_path', '')) ?: null,
            'bet' => trim($request->input('bet', '0.01-1.00')),
            'denomination' => (float) $request->input('denomination', 1.00),
            'view' => 1,
            'bids' => 0,
            'stat_in' => 0,
            'stat_out' => 0,
            'device' => 2,
            'category_temp' => 0,
            'original_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link category
        DB::table('game_categories')->insert([
            'game_id' => $gameId,
            'category_id' => (int) $request->input('category_id'),
        ]);

        return redirect()->back()->with('success', "New game '{$request->input('title')}' created and activated successfully!");
    }

    /** Register one or many validated folders from the isolated /CedarGames root. */
    public function importCedarGames(Request $request)
    {
        $request->validate([
            'names' => 'nullable|string|max:10000',
            'csv' => 'nullable|file|mimes:csv,txt|max:1024',
        ]);
        try {
            $results = (new \VanguardLTE\Services\CedarGameRegistry())->import(
                (string) $request->input('names', ''),
                $request->file('csv')
            );
        } catch (\DomainException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
        $created = count(array_filter($results, fn ($row) => in_array($row['status'], ['created', 'updated'], true)));
        $skipped = count($results) - $created;
        return redirect()->back()->with('success', "CEDAR import complete: {$created} ready, {$skipped} skipped.")
            ->with('cedar_import_results', $results);
    }

    public function toggleLegacyPlugin(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        try {
            (new \VanguardLTE\Services\LegacyCompatibilityService())->setPluginEnabled($request->boolean('enabled'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
        return redirect()->back()->with('success', $request->boolean('enabled')
            ? 'Legacy Compatibility enabled. No games were published.'
            : 'Legacy Compatibility disabled and all managed legacy games were turned off.');
    }

    public function importLegacyGames(Request $request)
    {
        $request->validate([
            'names' => 'nullable|string|max:10000',
            'csv' => 'nullable|file|mimes:csv,txt|max:1024',
            'rights_attested' => 'accepted',
        ]);
        try {
            $results = (new \VanguardLTE\Services\LegacyCompatibilityService())->import(
                (string) $request->input('names', ''), $request->boolean('rights_attested'),
                $request->file('csv'), (int) $request->user()->getAuthIdentifier()
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
        $ready = count(array_filter($results, fn (array $row): bool => $row['status'] !== 'skipped'));
        return redirect()->back()->with('success', "Legacy import complete: {$ready} registered disabled; activate after testing.")
            ->with('legacy_import_results', $results);
    }

    public function destroy($gameId)
    {
        $game = DB::table('games')->select('id', 'name', 'title')->where('id', $gameId)->first();
        if (!$game) {
            return redirect()->back()->withErrors('Game not found.');
        }

        try {
            DB::transaction(function () use ($gameId, $game) {
                if (Schema::hasTable('game_categories')) {
                    DB::table('game_categories')->where('game_id', $gameId)->delete();
                }
                if (Schema::hasTable('stat_game')) {
                    DB::table('stat_game')->where('game', $game->name)->delete();
                }
                DB::table('games')->where('id', $gameId)->delete();
            });

            $this->deleteGameImages($game->name);
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors('Delete failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Game deleted.');
    }

    public function deactivate($gameId)
    {
        return $this->toggleView(request(), $gameId);
    }

    public function activate($gameId)
    {
        return $this->toggleView(request(), $gameId);
    }

    private function deleteGameImages(string $name): void
    {
        $folder = public_path('frontend/Default/ico');
        $candidates = [
            $folder . DIRECTORY_SEPARATOR . $name . '.jpg',
            $folder . DIRECTORY_SEPARATOR . $name . '.jpeg',
            $folder . DIRECTORY_SEPARATOR . $name . '.png',
            $folder . DIRECTORY_SEPARATOR . $name . '.webp',
            $folder . DIRECTORY_SEPARATOR . $name . '.gif',
        ];

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    private function applyVisibility(iterable $games, bool $enabled): array
    {
        $affected = 0;
        $skipped = 0;
        $legacy = new \VanguardLTE\Services\LegacyCompatibilityService();
        foreach ($games as $game) {
            try {
                if (($game->source_type ?? '') === \VanguardLTE\Services\LegacyCompatibilityService::SOURCE_TYPE) {
                    $legacy->setGameEnabled($game, $enabled);
                } else {
                    DB::table('games')->where('id', $game->id)->update(['view' => $enabled ? 1 : 0]);
                }
                ++$affected;
            } catch (\RuntimeException) {
                ++$skipped;
            }
        }
        return [$affected, $skipped];
    }

    private function applyLibraryScope($query, bool $cedar, string $table = 'games'): void
    {
        $categoryIds = DB::table('categories')
            ->whereIn('href', \VanguardLTE\Services\CedarGameRegistry::CATEGORY_HREFS)
            ->pluck('id');
        $cedarIds = DB::table('game_categories')->whereIn('category_id', $categoryIds)->pluck('game_id');
        $scope = function ($q) use ($table, $cedarIds) {
            $q->where($table . '.source_type', \VanguardLTE\Services\CedarGameRegistry::SOURCE_TYPE)
                ->orWhere($table . '.name', 'like', 'Cedar%')
                ->orWhere($table . '.name', 'RoyalSteps')
                ->orWhereIn($table . '.id', $cedarIds)
                ->orWhereIn($table . '.original_id', $cedarIds);
        };

        $cedar ? $query->where($scope) : $query->whereNot($scope);
        $query->whereNotIn($table . '.name', \VanguardLTE\Services\CedarGameRegistry::RETIRED_GAMES);
    }
}
