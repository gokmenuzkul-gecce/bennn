<?php

namespace VanguardLTE\Casino\SmplCore;

/**
 * smpl core aggregator client.
 *
 * smpl core is a different protocol from the legacy loginxgames aggregator:
 *
 *  - Outbound calls carry HMAC-SHA1 header auth (X-Merchant-Id, X-Timestamp,
 *    X-Nonce, X-Sign) and are form-encoded.
 *  - The aggregator calls a single webhook endpoint on our server with an
 *    "action" of balance/bet/win/refund/rollback, and expects an always-200
 *    JSON body.
 *
 * The signature is HMAC-SHA1 over the ksort()-ed merge of the request data and
 * the three auth headers, serialised with http_build_query().
 */
class SmplCoreClient
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
        $defaults = (array) config('casino_providers.smplcore', []);
        $settingsKey = (string) ($defaults['settings_key'] ?? 'casino_provider_smplcore');

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
        return !empty($this->config['merchant_id']) && !empty($this->config['merchant_key']);
    }

    /** @return array{configured: bool, message: string} */
    public function configStatus(): array
    {
        $missing = [];
        foreach (['base_url', 'merchant_id', 'merchant_key'] as $field) {
            if (empty($this->config[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return ['configured' => false, 'message' => 'Eksik alanlar: ' . implode(', ', $missing)];
        }

        return ['configured' => true, 'message' => 'Yapılandırıldı · ' . $this->config['base_url']];
    }

    /**
     * Verify the merchant account is usable without moving money.
     *
     * Calls the signed /games endpoint; any authenticated response (even a 4xx
     * with a JSON body) proves the host is live and the credentials resolve.
     */
    public function testConnectivity(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => $this->configStatus()['message']];
        }

        try {
            $response = $this->request('GET', '/games');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'smpl core: ' . $e->getMessage()];
        }

        if ($response['status'] >= 200 && $response['status'] < 500) {
            return ['success' => true, 'message' => 'Sunucuya ulaşıldı (HTTP ' . $response['status'] . ')'];
        }

        return ['success' => false, 'message' => 'smpl core: beklenmeyen yanıt (HTTP ' . $response['status'] . ')'];
    }

    public function currency(): string
    {
        return (string) ($this->config['currency'] ?? 'TRY');
    }

    /** Public URL the aggregator posts webhooks to. */
    public function callbackUrl(): string
    {
        $base = rtrim((string) (config('casino_providers.callback_base') ?: config('app.url')), '/');
        $path = (string) ($this->config['callback_path'] ?? '/webhooks/smplcore/callbacks');

        return $base . '/' . ltrim($path, '/');
    }

    /** Base API URL (no trailing slash). */
    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    /**
     * Compute the HMAC-SHA1 signature for a set of parameters.
     *
     * The auth headers participate in the hash under their exact header names,
     * merged with the request data and sorted by key, exactly as smpl core does.
     *
     * @param  array<string, mixed>  $params
     */
    public function sign(array $params, string $merchantId, string $timestamp, string $nonce): string
    {
        $merged = array_merge($params, [
            'X-Merchant-Id' => $merchantId,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
        ]);
        ksort($merged);

        return hash_hmac('sha1', http_build_query($merged), (string) ($this->config['merchant_key'] ?? ''));
    }

    /**
     * Validate an inbound webhook signature.
     *
     * @param  array<string, mixed>   $payload  decoded form body
     * @param  array<string, string>  $headers  lowercase header map
     */
    public function verify(array $payload, array $headers): bool
    {
        $merchantId = (string) ($headers['x-merchant-id'] ?? '');
        $timestamp = (string) ($headers['x-timestamp'] ?? '');
        $nonce = (string) ($headers['x-nonce'] ?? '');
        $received = (string) ($headers['x-sign'] ?? '');

        if ($merchantId === '' || $timestamp === '' || $nonce === '' || $received === '') {
            return false;
        }

        if (!$this->isConfigured() || !hash_equals((string) $this->config['merchant_id'], $merchantId)) {
            return false;
        }

        $expected = $this->sign($payload, $merchantId, $timestamp, $nonce);

        return hash_equals($expected, $received);
    }

    /**
     * Perform a signed outbound API call.
     *
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>|null, raw: string}
     */
    public function request(string $method, string $endpoint, array $query = []): array
    {
        $url = $this->baseUrl() . '/' . ltrim($endpoint, '/');
        if ($query !== [] && strtoupper($method) === 'GET') {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $merchantId = (string) $this->config['merchant_id'];
        $sign = $this->sign($query, $merchantId, $timestamp, $nonce);

        $headers = [
            'X-Merchant-Id: ' . $merchantId,
            'X-Timestamp: ' . $timestamp,
            'X-Nonce: ' . $nonce,
            'X-Sign: ' . $sign,
        ];

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];

        if (strtoupper($method) !== 'GET' && $query !== []) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_POSTFIELDS] = http_build_query($query);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('smpl core: istek başarısız (' . $error . ').');
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => (string) $raw,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function games(): array
    {
        $response = $this->request('GET', '/games');
        $body = $response['body'] ?? [];

        return $body['data'] ?? $body['items'] ?? [];
    }

    /**
     * Initialize a real-money session and return the playable URL.
     *
     * @param  array<string, mixed>  $extra
     */
    public function init(string $playerId, string $gameUuid, array $extra = []): string
    {
        $payload = array_merge([
            'player_id' => $playerId,
            'game_uuid' => $gameUuid,
            'currency' => $this->currency(),
        ], $extra);

        $response = $this->request('POST', '/games/init', $payload);
        $body = $response['body'] ?? [];

        $url = $body['data']['url'] ?? $body['url'] ?? null;
        if ($response['status'] !== 200 || !is_string($url) || $url === '') {
            $message = $body['message'] ?? 'geçersiz yanıt';
            throw new \RuntimeException('smpl core: oyun başlatılamadı (' . $message . ').');
        }

        return $url;
    }
}
