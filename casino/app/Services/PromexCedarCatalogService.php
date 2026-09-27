<?php

namespace VanguardLTE\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PromexCedarCatalogService
{
    public function catalog(): array
    {
        $path = '/api/service/cedar/catalog';
        try {
            $response = Http::timeout(max(2, (int) config('licensing.hub_timeout', 8)))
                ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
                ->withHeaders(PromexInstallationService::signedHeaders('GET', $path))
                ->get($this->hubUrl() . '/cedar/catalog');
        } catch (ConnectionException $e) {
            throw new RuntimeException('The Cedar catalog is unavailable.', 0, $e);
        }
        $payload = $this->verifiedResponse($response->successful() ? $response->json() : null, 'cedar_catalog');
        if (!is_array($payload['games'] ?? null) || !array_is_list($payload['games'])) {
            throw new RuntimeException('The Cedar catalog payload is invalid.');
        }
        foreach ($payload['games'] as $game) $this->validateGame($game);
        return $payload;
    }

    public function launch(string $game): array
    {
        $game = trim($game);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $game)) throw new RuntimeException('Invalid Cedar game ID.');
        $path = '/api/service/cedar/launch';
        $body = json_encode(['game' => $game], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        try {
            $response = Http::timeout(max(2, (int) config('licensing.hub_timeout', 8)))
                ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
                ->withHeaders(PromexInstallationService::signedHeaders('POST', $path, $body))
                ->withBody($body, 'application/json')
                ->post($this->hubUrl() . '/cedar/launch');
        } catch (ConnectionException $e) {
            throw new RuntimeException('The Cedar launch service is unavailable.', 0, $e);
        }
        $payload = $this->verifiedResponse($response->successful() ? $response->json() : null, 'cedar_launch');
        $credentials = PromexInstallationService::credentials();
        if (($payload['game'] ?? null) !== $game
            || ($payload['operator_domain'] ?? null) !== LicenseService::licensedDomain()
            || !preg_match('/^[a-f0-9-]{36}$/D', (string) ($payload['launch_id'] ?? ''))
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', (string) ($payload['launch_token'] ?? ''))
            || !preg_match('#^/cedar/games/' . preg_quote($game, '#') . '/[A-Za-z0-9._/-]+$#D', (string) ($payload['entry_path'] ?? ''))
            || ($credentials['games'] !== null && !in_array($game, $credentials['games'], true))) {
            throw new RuntimeException('The Cedar launch payload is invalid.');
        }
        $payload['launch_url'] = $this->publicOrigin() . $payload['entry_path']
            . '?promex_remote=1#promex_launch=' . $payload['launch_token'];
        return $payload;
    }

    private function verifiedResponse(mixed $envelope, string $type): array
    {
        if (!is_array($envelope) || !is_string($envelope['signed_payload'] ?? null)) {
            throw new RuntimeException('The Service Hub returned an invalid signed response.');
        }
        $signature = base64_decode((string) ($envelope['signature'] ?? ''), true);
        $publicKey = (string) config('licensing.public_key', LicenseService::PROMEX_PUBLIC_KEY);
        if ($signature === false || openssl_verify($envelope['signed_payload'], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('The Service Hub response signature is invalid.');
        }
        $payload = json_decode($envelope['signed_payload'], true, 64, JSON_THROW_ON_ERROR);
        $credentials = PromexInstallationService::credentials();
        $now = time();
        if (!is_array($payload) || $credentials === null
            || ($payload['version'] ?? null) !== 1 || ($payload['type'] ?? null) !== $type
            || ($payload['installation_id'] ?? null) !== $credentials['installation_id']
            || !is_int($payload['issued_at'] ?? null) || !is_int($payload['expires_at'] ?? null)
            || $payload['issued_at'] > $now + 60 || $payload['expires_at'] <= $now
            || $payload['expires_at'] > $now + 600) {
            throw new RuntimeException('The Service Hub signed response is expired or mismatched.');
        }
        return $payload;
    }

    private function validateGame(mixed $game): void
    {
        $id = is_array($game) ? (string) ($game['id'] ?? '') : '';
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $id)
            || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', (string) ($game['version'] ?? ''))
            || ($game['availability'] ?? null) !== 'active'
            || !preg_match('#^/cedar/games/' . preg_quote($id, '#') . '/[A-Za-z0-9._/-]+$#D', (string) ($game['entry_path'] ?? ''))
            || !is_array($game['manifest'] ?? null)) {
            throw new RuntimeException('The Cedar catalog contains an invalid game.');
        }
    }

    private function hubUrl(): string
    {
        $url = rtrim((string) config('licensing.hub_url', LicenseService::DEFAULT_SERVER), '/');
        if (!preg_match('#^https://[^/]+(?:/[^?\#]*)?$#iD', $url)) throw new RuntimeException('PROMEX_HUB_URL must be HTTPS.');
        return $url;
    }

    private function publicOrigin(): string
    {
        $origin = rtrim((string) config('licensing.cedar_public_origin', 'https://clients.377.live'), '/');
        if (!preg_match('#^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?$#D', $origin)) {
            throw new RuntimeException('PROMEX_CEDAR_PUBLIC_ORIGIN must be an HTTPS origin.');
        }
        return $origin;
    }
}
