<?php

namespace VanguardLTE\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VanguardLTE\Game;
use VanguardLTE\Games\CedarMath;
use VanguardLTE\StatGame;
use VanguardLTE\User;

/** Provider-neutral, instant-settlement slot runtime for games in /CedarGames. */
final class CedarSlotService
{
    public const MATH_VERSION = 'cedar-slot-v1';
    private const REMOTE_GAMES = ['Cedarcules'];

    public function handle(Request $request, string $game): array
    {
        if (!Auth::check()) return $this->error('Sign in to play.');
        if (in_array($game, self::REMOTE_GAMES, true)) return $this->handleRemote($request, $game);
        try {
            return DB::transaction(function () use ($request, $game) {
                $manifest = CedarGameRegistry::manifest($game);
                if (!$manifest) throw new \DomainException('Cedar game is not registered.');
                $math = CedarGameRegistry::math($game);
                $user = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                if ($user->is_blocked || $user->status !== 'Active') throw new \DomainException('This account cannot play.');
                $stateRow = DB::table('cedar_states')->where('user_id', $user->id)->where('game', $game)->first();
                $state = $stateRow ? json_decode($stateRow->state, true, 32, JSON_THROW_ON_ERROR) : $this->newState();
                $action = $request->input('action', 'init');
                if ($action === 'init') $result = $this->init($user, $game, $manifest, $math, $state);
                elseif ($action === 'spin') $result = $this->spin($request, $user, $game, $manifest, $math, $state);
                else throw new \DomainException('Invalid action.');
                DB::table('cedar_states')->updateOrInsert(
                    ['user_id' => $user->id, 'game' => $game],
                    ['state' => json_encode($state, JSON_THROW_ON_ERROR)]
                );
                return $result;
            }, 5);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    private function handleRemote(Request $request, string $game): array
    {
        try {
            $manifest = config('cedar_slots.' . $game);
            if (!is_array($manifest)) throw new \RuntimeException('Cedar game integration is not registered.');
            $catalog = (new PromexCedarCatalogService())->catalog();
            $entitled = false;
            foreach ($catalog['games'] as $entry) {
                if (($entry['id'] ?? null) === $game && ($entry['manifest']['engine'] ?? null) === 'slot') {
                    $entitled = true;
                    break;
                }
            }
            if (!$entitled) throw new \RuntimeException('This Cedar slot is not available for this installation.');
            $action = $request->input('action', 'init');
            if ($action === 'init') return $this->remoteInit((int) Auth::id(), $game, $manifest);
            if ($action === 'spin') return $this->remoteSpin($request, (int) Auth::id(), $game);
            throw new \RuntimeException('Invalid action.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
    }

    private function remoteInit(int $userId, string $game, array $manifest): array
    {
        $remote = (new PromexCedarSettlementService())->initialize($userId, $game);
        $user = User::findOrFail($userId);
        $presentation = $remote['presentation'];
        $math = [
            'rows' => (int) $presentation['rows'],
            'reel_weights' => array_fill(0, (int) $presentation['reels'], []),
            'symbols' => $presentation['symbols'],
            'wild' => (int) $presentation['wild'],
            'published_rtp' => (float) $presentation['published_rtp'],
        ];
        $last = DB::table('cedar_rounds')->where('user_id', $userId)->where('game', $game)
            ->where('status', 'settled')->orderByDesc('created_at')->first();
        $lastProof = $last ? (json_decode((string) $last->data, true, 128, JSON_THROW_ON_ERROR)['result']['proof'] ?? null) : null;
        return [
            'status' => 'success', 'game' => $game, 'title' => $manifest['title'] ?? $game,
            'balance' => $this->money($user->balance), 'currency' => 'CEDARS',
            'min_bet' => (float) $presentation['min_wager'], 'max_bet' => (float) $presentation['max_wager'],
            'bet_steps' => $manifest['bet_steps'] ?? [0.5, 1, 2, 5, 10],
            'max_payout' => (float) ($manifest['max_payout'] ?? 1000000),
            'math_version' => 'hosted', 'math' => $math, 'last_proof' => $lastProof,
            'server_seed_hash' => $remote['server_seed_hash'], 'client_seed' => $remote['client_seed'], 'nonce' => $remote['nonce'],
            'brand' => [
                'name' => (string) settings('cedar_remake_brand_name', 'CEDAR'),
                'loader_css' => $this->remoteAsset($this->safeAsset(settings('cedar_remake_loader_css', '/CedarGames/_runtime/loader.css'), '/CedarGames/_runtime/loader.css'), $game),
                'theme_css' => $this->remoteAsset($this->safeAsset(settings('cedar_remake_theme_css', '/CedarGames/_runtime/cedar-slot.css?v=5'), '/CedarGames/_runtime/cedar-slot.css?v=5'), $game),
                'game_theme_css' => $this->remoteAsset($this->safeAsset($manifest['theme_css'] ?? '', ''), $game),
            ],
            'layout' => [
                'preset' => (string) ($manifest['layout']['preset'] ?? 'custom'),
                'columns' => (int) $presentation['reels'], 'rows' => (int) $presentation['rows'],
                'lines' => (int) $presentation['lines'],
            ],
            'assets' => $this->remotePaths($this->publicAssets($manifest['assets'] ?? []), $game),
            'audio' => $this->remotePaths($this->publicAudio($manifest['audio'] ?? []), $game),
        ];
    }

    private function remoteSpin(Request $request, int $userId, string $game): array
    {
        if ((string) settings('enable_cedar_remakes', '1') !== '1'
            || !Game::where('name', $game)->where('source_type', CedarGameRegistry::SOURCE_TYPE)->where('view', 1)->exists()) {
            throw new \RuntimeException('This remake is currently disabled.');
        }
        foreach (['request_id', 'wager', 'client_seed', 'server_seed_hash'] as $field) {
            if (!is_scalar($request->input($field)) || is_bool($request->input($field))) {
                throw new \RuntimeException('The Cedar spin request is invalid.');
            }
        }
        return (new PromexCedarSettlementService())->settle(
            $userId, $game, (string) $request->input('request_id'), (string) $request->input('wager'),
            (string) $request->input('client_seed'), (string) $request->input('server_seed_hash')
        );
    }

    private function init(User $user, string $game, array $manifest, array $math, array $state): array
    {
        $last = DB::table('cedar_rounds')->where('user_id', $user->id)->where('game', $game)
            ->where('status', 'settled')->orderByDesc('created_at')->first();
        $lastProof = $last ? (json_decode($last->data, true)['result']['proof'] ?? null) : null;
        return [
            'status' => 'success', 'game' => $game, 'title' => $manifest['title'] ?? $game,
            'balance' => $this->money($user->balance), 'currency' => 'CEDARS',
            'min_bet' => (float) ($manifest['min_bet'] ?? 0.50), 'max_bet' => (float) ($manifest['max_bet'] ?? 100),
            'bet_steps' => $manifest['bet_steps'] ?? [0.5, 1, 2, 5, 10], 'max_payout' => (float) ($manifest['max_payout'] ?? 1000000),
            'math_version' => self::MATH_VERSION, 'math' => $this->publicMath($math), 'last_proof' => $lastProof,
            'server_seed_hash' => hash('sha256', $state['server_seed']), 'client_seed' => $state['client_seed'], 'nonce' => $state['nonce'],
            'brand' => [
                'name' => (string) settings('cedar_remake_brand_name', 'CEDAR'),
                'loader_css' => $this->safeAsset(settings('cedar_remake_loader_css', '/CedarGames/_runtime/loader.css'), '/CedarGames/_runtime/loader.css'),
                'theme_css' => $this->safeAsset(settings('cedar_remake_theme_css', '/CedarGames/_runtime/cedar-slot.css?v=5'), '/CedarGames/_runtime/cedar-slot.css?v=5'),
                'game_theme_css' => $this->safeAsset($manifest['theme_css'] ?? '', ''),
            ],
            'layout' => [
                'preset' => (string) ($manifest['layout']['preset'] ?? 'custom'),
                'columns' => count($math['reel_weights']),
                'rows' => (int) $math['rows'],
                'lines' => count($math['paylines']),
            ],
            'assets' => $this->publicAssets($manifest['assets'] ?? []),
            'audio' => $this->publicAudio($manifest['audio'] ?? []),
        ];
    }

    private function spin(Request $request, User $user, string $game, array $manifest, array $math, array &$state): array
    {
        if ((string) settings('enable_cedar_remakes', '1') !== '1' ||
            !Game::where('name', $game)->where('source_type', CedarGameRegistry::SOURCE_TYPE)->where('view', 1)->exists()) {
            throw new \DomainException('This remake is currently disabled.');
        }
        $requestId = $request->input('request_id');
        if (!is_string($requestId) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $requestId)) throw new \DomainException('Missing request ID. Reload the game.');
        $previous = DB::table('cedar_rounds')->where('user_id', $user->id)->where('game', $game)->where('request_id', $requestId)->first();
        if ($previous) return json_decode($previous->data, true)['result'];
        $commitment = $request->input('server_seed_hash');
        if (!is_string($commitment) || !hash_equals(hash('sha256', $state['server_seed']), $commitment)) throw new \DomainException('Seed changed. Reload the game before spinning.');
        $min = (float) ($manifest['min_bet'] ?? 0.50); $max = (float) ($manifest['max_bet'] ?? 100);
        $wager = $this->number($request->input('wager'), $min, $max);
        if (abs($wager * 100 - round($wager * 100)) > 0.00001) throw new \DomainException('Use at most two decimal places.');
        if ((float) $user->balance < $wager) throw new \DomainException('Insufficient Cedar Coins balance.');
        $client = $request->input('client_seed', $state['client_seed']);
        if (!is_string($client) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $client)) throw new \DomainException('Invalid client seed.');

        $server = $state['server_seed']; $nonce = (int) $state['nonce']; $cursor = 0;
        $rng = function (int $low, int $high) use ($server, $client, $nonce, &$cursor): int {
            return $low + CedarMath::integer($server, $client, $nonce, $high - $low + 1, $cursor++);
        };
        $grid = MultiRowSlotEngine::generateWeightedGrid($math['reel_weights'], (int) $math['rows'], $rng);
        $lineCount = count($math['paylines']);
        $evaluation = MultiRowSlotEngine::evaluateLines($grid, (int) $math['rows'], $math['paylines'], $math['paytable'], (int) $math['wild'], $wager / $lineCount);
        $maxPayout = (float) ($manifest['max_payout'] ?? 1000000);
        $win = min($maxPayout, (float) $evaluation['TotalWin']);
        $roundId = (string) Str::uuid();
        $nextServer = bin2hex(random_bytes(32));
        $parameters = $this->publicMath($math);
        $result = [
            'status' => 'success', 'bet_id' => $roundId, 'wager' => $wager, 'grid' => $grid,
            'line_wins' => $evaluation['WinLines'], 'win_amount' => $this->money($win), 'total_win' => $this->money($win),
            'server_seed' => $server, 'revealed_server_seed' => $server, 'server_seed_hash' => hash('sha256', $server),
            'next_server_seed_hash' => hash('sha256', $nextServer), 'client_seed' => $client, 'nonce' => $nonce,
            'math_version' => self::MATH_VERSION,
        ];
        $proof = [
            'bet_id' => $roundId, 'game' => $game, 'math_version' => self::MATH_VERSION,
            'server_seed' => $server, 'server_seed_hash' => hash('sha256', $server), 'client_seed' => $client, 'nonce' => $nonce,
            'parameters' => $parameters, 'wager' => $wager, 'win' => $win,
            'outcome' => ['grid' => $grid, 'total_win' => $this->money($win), 'line_wins' => $evaluation['WinLines']],
        ];
        $result['proof'] = $proof;
        $user->decrement('balance', $wager);
        AffiliateService::recordWagerCommission($user, $wager, strtolower($game), true);
        VipService::recordWagerXpAndRakeback($user, $wager, max(5.0, 100 - (float) $math['published_rtp']));
        if ($win > 0) $user->increment('balance', $win);
        $user->refresh();
        $this->audit($user, $game, $wager, $win);
        $result['balance'] = $this->money($user->balance); $result['new_balance'] = $result['balance'];
        DB::table('cedar_rounds')->insert([
            'id' => $roundId, 'user_id' => $user->id, 'game' => $game, 'request_id' => $requestId,
            'status' => 'settled', 'wager' => $wager, 'win' => $win,
            'data' => json_encode(['server_seed' => $server, 'client_seed' => $client, 'nonce' => $nonce, 'math_version' => self::MATH_VERSION, 'parameters' => $parameters, 'result' => $result], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $state = ['server_seed' => $nextServer, 'client_seed' => $client, 'nonce' => $nonce + 1, 'active' => null];
        return $result;
    }

    private function publicMath(array $math): array
    {
        return array_intersect_key($math, array_flip(['rows', 'reel_weights', 'paylines', 'paytable', 'wild', 'published_rtp', 'symbols']));
    }
    private function newState(): array { return ['server_seed' => bin2hex(random_bytes(32)), 'client_seed' => bin2hex(random_bytes(8)), 'nonce' => 1, 'active' => null]; }
    private function error(string $message): array { return ['status' => 'error', 'message' => $message]; }
    private function money($value): string { return number_format((float) $value, 2, '.', ''); }
    private function number($value, float $min, float $max): float
    {
        if (!is_scalar($value) || is_bool($value) || !is_numeric($value) || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max) throw new \DomainException("Enter a wager between {$min} and {$max}.");
        return (float) $value;
    }
    private function safeAsset($value, string $default): string
    {
        return is_string($value) && ($value === '' || preg_match('#^/[A-Za-z0-9._/-]+$#D', $value)) ? $value : $default;
    }
    private function publicAssets($value): array
    {
        if (!is_array($value)) return [];
        $assets = [];
        foreach (['background', 'mobile_background', 'frame', 'logo'] as $key) {
            $path = $this->safeAsset($value[$key] ?? '', '');
            if ($path !== '') $assets[$key] = $path;
        }
        $symbols = [];
        foreach (($value['symbols'] ?? []) as $symbol => $path) {
            if (ctype_digit((string) $symbol) && ($safe = $this->safeAsset($path, '')) !== '') $symbols[(string) $symbol] = $safe;
        }
        if ($symbols) $assets['symbols'] = $symbols;
        return $assets;
    }
    private function publicAudio($value): array
    {
        if (!is_array($value) || ($value['mode'] ?? '') !== 'files') return ['mode' => 'procedural'];
        $files = [];
        foreach (($value['files'] ?? []) as $event => $path) {
            if (is_string($event) && preg_match('/^[a-z][a-z0-9_]*$/D', $event) && ($safe = $this->safeAsset($path, '')) !== '') $files[$event] = $safe;
        }
        $medium = (float) ($value['win_tiers']['medium_multiplier'] ?? 5);
        $large = (float) ($value['win_tiers']['large_multiplier'] ?? 20);
        return ['mode' => 'files', 'files' => $files, 'win_tiers' => ['medium_multiplier' => $medium, 'large_multiplier' => $large]];
    }
    private function remoteAsset(string $path, string $game): string
    {
        if ($path === '') return '';
        $runtimePrefix = '/CedarGames/_runtime/';
        $gamePrefix = '/CedarGames/' . $game . '/';
        if (str_starts_with($path, $runtimePrefix)) return '/cedar/runtime/' . substr($path, strlen($runtimePrefix));
        if (str_starts_with($path, $gamePrefix)) return '/cedar/games/' . $game . '/' . substr($path, strlen($gamePrefix));
        return $path;
    }
    private function remotePaths(array $values, string $game): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) $values[$key] = $this->remotePaths($value, $game);
            elseif (is_string($value) && str_starts_with($value, '/')) $values[$key] = $this->remoteAsset($value, $game);
        }
        return $values;
    }
    private function audit(User $user, string $game, float $bet, float $win): void
    {
        (new StatGame(['user_id' => $user->id, 'balance' => $user->balance, 'bet' => $bet, 'win' => $win,
            'game' => $game, 'in_game' => 1, 'shop_id' => $user->shop_id ?: 1, 'date_time' => now()]))->saveQuietly();
    }
}
