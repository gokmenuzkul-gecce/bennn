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

    /** @return array<string, mixed> */
    private function resolveConfig(): array
    {
        $defaults = (array) config('casino_providers.waija', []);
        $settingsKey = (string) ($defaults['settings_key'] ?? 'casino_provider_waija');

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
        return !empty($this->config['base_url'])
            && !empty($this->config['api_login'])
            && !empty($this->config['api_password'])
            && !empty($this->config['salt_key']);
    }

    /** @return array{configured: bool, message: string} */
    public function configStatus(): array
    {
        $missing = [];
        foreach (['base_url', 'api_login', 'api_password', 'salt_key'] as $field) {
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

    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    public function saltKey(): string
    {
        return (string) ($this->config['salt_key'] ?? '');
    }

    /** Public URL Waija GETs its wallet callbacks to. */
    public function callbackUrl(): string
    {
        $base = rtrim((string) (config('casino_providers.callback_base') ?: config('app.url')), '/');
        $path = (string) ($this->config['callback_path'] ?? '/webhooks/waija/callbacks');

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

        $ch = curl_init($this->baseUrl());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('waija: istek başarısız (' . $error . ').');
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
    public function createPlayer(string $username, string $password): ?array
    {
        $response = $this->call('createPlayer', [
            'user_username' => $username,
            'user_password' => $password,
            'currency' => $this->currency(),
        ]);

        return $response['body']['response'] ?? null;
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
        if ((int) ($body['error'] ?? -1) !== 0) {
            $message = $body['message'] ?? 'bilinmeyen hata';
            throw new \RuntimeException('waija: gamelist reddedildi (' . $message . ').');
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
            'currency' => $this->currency(),
        ], $extra);

        $response = $this->call('getGame', $payload);
        $body = $response['body'] ?? [];

        $url = $body['response'] ?? null;
        if ((int) ($body['error'] ?? -1) !== 0 || !is_string($url) || $url === '') {
            $message = $body['message'] ?? 'geçersiz yanıt';
            throw new \RuntimeException('waija: oyun başlatılamadı (' . $message . ').');
        }

        return $url;
    }

    public function testConnectivity(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => $this->configStatus()['message']];
        }

        try {
            $response = $this->call('getGameList', ['list_type' => 1, 'currency' => $this->currency()]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'waija: ' . $e->getMessage()];
        }

        if ($response['status'] >= 200 && $response['status'] < 500) {
            return ['success' => true, 'message' => 'Sunucuya ulaşıldı (HTTP ' . $response['status'] . ')'];
        }

        return ['success' => false, 'message' => 'waija: beklenmeyen yanıt (HTTP ' . $response['status'] . ')'];
    }
}
