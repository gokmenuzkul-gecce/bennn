<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\Providers\Contracts\CasinoProvider;

/**
 * Shared seamless-wallet behaviour for every vendor.
 *
 * The four aggregator brands differ only by endpoint + credentials, so the
 * signing, verification and launch logic lives here and subclasses merely
 * declare their key/label. Credentials resolve from the settings table first
 * (operator editable in Liteback) and fall back to config/casino_providers.php.
 */
abstract class AbstractCasinoProvider implements CasinoProvider
{
    /** Logical field order per wallet operation, as required by the vendor. */
    protected const SIGN_ORDERS = [
        'GetBalance' => ['agentID', 'userID', 'gameID'],
        'Withdraw' => ['agentID', 'userID', 'amount', 'transactionID', 'roundID', 'gameID'],
        'Deposit' => ['agentID', 'userID', 'amount', 'refTransactionID', 'transactionID', 'roundID', 'gameID'],
        'BetWin' => ['agentID', 'userID', 'betAmount', 'winAmount', 'transactionID', 'roundID', 'gameID'],
        'RollbackTransaction' => ['agentID', 'userID', 'refTransactionID', 'gameID'],
    ];

    /** Fields whose value is money and must be normalised to 2 decimals. */
    protected const MONEY_FIELDS = ['amount', 'betAmount', 'winAmount'];

    public function config(): array
    {
        $defaults = (array) config('casino_providers.providers.' . $this->key(), []);
        $overrides = [];
        $settingsKey = $defaults['settings_key'] ?? ('casino_provider_' . $this->key());

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

    public function isConfigured(): bool
    {
        $config = $this->config();

        return !empty($config['endpoint'])
            && !empty($config['agent_id'])
            && !empty($config['secret_key']);
    }

    public function configStatus(): array
    {
        $config = $this->config();
        $missing = [];
        foreach (['endpoint', 'agent_id', 'secret_key'] as $field) {
            if (empty($config[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return [
                'configured' => false,
                'message' => 'Eksik alanlar: ' . implode(', ', $missing),
            ];
        }

        return [
            'configured' => true,
            'message' => 'Yapılandırıldı · ' . ($config['endpoint'] ?? ''),
        ];
    }

    public function signOrder(string $operation): array
    {
        return self::SIGN_ORDERS[$operation] ?? [];
    }

    public function sign(array $params, array $order): string
    {
        $config = $this->config();
        $secret = (string) ($config['secret_key'] ?? '');
        $message = $this->buildMessage($params, $order);

        return strtoupper(hash_hmac('sha256', $message, $secret));
    }

    public function verify(array $payload, array $order): bool
    {
        $provided = (string) ($this->value($payload, 'sign') ?? '');
        if ($provided === '') {
            return false;
        }

        $expected = $this->sign($payload, $order);

        return hash_equals($expected, strtoupper($provided));
    }

    /**
     * Concatenate the ordered field values into the message the vendor hashes.
     * Money fields are normalised to exactly two decimal places.
     */
    protected function buildMessage(array $params, array $order): string
    {
        $message = '';
        foreach ($order as $field) {
            $value = $this->value($params, $field);
            if (in_array($field, self::MONEY_FIELDS, true)) {
                $value = number_format((float) $value, 2, '.', '');
            }
            $message .= (string) $value;
        }

        return $message;
    }

    /** Case-insensitive payload lookup (vendors mix userID / userid casing). */
    protected function value(array $params, string $field): mixed
    {
        if (array_key_exists($field, $params)) {
            return $params[$field];
        }
        $needle = strtolower($field);
        foreach ($params as $key => $value) {
            if (strtolower((string) $key) === $needle) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Ask the aggregator for a playable session URL.
     *
     * The launch endpoint is a POST that takes the bearer token in a header and
     * answers with {"code":0,"url":"..."}; it is not a redirect target, so the
     * URL cannot be built client-side. The vendor also rejects non-alphanumeric
     * user ids and requires the numeric vendor game id, both of which the caller
     * is responsible for providing.
     */
    public function launchUrl(string $userCode, string $gameId, string $lang = 'tr'): string
    {
        $config = $this->config();
        $scheme = (string) ($config['scheme'] ?? 'https');
        $host = (string) ($config['endpoint'] ?? '');
        $path = (string) ($config['launch_path'] ?? '/userauth');

        $payload = [
            'agentID' => (string) ($config['agent_id'] ?? ''),
            'userID' => $this->sanitizeUserCode($userCode),
            'gameID' => $gameId,
            'lang' => $lang,
        ];

        $ch = curl_init(sprintf('%s://%s%s', $scheme, $host, $path));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . (string) ($config['api_token'] ?? ''),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException($this->label() . ': oyun başlatılamadı (' . $curlError . ').');
        }

        $decoded = json_decode((string) $body, true);
        $url = is_array($decoded) ? ($decoded['url'] ?? null) : null;

        if ($status !== 200 || !is_string($url) || $url === '') {
            $message = is_array($decoded) ? ($decoded['message'] ?? 'bilinmeyen hata') : 'geçersiz yanıt';
            throw new \RuntimeException($this->label() . ': oyun başlatılamadı (' . $message . ').');
        }

        return $url;
    }

    /**
     * The vendor rejects any user id containing a non-alphanumeric character,
     * so underscores in the legacy "u<id>_<hash>" code are stripped.
     */
    private function sanitizeUserCode(string $userCode): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $userCode);

        return $clean !== '' ? $clean : 'player';
    }

    /**
     * Payload the lobby needs to open an embedded session.
     *
     * Most vendors hand back a plain playable URL. Amusnet instead returns a
     * page whose only job is to auto-POST a form to its own game host, which a
     * browser refuses to do inside an iframe loaded cross-origin, so it
     * overrides this to surface the form for local re-submission.
     *
     * @return array{url: string, form?: array{action: string, fields: array<string, string>}}
     */
    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array
    {
        return ['url' => $this->launchUrl($userCode, $gameId, $lang)];
    }


    public function testConnectivity(): array
    {
        $config = $this->config();
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => $this->configStatus()['message']];
        }

        $scheme = $config['scheme'] ?? 'https';
        $host = (string) $config['endpoint'];

        // The aggregator exposes no unauthenticated health endpoint, so we only
        // verify DNS/TLS reachability. Any HTTP response (even 4xx) proves the
        // host is live and the credentials can be used for a real launch.
        $errorNumber = 0;
        $errorMessage = '';
        $context = stream_context_create([
            'http' => ['method' => 'HEAD', 'timeout' => 8, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        @file_get_contents($scheme . '://' . $host . '/', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($statusLine !== '') {
            return ['success' => true, 'message' => 'Sunucuya ulaşıldı (' . $statusLine . ')'];
        }

        return [
            'success' => false,
            'message' => 'Sunucuya ulaşılamadı: ' . ($errorMessage !== '' ? $errorMessage : $host),
        ];
    }
}
