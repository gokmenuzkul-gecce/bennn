<?php

namespace VanguardLTE\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PromexInstallationService
{
    private const FILE_VERSION = 1;

    /** Exchange a verified license key for one domain-bound service credential. */
    public static function activate(string $licenseKey, ?string $label = null): array
    {
        $licenseKey = trim($licenseKey);
        $domain = LicenseService::licensedDomain();
        if ($licenseKey === '' || $domain === '') {
            throw new RuntimeException('A license key and valid APP_URL are required for service activation.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(max(2, (int) config('licensing.hub_timeout', 8)))
                ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
                ->post(self::hubUrl() . '/installations/activate', [
                    'license_key' => $licenseKey,
                    'domain' => $domain,
                    'label' => trim((string) ($label ?? config('app.name', 'Promex installation'))),
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('The Promex Service Hub is unavailable.', 0, $e);
        }

        $data = $response->json();
        if (!$response->successful() || !is_array($data)) {
            $message = is_array($data) && is_string($data['message'] ?? null)
                ? $data['message'] : 'Service installation activation was denied.';
            throw new RuntimeException($message);
        }

        $credentials = self::validatedCredentials($data, $domain);
        $credentials['license_fingerprint'] = hash('sha256', $licenseKey);
        self::save($credentials);

        return self::publicStatus($credentials);
    }

    /** Return private credentials for server-side service clients only. */
    public static function credentials(): ?array
    {
        $path = self::path();
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return null;
        }

        try {
            return self::validatedCredentials($decoded, LicenseService::licensedDomain());
        } catch (RuntimeException) {
            return null;
        }
    }

    /** Build replay-resistant HMAC headers for one exact HTTP request. */
    public static function signedHeaders(string $method, string $path, string $body = '', ?int $timestamp = null, ?string $requestId = null): array
    {
        $credentials = self::credentials();
        if ($credentials === null) {
            throw new RuntimeException('This installation has no valid Promex service credential.');
        }

        $method = strtoupper(trim($method));
        $parts = parse_url($path);
        $requestPath = is_array($parts) && is_string($parts['path'] ?? null) ? $parts['path'] : '/';
        if (is_array($parts) && is_string($parts['query'] ?? null)) {
            $requestPath .= '?' . $parts['query'];
        }
        if (!preg_match('/^[A-Z]+$/D', $method)
            || !preg_match("#^/[A-Za-z0-9._~!$&'()*+,;=:@%/?\\-]*$#D", $requestPath)) {
            throw new RuntimeException('The service request method or path is invalid.');
        }

        $sentAt = (string) ($timestamp ?? time());
        $requestId = strtolower($requestId ?? bin2hex(random_bytes(16)));
        if (!preg_match('/^[a-f0-9]{32}$/D', $requestId)) {
            throw new RuntimeException('The service request identifier is invalid.');
        }

        $canonical = $method . "\n" . $requestPath . "\n" . $sentAt . "\n" . $requestId . "\n" . hash('sha256', $body);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Promex-Installation' => $credentials['installation_id'],
            'X-Promex-Request' => $requestId,
            'X-Promex-Time' => $sentAt,
            'X-Promex-Signature' => hash_hmac('sha256', $canonical, $credentials['installation_secret']),
        ];
    }

    /** Safe for controllers and views; intentionally excludes the secret. */
    public static function status(): array
    {
        $credentials = self::credentials();
        return $credentials === null
            ? ['status' => 'not_activated']
            : self::publicStatus($credentials);
    }

    /** Activate only when this local credential does not belong to the saved license. */
    public static function ensureActivated(string $licenseKey, ?string $label = null): array
    {
        $credentials = self::credentials();
        $fingerprint = hash('sha256', trim($licenseKey));
        if ($credentials !== null
            && is_string($credentials['license_fingerprint'] ?? null)
            && hash_equals($credentials['license_fingerprint'], $fingerprint)) {
            return self::publicStatus($credentials);
        }

        return self::activate($licenseKey, $label);
    }

    public static function forget(): void
    {
        $path = self::path();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function validatedCredentials(array $data, string $expectedDomain): array
    {
        $installationId = trim((string) ($data['installation_id'] ?? ''));
        $secret = trim((string) ($data['installation_secret'] ?? ''));
        $domain = strtolower(rtrim(trim((string) ($data['domain'] ?? '')), '.'));
        $features = $data['features'] ?? null;
        $games = $data['games'] ?? null;
        $fingerprint = $data['license_fingerprint'] ?? null;

        if (($data['version'] ?? self::FILE_VERSION) !== self::FILE_VERSION
            || !preg_match('/^inst_[a-f0-9]{32}$/D', $installationId)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $secret)
            || $expectedDomain === '' || !hash_equals($expectedDomain, $domain)
            || !self::validList($features, '/^[a-z0-9_]+$/D')
            || ($games !== null && !self::validList($games, '/^[A-Za-z][A-Za-z0-9_]{1,99}$/D'))
            || ($fingerprint !== null && (!is_string($fingerprint) || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)))) {
            throw new RuntimeException('The Service Hub returned invalid installation credentials.');
        }

        return [
            'version' => self::FILE_VERSION,
            'installation_id' => $installationId,
            'installation_secret' => $secret,
            'domain' => $domain,
            'features' => array_values(array_unique($features)),
            'games' => $games === null ? null : array_values(array_unique($games)),
            'valid_until' => is_string($data['valid_until'] ?? null) ? $data['valid_until'] : null,
            'activated_at' => is_string($data['activated_at'] ?? null) ? $data['activated_at'] : gmdate('c'),
            'license_fingerprint' => $fingerprint,
        ];
    }

    private static function validList(mixed $items, string $pattern): bool
    {
        if (!is_array($items) || !array_is_list($items)) {
            return false;
        }
        foreach ($items as $item) {
            if (!is_string($item) || !preg_match($pattern, $item)) {
                return false;
            }
        }
        return true;
    }

    private static function save(array $credentials): void
    {
        $path = self::path();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the private installation credential directory.');
        }

        $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            $payload = json_encode($credentials, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($temp, $payload, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the installation credential.');
            }
            @chmod($temp, 0600);
            if (!rename($temp, $path)) {
                throw new RuntimeException('Unable to replace the installation credential.');
            }
            @chmod($path, 0600);
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private static function publicStatus(array $credentials): array
    {
        return [
            'status' => 'active',
            'installation_id' => $credentials['installation_id'],
            'domain' => $credentials['domain'],
            'features' => $credentials['features'],
            'games' => $credentials['games'],
            'valid_until' => $credentials['valid_until'],
            'activated_at' => $credentials['activated_at'],
        ];
    }

    private static function hubUrl(): string
    {
        $url = rtrim((string) config('licensing.hub_url', LicenseService::DEFAULT_SERVER), '/');
        if (!preg_match('#^https://[^/]+(?:/[^?\#]*)?$#iD', $url)) {
            throw new RuntimeException('PROMEX_HUB_URL must be an HTTPS URL.');
        }
        return $url;
    }

    private static function path(): string
    {
        $suffix = config('licensing.runtime_profile', 'live') === 'local' ? '.local' : '';
        return storage_path('framework/promex.installation' . $suffix);
    }
}
