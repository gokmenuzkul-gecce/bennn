<?php

namespace VanguardLTE\Casino\OroPlay;

use Illuminate\Support\Facades\Cache;

/**
 * OroPlay aggregator client (Live Casino, Slot & Mini Game API v1.1.3).
 *
 * OroPlay is a third protocol alongside the legacy loginxgames aggregator and
 * smpl core:
 *
 *  - Outbound calls authenticate with a short-lived bearer token minted from
 *    clientId/clientSecret via POST /auth/createtoken. Token creation is rate
 *    limited (5 per 30s), so the token is cached until shortly before it
 *    expires.
 *  - The operator-side seamless-wallet callbacks (POST /api/balance,
 *    /api/transaction, /api/batch-transactions) are authenticated with HTTP
 *    Basic, i.e. base64("clientId:clientSecret").
 *  - Every response is JSON shaped {"success": bool, "message": mixed,
 *    "errorCode": int}; on the wallet endpoints "message" carries the balance.
 *
 * The base URL is issued per environment by OroPlay (staging/production); the
 * staging client id is prefixed "stg-TRY-".
 */
class OroPlayClient
{
    /** Cache key holding the bearer token and its expiry. */
    private const TOKEN_CACHE_KEY = 'oroplay.bearer_token';

    /** Refresh the token this many seconds before it actually expires. */
    private const TOKEN_SKEW = 30;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->resolveConfig();
    }

    /** @return array<string, mixed> */
    private function resolveConfig(): array
    {
        $defaults = (array) config('casino_providers.oroplay', []);
        $settingsKey = (string) ($defaults['settings_key'] ?? 'casino_provider_oroplay');

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
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }

    /** @return array{configured: bool, message: string} */
    public function configStatus(): array
    {
        $missing = [];
        foreach (['base_url', 'client_id', 'client_secret'] as $field) {
            if (empty($this->config[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return ['configured' => false, 'message' => 'Eksik alanlar: ' . implode(', ', $missing)];
        }

        return ['configured' => true, 'message' => 'Yapılandırıldı · ' . $this->baseUrl()];
    }

    public function currency(): string
    {
        return (string) ($this->config['currency'] ?? 'TRY');
    }

    /** Base API URL (no trailing slash). */
    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    /** Public URL OroPlay posts wallet callbacks to (registered in the agent page). */
    public function callbackUrl(): string
    {
        $base = rtrim((string) (config('casino_providers.callback_base') ?: config('app.url')), '/');
        $path = (string) ($this->config['callback_path'] ?? '/webhooks/oroplay/api');

        return $base . '/' . ltrim($path, '/');
    }

    /** The exact Basic Authorization header OroPlay sends on wallet callbacks. */
    public function basicAuthHeader(): string
    {
        $pair = (string) $this->config['client_id'] . ':' . (string) $this->config['client_secret'];

        return 'Basic ' . base64_encode($pair);
    }

    /**
     * Validate the Basic Authorization header of an inbound callback.
     *
     * OroPlay documents the header as base64(clientId:clientSecret), which is
     * exactly what PHP exposes through PHP_AUTH_USER / PHP_AUTH_PW when the
     * request carries a Basic credential; we accept either form.
     */
    public function verifyBasic(string $header): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $expected = $this->basicAuthHeader();
        if ($header !== '' && hash_equals($expected, $header)) {
            return true;
        }

        $user = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
        $pass = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
        if ($user !== '' || $pass !== '') {
            return hash_equals((string) $this->config['client_id'], $user)
                && hash_equals((string) $this->config['client_secret'], $pass);
        }

        return false;
    }

    /**
     * Return a cached bearer token, minting a fresh one when needed.
     *
     * OroPlay caps /auth/createtoken at 5 requests per 30 seconds, so the token
     * is cached for its lifetime (minus a small skew) instead of being fetched
     * on every call.
     */
    public function token(bool $force = false): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OroPlay: client_id/client_secret eksik.');
        }

        if (!$force) {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_array($cached) && !empty($cached['token']) && (int) $cached['expires'] - self::TOKEN_SKEW > time()) {
                return (string) $cached['token'];
            }
        }

        $response = $this->request('POST', '/auth/createtoken', [
            'clientId' => (string) $this->config['client_id'],
            'clientSecret' => (string) $this->config['client_secret'],
        ], 'none');

        $body = $response['body'] ?? [];
        $token = $body['token'] ?? null;
        if (!is_string($token) || $token === '') {
            $message = $body['message'] ?? 'geçersiz yanıt';
            throw new \RuntimeException('OroPlay: token alınamadı (' . $message . ').');
        }

        $expires = (int) ($body['expiration'] ?? (time() + 1800));
        Cache::put(self::TOKEN_CACHE_KEY, ['token' => $token, 'expires' => $expires], max(60, $expires - time()));

        return $token;
    }

    /**
     * Perform an outbound API call.
     *
     * @param  'bearer'|'basic'|'none'  $auth
     * @return array{status: int, body: array<string, mixed>|null, raw: string}
     */
    public function request(string $method, string $endpoint, array $body = [], string $auth = 'bearer'): array
    {
        $url = $this->baseUrl() . '/' . ltrim($endpoint, '/');
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($auth === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        } elseif ($auth === 'basic') {
            $headers[] = 'Authorization: ' . $this->basicAuthHeader();
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (strtoupper($method) !== 'GET' && $body !== []) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('OroPlay: istek başarısız (' . $error . ').');
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => (string) $raw,
        ];
    }

    /**
     * The list of vendors (game providers) available for the integration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function vendors(): array
    {
        $response = $this->request('GET', '/vendors/list');
        $message = $response['body']['message'] ?? [];

        return is_array($message) ? array_values(array_filter($message, 'is_array')) : [];
    }

    /**
     * The games a vendor offers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function games(string $vendorCode, string $language = 'tr'): array
    {
        $response = $this->request('POST', '/games/list', [
            'vendorCode' => $vendorCode,
            'language' => $language,
        ]);
        $message = $response['body']['message'] ?? [];

        return is_array($message) ? array_values(array_filter($message, 'is_array')) : [];
    }

    /** @return array<string, mixed> */
    public function gameDetail(string $vendorCode, string $gameCode): array
    {
        $response = $this->request('POST', '/game/detail', [
            'vendorCode' => $vendorCode,
            'gameCode' => $gameCode,
        ]);

        return (array) ($response['body']['message'] ?? []);
    }

    /**
     * Initialize a real-money session and return the playable URL.
     *
     * @param  array<string, mixed>  $extra
     */
    public function launchUrl(string $vendorCode, string $gameCode, string $userCode, string $language = 'tr', array $extra = []): string
    {
        $payload = array_merge([
            'vendorCode' => $vendorCode,
            'gameCode' => $gameCode,
            'userCode' => $userCode,
            'language' => $language,
            'lobbyUrl' => (string) ($this->config['lobby_url'] ?? ''),
        ], $extra);

        $response = $this->request('POST', '/game/launch-url', $payload);
        $body = $response['body'] ?? [];

        $url = $body['message'] ?? null;
        if ((int) ($body['errorCode'] ?? -1) !== 0 || !is_string($url) || $url === '') {
            $message = is_string($url) ? $url : ($body['message'] ?? 'bilinmeyen hata');
            throw new \RuntimeException('OroPlay: oyun başlatılamadı (' . $message . ').');
        }

        return $url;
    }

    /** Register a player on the OroPlay side (Balance Transfer API). */
    public function createUser(string $userCode): bool
    {
        $response = $this->request('POST', '/user/create', ['userCode' => $userCode]);
        $body = $response['body'] ?? [];

        return (int) ($body['errorCode'] ?? -1) === 0;
    }

    /** Player balance held on the OroPlay side (Balance Transfer API). */
    public function userBalance(string $userCode): float
    {
        $response = $this->request('POST', '/user/balance', ['userCode' => $userCode]);
        $body = $response['body'] ?? [];

        return round((float) ($body['message'] ?? 0), 2);
    }

    /**
     * Verify connectivity by minting a token (and, when configured, listing
     * vendors) without moving any money.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnectivity(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => $this->configStatus()['message']];
        }

        try {
            $this->token(true);
            $vendors = $this->vendors();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Bağlantı OK · ' . count($vendors) . ' sağlayıcı'];
    }
}
