<?php

namespace VanguardLTE\Casino\Aggregator01;

/**
 * 01.tech Aggregator (A8R) client.
 *
 * A8R fronts many game providers behind one credential set and speaks Twirp
 * Wire Protocol v7: every call is an HTTP POST to "<base>/v2/<Service>/<Method>"
 * with an application/json body, signed by hex HMAC-SHA256 over the exact raw
 * body, keyed by AUTH_TOKEN and carried in the "X-REQUEST-SIGN" header. The
 * reply is JSON; failures are Twirp errors holding the real code in meta.api_code.
 *
 * Outbound (Casino -> Aggregator) calls used here:
 *   - casino_a8r.Game/List       catalogue (slots + live tables)
 *   - casino_a8r.Launcher/Real   real-money session URL
 *   - casino_a8r.Launcher/Demo   fun-play session URL
 *
 * The wallet travels the other way (a8r_casino.Player/Balance, Round/BetWin,
 * Round/Rollback, Round/Finish); the Aggregator signs those the same way and the
 * webhook verifies them with verifyWebhook().
 *
 * Docs: https://docs.aggregator.01.tech/overview
 */
class Aggregator01Client
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->resolveConfig();
    }

    /** @return array<string, mixed> */
    private function resolveConfig(): array
    {
        $defaults = (array) config('casino_providers.aggregator01', []);
        $settingsKey = (string) ($defaults['settings_key'] ?? 'casino_provider_aggregator01');

        $overrides = [];
        if (function_exists('settings')) {
            $stored = settings($settingsKey);
            if (is_string($stored) && $stored !== '') {
                $decoded = json_decode($stored, true);
                if (is_array($decoded)) {
                    $overrides = $decoded;
                }
            }
        }

        $config = array_merge($defaults, array_filter($overrides, static fn ($v) => $v !== null && $v !== ''));
        $config['settings_key'] = $settingsKey;

        return $config;
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Enough to pull the catalogue and mint sessions: base URL + AUTH_TOKEN.
     *
     * The AUTH_TOKEN signs both directions, so it is mandatory. The casino_id is
     * defaulted ("gecce") rather than gating, so a half-configured account still
     * shows a useful status in Liteback.
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['base_url'])
            && !empty($this->config['auth_token']);
    }

    /** @return array{configured: bool, message: string} */
    public function configStatus(): array
    {
        $missing = [];
        foreach (['base_url', 'auth_token'] as $field) {
            if (empty($this->config[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return ['configured' => false, 'message' => 'Eksik alanlar: ' . implode(', ', $missing)];
        }

        return ['configured' => true, 'message' => 'Yapılandırıldı · ' . $this->baseUrl() . ' (casino_id: ' . $this->casinoId() . ')'];
    }

    public function currency(): string
    {
        return strtoupper((string) ($this->config['currency'] ?? 'TRY'));
    }

    public function casinoId(): string
    {
        return (string) ($this->config['casino_id'] ?? 'gecce');
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    /** Public URL the Aggregator posts seamless-wallet callbacks to. */
    public function callbackUrl(): string
    {
        $base = rtrim((string) (config('casino_providers.callback_base') ?: config('app.url')), '/');
        $path = (string) ($this->config['callback_path'] ?? '/webhooks/aggregator01/callbacks');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Hex HMAC-SHA256 over a raw body, keyed by AUTH_TOKEN.
     *
     * Used both to sign our outbound calls and to verify inbound callbacks.
     */
    public function signBody(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, (string) ($this->config['auth_token'] ?? ''));
    }

    /**
     * Verify the "X-REQUEST-SIGN" header of an inbound callback.
     *
     * The HMAC is computed over the exact raw request body, so the caller must
     * pass the untouched bytes (never a re-encoded array).
     */
    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $token = (string) ($this->config['auth_token'] ?? '');
        $signature = strtolower(trim($signature));
        if ($token === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->signBody($rawBody), $signature);
    }

    /**
     * Perform a signed outbound Twirp call.
     *
     * @param  string  $service  e.g. "casino_a8r.Game"
     * @param  string  $method   e.g. "List"
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>|null, raw: string}
     */
    public function call(string $service, string $method, array $payload = []): array
    {
        $raw = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $url = $this->baseUrl() . '/v2/' . $service . '/' . $method;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-REQUEST-SIGN: ' . $this->signBody($raw),
            ],
            CURLOPT_POSTFIELDS => $raw,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('01.tech: istek başarısız (' . $error . ').');
        }

        $decoded = json_decode((string) $body, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => (string) $body,
        ];
    }

    /**
     * True when a Twirp reply carries an error.
     *
     * Success is HTTP 200 with no "code" field. A failure is a 4xx/5xx carrying
     * {code, msg, meta.api_code}; any non-2xx status counts as an error.
     *
     * @param  array{status: int, body: array<string, mixed>|null, raw: string}  $response
     */
    private function failed(array $response): bool
    {
        return $response['status'] < 200
            || $response['status'] >= 300
            || isset(($response['body'] ?? [])['code']);
    }

    /**
     * Human message from a Twirp error reply (meta.api_message preferred).
     *
     * @param  array{status: int, body: array<string, mixed>|null, raw: string}  $response
     */
    private function errorMessage(array $response): string
    {
        $body = $response['body'] ?? [];
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];

        return (string) ($meta['api_message'] ?? $meta['message'] ?? $body['msg'] ?? $body['code'] ?? ('HTTP ' . $response['status']));
    }

    /**
     * Full catalogue, flattened to one row per game.
     *
     * The reply nests games under providers: {providers:[{name, games:[...]}]}.
     * Each game carries the launcher id (`id`), a per-provider id2, a `provider`,
     * `category`, `live` flag, cover art (image_assets.base_url + game.images)
     * and release/recall dates. We flatten, drop unreleased/recalled games and
     * build absolute cover URLs from the image presets.
     *
     * @return array<int, array<string, mixed>>
     */
    public function games(): array
    {
        $response = $this->call('casino_a8r.Game', 'List', ['casino_id' => $this->casinoId()]);
        if ($this->failed($response)) {
            throw new \RuntimeException('01.tech: gamelist reddedildi (' . $this->errorMessage($response) . ').');
        }

        $providers = $response['body']['providers'] ?? [];
        if (!is_array($providers)) {
            return [];
        }

        $now = time();
        $games = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $providerName = (string) ($provider['name'] ?? '');
            $imageAssets = is_array($provider['image_assets'] ?? null) ? $provider['image_assets'] : [];
            $baseUrl = rtrim((string) ($imageAssets['base_url'] ?? ''), '/');

            foreach (($provider['games'] ?? []) as $game) {
                if (!is_array($game)) {
                    continue;
                }

                $id = (string) ($game['id'] ?? '');
                $title = trim((string) ($game['title'] ?? ''));
                if ($id === '' || $title === '') {
                    continue;
                }

                // Skip games that are not yet released or already recalled.
                if ($this->parseTime($game['released_at'] ?? null, $now, 'after')
                    || $this->parseTime($game['recalled_at'] ?? null, $now, 'before')) {
                    continue;
                }

                $games[] = [
                    'gameid' => $id,
                    'symbol' => preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?: $id,
                    'name' => $title,
                    'icon' => $this->imageUrl($baseUrl, is_array($game['images'] ?? null) ? $game['images'] : []),
                    'vendor' => (string) ($game['provider'] ?? $providerName),
                    'producer' => (string) ($game['producer'] ?? ''),
                    'live' => (bool) ($game['live'] ?? false),
                    'category' => (string) ($game['category'] ?? ''),
                    'type' => $this->mapCategory((string) ($game['category'] ?? ''), (bool) ($game['live'] ?? false)),
                ];
            }
        }

        return $games;
    }

    /**
     * Pick the best available cover preset and build an absolute URL.
     *
     * Prefers horizontal for the lobby grid, then square, widescreen, vertical.
     *
     * @param  array<string, mixed>  $images
     */
    private function imageUrl(string $baseUrl, array $images): string
    {
        if ($baseUrl === '' || !is_array($images)) {
            return '';
        }

        foreach (['horizontal', 'square', 'widescreen', 'vertical'] as $preset) {
            $path = $images[$preset] ?? null;
            if (is_string($path) && $path !== '') {
                return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
            }
        }

        return '';
    }

    /**
     * Interpret a released_at / recalled_at timestamp against now.
     *
     * Returns true when the game should be considered unavailable: released_at
     * in the future, or recalled_at in the past.
     */
    private function parseTime(mixed $value, int $now, string $direction): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return false;
        }

        return $direction === 'after' ? $ts > $now : $ts < $now;
    }

    /**
     * Map A8R's category (plus the live flag) onto Game::GAME_TYPES.
     *
     * The gamelist categories are card/casual/crash/craps/fishing/lottery/mines/
     * poker/roulette/scratch/slots/video_poker/virtual_sports, plus live tables
     * flagged live=true. Unknown values fall back to slots.
     */
    private function mapCategory(string $category, bool $live): string
    {
        if ($live) {
            return 'live';
        }

        $category = strtolower(trim($category));

        return match ($category) {
            'slots' => 'slots',
            'crash' => 'crash',
            'fishing' => 'fishing',
            'card', 'poker', 'roulette', 'craps', 'video_poker' => 'table',
            'lottery', 'scratch', 'mines' => 'instant',
            'virtual_sports' => 'virtual',
            'casual' => 'arcade',
            default => 'slots',
        };
    }

    /**
     * Mint a real-money session URL.
     *
     * @param  array<string, mixed>  $player  per-player overrides (email, name, dob, ...)
     * @param  array<string, mixed>  $extra
     */
    public function launchReal(string $game, string $playerId, array $player = [], array $extra = []): string
    {
        $payload = array_merge([
            'casino_id' => $this->casinoId(),
            'client_type' => 'desktop',
            'game' => $game,
            'ip' => '127.0.0.1',
            'jurisdiction' => (string) ($this->config['jurisdiction'] ?? 'TR'),
            'locale' => (string) ($this->config['locale'] ?? 'tr'),
            'player' => $this->playerPayload($playerId, $player),
            'urls' => [
                'deposit_url' => (string) ($this->config['deposit_url'] ?? rtrim((string) config('app.url'), '/')),
                'return_url' => (string) ($this->config['return_url'] ?? rtrim((string) config('app.url'), '/')),
            ],
        ], $extra);

        return $this->launch('Real', $payload);
    }

    /**
     * Mint a fun-play (demo) session URL.
     *
     * @param  array<string, mixed>  $extra
     */
    public function launchDemo(string $game, array $extra = []): string
    {
        $payload = array_merge([
            'casino_id' => $this->casinoId(),
            'client_type' => 'desktop',
            'game' => $game,
            'ip' => '127.0.0.1',
            'locale' => (string) ($this->config['locale'] ?? 'tr'),
        ], $extra);

        return $this->launch('Demo', $payload);
    }

    /** @param array<string, mixed> $payload */
    private function launch(string $method, array $payload): string
    {
        $response = $this->call('casino_a8r.Launcher', $method, $payload);
        $url = $response['body']['launch_url'] ?? null;

        if ($this->failed($response) || !is_string($url) || $url === '') {
            throw new \RuntimeException('01.tech: oyun başlatılamadı (' . $this->errorMessage($response) . ').');
        }

        return $url;
    }

    /**
     * Build the `player` object shared by Launcher/Real and Freespins/Issue.
     *
     * Every field the Aggregator marks required is always present (with sane
     * defaults), and any real value the caller passes overrides the default.
     *
     * @param  array<string, mixed>  $player
     * @return array<string, mixed>
     */
    private function playerPayload(string $playerId, array $player = []): array
    {
        return array_merge([
            'id' => $playerId,
            'currency' => $this->currency(),
            'country' => (string) ($this->config['country'] ?? 'TR'),
            'firstname' => 'Player',
            'lastname' => $playerId,
            'nickname' => $playerId,
            'gender' => 'm',
            'email' => (string) ($this->config['default_email'] ?? 'player@casino.local'),
            'date_of_birth' => (string) ($this->config['default_date_of_birth'] ?? '1990-01-01T00:00:00Z'),
            'registered_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $this->playerOverrides($player));
    }

    /**
     * Issue a free spins campaign (casino_a8r.Freespins/Issue).
     *
     * The player gets `freespins_quantity` free rounds in total across the listed
     * games (not per game), so we issue one game at a time by default.
     *
     * @param  string[]  $games   Aggregator game ids ("provider:game")
     * @param  array<string, mixed>  $player  per-player fields (id required)
     * @return array{success: bool, message: string, raw: string}
     */
    public function issueFreespins(string $issueId, int $quantity, string $betAmount, array $games, array $player, string $validUntil): array
    {
        $payload = [
            'casino_id' => $this->casinoId(),
            'issue_id' => $issueId,
            'freespins_quantity' => $quantity,
            'bet_amount' => $betAmount,
            'games' => array_values($games),
            'valid_until' => $validUntil,
            'player' => $this->playerPayload((string) ($player['id'] ?? ''), $player),
        ];

        return $this->freespinsCall('Issue', $payload);
    }

    /**
     * Cancel a free spins campaign (casino_a8r.Freespins/Cancel).
     *
     * @return array{success: bool, message: string, raw: string}
     */
    public function cancelFreespins(string $issueId, string $provider): array
    {
        return $this->freespinsCall('Cancel', [
            'casino_id' => $this->casinoId(),
            'issue_id' => $issueId,
            'provider' => $provider,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, raw: string}
     */
    private function freespinsCall(string $method, array $payload): array
    {
        $response = $this->call('casino_a8r.Freespins', $method, $payload);

        if ($this->failed($response)) {
            return ['success' => false, 'message' => $this->errorMessage($response), 'raw' => $response['raw']];
        }

        return ['success' => true, 'message' => '', 'raw' => $response['raw']];
    }

    /**
     * Only pass through player fields the local user actually has.
     *
     * @param  array<string, mixed>  $player
     * @return array<string, mixed>
     */
    private function playerOverrides(array $player): array
    {
        $allowed = ['country', 'currency', 'date_of_birth', 'email', 'firstname', 'gender', 'lastname', 'nickname', 'registered_at', 'tags'];
        $overrides = [];
        foreach ($allowed as $key) {
            if (isset($player[$key]) && $player[$key] !== '') {
                $overrides[$key] = $player[$key];
            }
        }

        return $overrides;
    }

    /** @return array{success: bool, message: string} */
    public function testConnectivity(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => $this->configStatus()['message']];
        }

        try {
            $response = $this->call('casino_a8r.Game', 'List', ['casino_id' => $this->casinoId()]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '01.tech: ' . $e->getMessage()];
        }

        if ($response['status'] >= 200 && $response['status'] < 300 && !isset(($response['body'] ?? [])['code'])) {
            return ['success' => true, 'message' => 'Sunucuya ulaşıldı · ' . $this->baseUrl()];
        }

        return ['success' => false, 'message' => '01.tech: ' . $this->errorMessage($response)];
    }
}
