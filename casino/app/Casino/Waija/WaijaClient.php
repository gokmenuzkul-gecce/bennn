<?php

namespace VanguardLTE\Casino\Waija;

/**
 * Waija (Slotsgateway) aggregator client.
 *
 * Waija fronts 150-250+ vendors behind one credential set and speaks a simple
 * JSON-RPC-ish protocol: every outbound call is a POST to the account's base
 * URL carrying api_login/api_password and a "method" (createPlayer, getGameList,
 * getGame, addFreeRounds, ...). The response is always {error, response|message}
 * where error 0 means success.
 *
 * Seamless-wallet callbacks travel the other way: the game server GETs our
 * callback URL with action=balance|debit|credit and a signature of
 * md5(timestamp + saltkey) that must be checked against a 30-second window.
 *
 * Docs: https://documentation.waija.com
 */
class WaijaClient
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->resolveConfig();
    }

    /** Human label used in thrown error messages (overridden by subclasses). */
    protected function label(): string
    {
        return 'waija';
    }

    /** Config block key under casino_providers (overridden by subclasses). */
    protected function configKey(): string
    {
        return 'waija';
    }

    /** settings() row holding the admin overrides. */
    protected function defaultSettingsKey(): string
    {
        return 'casino_provider_waija';
    }

    /** Callback path this aggregator is registered with. */
    protected function defaultCallbackPath(): string
    {
        return '/webhooks/waija/callbacks';
    }

    /** @return array<string, mixed> */
    private function resolveConfig(): array
    {
        $defaults = (array) config('casino_providers.' . $this->configKey(), []);
        $settingsKey = (string) ($defaults['settings_key'] ?? $this->defaultSettingsKey());

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
     * Enough to mint sessions and pull the catalogue (base URL + credentials).
     *
     * The salt key is deliberately NOT required here: it only signs inbound
     * wallet callbacks. Gating the whole client on it silently disabled the
     * catalogue sync and every launch until the operator had the salt.
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['base_url'])
            && !empty($this->config['api_login'])
            && !empty($this->config['api_password']);
    }

    /** Whether inbound wallet callbacks can be signature-verified. */
    public function canVerifyCallbacks(): bool
    {
        return $this->isConfigured() && $this->saltKey() !== '';
    }

    /** @return array{configured: bool, message: string} */
    public function configStatus(): array
    {
        $missing = [];
        foreach (['base_url', 'api_login', 'api_password'] as $field) {
            if (empty($this->config[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return ['configured' => false, 'message' => 'Eksik alanlar: ' . implode(', ', $missing)];
        }

        $message = 'Yapılandırıldı · ' . $this->baseUrl();
        if ($this->saltKey() === '') {
            $message .= ' (uyarı: salt_key boş — gelen callback imzaları doğrulanamaz)';
        }

        return ['configured' => true, 'message' => $message];
    }

    public function currency(): string
    {
        return strtoupper((string) ($this->config['currency'] ?? 'TRY'));
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    public function saltKey(): string
    {
        return (string) ($this->config['salt_key'] ?? '');
    }

    /**
     * Optional operator brand tag Waija echoes back in the launch payload.
     *
     * Sent only when configured; Waija rejects an empty string.
     */
    public function branded(): ?string
    {
        $branded = (string) ($this->config['branded'] ?? '');

        return $branded !== '' ? $branded : null;
    }

    /** Public URL Waija GETs its wallet callbacks to. */
    public function callbackUrl(): string
    {
        $base = rtrim((string) (config('casino_providers.callback_base') ?: config('app.url')), '/');
        $path = (string) ($this->config['callback_path'] ?? $this->defaultCallbackPath());

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Recreate the callback signature: md5(timestamp + saltkey).
     *
     * Waija's validation page is explicit about the order and the hash.
     */
    public function sign(string $timestamp): string
    {
        return md5($timestamp . $this->saltKey());
    }

    /**
     * Verify an inbound callback signature and its freshness.
     *
     * The timestamp must be within the last 30 seconds, which is what stops a
     * captured callback from being replayed.
     */
    public function verify(string $timestamp, string $key): bool
    {
        $salt = $this->saltKey();
        if ($salt === '' || $timestamp === '' || $key === '') {
            return false;
        }

        if (!ctype_digit($timestamp)) {
            return false;
        }

        $drift = abs(time() - (int) $timestamp);
        if ($drift > (int) ($this->config['signature_window'] ?? 30)) {
            return false;
        }

        return hash_equals($this->sign($timestamp), strtolower(trim($key)));
    }

    /**
     * Perform a signed outbound method call.
     *
     * The reference SDK (slotsgateway/slotsgateway-php-client) posts these as
     * form parameters, so the body is application/x-www-form-urlencoded by
     * default; set `request_format=json` only if the account requires JSON.
     *
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>|null, raw: string}
     */
    public function call(string $method, array $params = []): array
    {
        $payload = array_merge([
            'api_login' => (string) ($this->config['api_login'] ?? ''),
            'api_password' => (string) ($this->config['api_password'] ?? ''),
            'method' => $method,
        ], $params);

        $asJson = strtolower((string) ($this->config['request_format'] ?? 'form')) === 'json';

        $ch = curl_init($this->baseUrl());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                $asJson ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $asJson ? json_encode($payload) : http_build_query($payload),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException($this->label() . ': istek başarısız (' . $error . ').');
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => (string) $raw,
        ];
    }

    /**
     * Ensure the player exists on the Waija side.
     *
     * createPlayer is the first real-money call and is safe to repeat: when the
     * player already exists Waija redirects internally to playerExists and still
     * answers with a successful player payload.
     *
     * @return array<string, mixed>|null
     */
    public function createPlayer(string $username, string $password, ?string $nickname = null): ?array
    {
        $params = [
            'user_username' => $username,
            'user_password' => $password,
            'currency' => strtoupper($this->currency()),
        ];
        if ($nickname !== null && $nickname !== '') {
            $params['user_nickname'] = $nickname;
        }

        $response = $this->call('createPlayer', $params);

        return $response['body']['response'] ?? null;
    }

    /**
     * Add free rounds (free spins) to a player for a game.
     *
     * @return array<string, mixed>
     */
    public function addFreeRounds(string $username, string $password, string $gameId, int $freespins, float $betLevel, string $lang = 'tr'): array
    {
        return $this->call('addFreeRounds', [
            'lang' => $lang,
            'user_username' => $username,
            'user_password' => $password,
            'gameid' => $gameId,
            'freespins' => $freespins,
            'bet_level' => $betLevel,
            'currency' => strtoupper($this->currency()),
        ])['body'] ?? [];
    }

    /** @return array<string, mixed> */
    public function getFreeRounds(string $username, string $password): array
    {
        return $this->call('getFreeRounds', [
            'user_username' => $username,
            'user_password' => $password,
            'currency' => strtoupper($this->currency()),
        ])['body'] ?? [];
    }

    /** @return array<string, mixed> */
    public function deleteFreeRounds(string $username, string $password, string $gameId): array
    {
        return $this->call('deleteFreeRounds', [
            'gameid' => $gameId,
            'user_username' => $username,
            'user_password' => $password,
            'currency' => strtoupper($this->currency()),
        ])['body'] ?? [];
    }

    /** @return array<string, mixed> */
    public function deleteAllFreeRounds(string $username, string $password): array
    {
        return $this->call('deleteAllFreeRounds', [
            'user_username' => $username,
            'user_password' => $password,
            'currency' => strtoupper($this->currency()),
        ])['body'] ?? [];
    }

    /**
     * Open a fun-play (demo) session.
     *
     * No player account and no wallet callbacks are involved, so this is the
     * cleanest way to prove a game id launches before the account is unlocked
     * for real money.
     *
     * @param  array<string, mixed>  $extra
     * @return array{url: string, session_id: string}
     */
    public function demo(string $gameId, string $lang = 'tr', array $extra = []): array
    {
        $payload = array_merge([
            'gameid' => $gameId,
            'homeurl' => (string) ($this->config['home_url'] ?? config('app.url')),
            'cashierurl' => (string) ($this->config['cashier_url'] ?? config('app.url')),
            'lang' => $lang,
            'currency' => strtoupper($this->currency()),
        ], $this->launchContext(), $this->branded() !== null ? ['branded' => $this->branded()] : [], $extra);

        $response = $this->call('getGameDemo', $payload);
        $body = $response['body'] ?? [];

        if ($this->failed($response)) {
            throw new \RuntimeException($this->launchErrorMessage((string) ($body['message'] ?? ('http ' . $response['status']))));
        }

        return [
            'url' => (string) ($this->extractLaunchUrl($body) ?? ''),
            'session_id' => (string) ($body['session_id'] ?? ''),
        ];
    }

    /**
     * Normalise Waija's error field.
     *
     * Success is the integer 0. Failures are usually an integer code, but auth
     * and transport failures come back as a string ("Unauthorized"), and a
     * plain (int) cast turns that into 0 — which reads as success and silently
     * returns an empty catalogue. Any non-numeric value counts as an error.
     *
     * @param  array<string, mixed>  $body
     */
    private function errorCode(array $body): int
    {
        $error = $body['error'] ?? -1;

        if (is_int($error)) {
            return $error;
        }

        if (is_string($error) && is_numeric($error)) {
            return (int) $error;
        }

        return $error === 0 ? 0 : 1;
    }

    /** @param array{status: int, body: array<string, mixed>|null, raw: string} $response */
    private function failed(array $response): bool
    {
        return $response['status'] >= 400
            || $this->errorCode($response['body'] ?? []) !== 0;
    }

    /**
     * Full catalogue for the account currency.
     *
     * Each row carries id_hash (the launch id), name, category/vendor and image
     * URLs. Waija asks operators to cache this server-side.
     *
     * @return array<int, array<string, mixed>>
     */
    public function games(): array
    {
        $response = $this->call('getGameList', [
            'show_additional' => true,
            'show_systems' => 0,
            'list_type' => 1,
            'currency' => $this->currency(),
        ]);

        $body = $response['body'] ?? [];
        if ($this->failed($response)) {
            $message = $body['message'] ?? ('http ' . $response['status']);
            throw new \RuntimeException($this->label() . ': gamelist reddedildi (' . $message . ').');
        }

        $list = $body['response'] ?? [];

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * Open a real-money session and return the playable URL.
     *
     * @param  array<string, mixed>  $extra
     */
    public function launch(string $username, string $password, string $gameId, array $extra = []): string
    {
        $payload = array_merge([
            'lang' => (string) ($extra['lang'] ?? 'tr'),
            'user_username' => $username,
            'user_password' => $password,
            'gameid' => $gameId,
            'homeurl' => (string) ($this->config['home_url'] ?? config('app.url')),
            'cashierurl' => (string) ($this->config['cashier_url'] ?? config('app.url')),
            'play_for_fun' => 0,
            'currency' => strtoupper($this->currency()),
        ], $this->launchContext(), $this->branded() !== null ? ['branded' => $this->branded()] : [], $extra);

        $response = $this->call('getGame', $payload);
        $body = $response['body'] ?? [];

        $url = $this->extractLaunchUrl($body);
        if ($this->failed($response) || $url === null) {
            $message = $body['message'] ?? 'geçersiz yanıt';
            throw new \RuntimeException($this->launchErrorMessage((string) $message));
        }

        return $url;
    }

    /**
     * Turn a vendor launch refusal into a message a player can act on.
     *
     * The aggregator's most common refusal is its hourly per-account launch cap
     * ("Too many game launches — ..."). It is transient and not the player's
     * fault, so it is surfaced in Turkish rather than the raw English vendor
     * text, which otherwise reads as a broken game.
     */
    protected function launchErrorMessage(string $message): string
    {
        $needle = strtolower($message);
        if (str_contains($needle, 'too many') || str_contains($needle, 'launch limit')) {
            return 'Şu anda çok fazla oyun açılıyor; lütfen birkaç dakika sonra tekrar deneyin.';
        }

        return $this->label() . ': oyun başlatılamadı (' . $message . ').';
    }

    /**
     * Extra getGame fields the docs say some studios require.
     *
     * `device` is mandatory for platforms that refuse a launch without it, and
     * `country` (ISO 3166 alpha-2) is enforced for accounts serving a
     * restricted market. Both are optional and only sent when configured, so
     * studios that reject unknown fields are unaffected.
     *
     * @return array<string, string>
     */
    protected function launchContext(): array
    {
        $context = [];

        $device = $this->detectDevice();
        if ($device !== null) {
            $context['device'] = $device;
        }

        $country = strtoupper(trim((string) ($this->config['country'] ?? '')));
        if ($country !== '') {
            $context['country'] = $country;
        }

        return $context;
    }

    /**
     * Which build to ask the studio for.
     *
     * Derived from the launching request's user agent so a phone gets the
     * vendor's mobile build — a desktop build on a phone is what makes live
     * tables fail to open or render without video. The configured value is only
     * a fallback for contexts with no request (CLI, queue).
     */
    protected function detectDevice(): ?string
    {
        try {
            $ua = (string) (request()->userAgent() ?? '');
            if ($ua !== '') {
                $detect = new \Detection\MobileDetect();
                $detect->setUserAgent($ua);

                return $detect->isMobile() ? 'mobile' : 'desktop';
            }
        } catch (\Throwable $e) {
            // No request bound (CLI/queue): fall through to config.
        }

        $configured = strtolower(trim((string) ($this->config['device'] ?? '')));

        return in_array($configured, ['desktop', 'mobile'], true) ? $configured : null;
    }

    /**
     * Pull the playable URL out of a getGame/getGameDemo reply.
     *
     * Most Waija-style studios answer with the URL as a bare string, but some —
     * notably aggregators fronting Hub-hosted slots and live-dealer tables —
     * wrap it in an object: {"gameurl":"..."}. Accept both shapes.
     *
     * @param  array<string, mixed>  $body
     */
    protected function extractLaunchUrl(array $body): ?string
    {
        $response = $body['response'] ?? null;

        if (is_string($response)) {
            return $response !== '' ? $response : null;
        }

        if (is_array($response)) {
            foreach (['gameurl', 'game_url', 'url'] as $key) {
                $value = $response[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    public function testConnectivity(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => $this->configStatus()['message']];
        }

        try {
            $response = $this->call('getGameList', ['list_type' => 1, 'currency' => $this->currency()]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $this->label() . ': ' . $e->getMessage()];
        }

        if ($response['status'] >= 200 && $response['status'] < 500) {
            return ['success' => true, 'message' => 'Sunucuya ulaşıldı (HTTP ' . $response['status'] . ')'];
        }

        return ['success' => false, 'message' => $this->label() . ': beklenmeyen yanıt (HTTP ' . $response['status'] . ')'];
    }
}
