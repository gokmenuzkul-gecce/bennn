<?php

namespace VanguardLTE\Casino\Gregmorn;

/**
 * Gregmorn Hub aggregator client.
 *
 * Gregmorn Hub is the multi-vendor aggregator documented at docs.gregmorn.org
 * (slots and live-casino tables from many providers behind one credential set).
 * It speaks a protocol of its own:
 *
 *  - Outbound: POST /auth/login (form-encoded) mints a short-lived JWT;
 *    GET /users/{user_id}/getUserGames/{currency} lists the catalogue;
 *    POST /games/openGame (JSON + X-Signature) returns a playable session URL.
 *  - Inbound (seamless wallet): the Hub POSTs getBalance / writeBet / rollback
 *    to the callback URL with an X-Signature that is hex HMAC-SHA256 over the
 *    raw JSON body, keyed by the account secret.
 */
class GregmornClient
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, mixed>|null */
    private ?array $auth = null;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->resolveConfig();
    }

    /** @return array<string, mixed> */
    private function resolveConfig(): array
    {
        $defaults = (array) config('casino_providers.gregmorn', []);
        $settingsKey = (string) ($defaults['settings_key'] ?? 'casino_provider_gregmorn');

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

    public function isConfigured(): bool
    {
        return !empty($this->config['login'])
            && !empty($this->config['password'])
            && !empty($this->config['secret_key']);
    }

    /** @return array{configured: bool, message: string} */
    public function configStatus(): array
    {
        $missing = [];
        foreach (['office_base_url', 'client_base_url', 'login', 'password', 'secret_key'] as $field) {
            if (empty($this->config[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return ['configured' => false, 'message' => 'Eksik alanlar: ' . implode(', ', $missing)];
        }

        return ['configured' => true, 'message' => 'Yapılandırıldı · ' . $this->officeBaseUrl()];
    }

    public function currency(): string
    {
        return (string) ($this->config['currency'] ?? 'TRY');
    }

    public function officeBaseUrl(): string
    {
        return rtrim((string) ($this->config['office_base_url'] ?? ''), '/');
    }

    public function clientBaseUrl(): string
    {
        return rtrim((string) ($this->config['client_base_url'] ?? ''), '/');
    }

    /** Public URL the Hub posts seamless-wallet callbacks to. */
    public function callbackUrl(): string
    {
        $base = rtrim((string) (config('casino_providers.callback_base') ?: config('app.url')), '/');
        $path = (string) ($this->config['callback_path'] ?? '/webhooks/gregmorn/callbacks');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Hex HMAC-SHA256 over a raw body, keyed by the account secret. Used both to
     * sign our openGame request and to verify inbound wallet callbacks.
     */
    public function signBody(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, (string) ($this->config['secret_key'] ?? ''));
    }

    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) ($this->config['secret_key'] ?? '');
        $signature = strtolower(trim($signature));
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->signBody($rawBody), $signature);
    }

    /**
     * Log in and cache the JWT for its short TTL.
     *
     * @return array<string, mixed>
     */
    public function auth(): array
    {
        if ($this->auth !== null) {
            return $this->auth;
        }

        $cacheKey = 'casino_gregmorn_auth_' . md5((string) ($this->config['login'] ?? ''));
        try {
            $cached = cache()->get($cacheKey);
            if (is_array($cached) && !empty($cached['accessToken'])) {
                return $this->auth = $cached;
            }
        } catch (\Throwable) {
            // No cache backend available (e.g. bare CLI): fall through to login.
        }

        $auth = $this->loginRequest();
        try {
            cache()->put($cacheKey, $auth, now()->addMinutes(10));
        } catch (\Throwable) {
            // Caching is an optimisation only.
        }

        return $this->auth = $auth;
    }

    /** @return array<string, mixed> */
    private function loginRequest(): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('gregmorn: ' . $this->configStatus()['message']);
        }

        $response = $this->http(
            'POST',
            $this->officeBaseUrl() . '/auth/login',
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            http_build_query([
                'login' => (string) $this->config['login'],
                'password' => (string) $this->config['password'],
            ])
        );

        $body = $response['body'];
        if ($response['status'] !== 200 || !is_array($body) || empty($body['accessToken'])) {
            $message = is_array($body) ? ($body['message'] ?? $body['error'] ?? 'yetkilendirme başarısız') : 'geçersiz yanıt';
            throw new \RuntimeException('gregmorn: giriş başarısız (' . $message . ').');
        }

        return $body;
    }

    /** Operator user id from config, otherwise the id returned by /auth/login. */
    public function userId(): string
    {
        $configured = (string) ($this->config['user_id'] ?? '');
        if ($configured !== '') {
            return $configured;
        }

        $auth = $this->auth();

        return (string) ($auth['user']['id'] ?? '');
    }

    /**
     * Fetch the full catalogue (slots + live tables) for the account currency.
     *
     * @return array<int, array<string, mixed>>
     */
    public function games(): array
    {
        $token = (string) ($this->auth()['accessToken'] ?? '');
        $userId = $this->userId();
        if ($token === '' || $userId === '') {
            throw new \RuntimeException('gregmorn: oyun listesi için oturum bilgisi eksik.');
        }

        $url = $this->officeBaseUrl()
            . '/users/' . rawurlencode($userId)
            . '/getUserGames/' . rawurlencode($this->currency());

        $response = $this->http('GET', $url, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ]);

        $body = $response['body'];
        if ($response['status'] !== 200) {
            $message = is_array($body) ? ($body['message'] ?? $body['error'] ?? 'liste alınamadı') : 'geçersiz yanıt';
            throw new \RuntimeException('gregmorn: oyun listesi alınamadı (HTTP ' . $response['status'] . ', ' . $message . ').');
        }

        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            $body = $body['data'];
        }

        return is_array($body) ? array_values(array_filter($body, 'is_array')) : [];
    }

    /**
     * Mint a real-money session URL for a player and game.
     *
     * @param  array<string, mixed>  $extra
     */
    public function openGame(string $playerLogin, string $gameId, array $extra = []): string
    {
        $payload = array_merge([
            'currency' => $this->currency(),
            'demo' => '0',
            'exitUrl' => (string) ($this->config['exit_url'] ?? rtrim((string) config('app.url'), '/')),
            'gameId' => $gameId,
            'language' => 'tr',
            'player_login' => $playerLogin,
            'user_id' => $this->userId(),
        ], $extra);

        $raw = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->http('POST', $this->clientBaseUrl() . '/games/openGame', [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Signature: ' . $this->signBody($raw),
        ], $raw);

        $body = $response['body'];
        $url = is_array($body) ? ($body['content']['game']['url'] ?? null) : null;

        if ($response['status'] !== 200 || !is_string($url) || $url === '') {
            $message = is_array($body) ? ($body['error'] ?? $body['message'] ?? 'bilinmeyen hata') : 'geçersiz yanıt';
            throw new \RuntimeException('gregmorn: oyun başlatılamadı (' . $message . ').');
        }

        return $url;
    }

    /** @return array{success: bool, message: string} */
    public function testConnectivity(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => $this->configStatus()['message']];
        }

        try {
            $this->auth();

            return ['success' => true, 'message' => 'Giriş başarılı · ' . $this->officeBaseUrl()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{status: int, body: array<string, mixed>|null, raw: string}
     */
    private function http(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('gregmorn: istek başarısız (' . $error . ').');
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => (string) $raw,
        ];
    }
}
