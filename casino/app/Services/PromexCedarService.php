<?php

namespace VanguardLTE\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PromexCedarService
{
    public function arcade(string $game, string $scope, string $command, array $payload): array
    {
        $input = ['game'=>$game, 'scope_id'=>$scope, 'command_id'=>$command, 'payload'=>$payload];
        $response = $this->post('/api/service/cedar/arcade', $input);
        $raw = $response['signed_payload'] ?? null;
        $signature = base64_decode((string) ($response['signature'] ?? ''), true);
        if (!is_string($raw) || $signature === false || openssl_verify($raw, $signature,
            (string) config('licensing.public_key', LicenseService::PROMEX_PUBLIC_KEY), OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Invalid arcade signature; recover the existing command.');
        }
        $signed = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        if (($signed['type'] ?? '') !== 'cedar_arcade_command'
            || ($signed['installation_id'] ?? '') !== (PromexInstallationService::status()['installation_id'] ?? null)
            || ($signed['game'] ?? '') !== $game || ($signed['scope_id'] ?? '') !== $scope
            || ($signed['command_id'] ?? '') !== $command
            || ($signed['input_hash'] ?? '') !== hash('sha256', json_encode($input, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
            || !is_array($signed['result'] ?? null)) throw new RuntimeException('Arcade response binding failed.');
        return $signed['result'];
    }

    public function init(string $game, string $scopeId): array
    {
        $game = $this->game($game);
        $scopeId = $this->scope($scopeId);
        $response = $this->post('/api/service/cedar/init', ['game' => $game, 'scope_id' => $scopeId]);
        if (($response['status'] ?? null) !== 'success'
            || ($response['game'] ?? null) !== $game
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($response['server_seed_hash'] ?? ''))
            || (int) ($response['nonce'] ?? 0) < 1
            || !$this->validPresentation($response['presentation'] ?? null)) {
            throw new RuntimeException('The Cedar service returned an invalid commitment.');
        }

        return $response;
    }

    public function spin(
        string $game,
        string $scopeId,
        string $wager,
        string $clientSeed,
        string $serverSeedHash,
        ?string $requestId = null
    ): array {
        $game = $this->game($game);
        $scopeId = $this->scope($scopeId);
        $wager = $this->money($wager);
        $clientSeed = trim($clientSeed);
        $serverSeedHash = strtolower(trim($serverSeedHash));
        $requestId = strtolower($requestId ?? bin2hex(random_bytes(16)));
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $clientSeed)
            || !preg_match('/^[a-f0-9]{64}$/D', $serverSeedHash)
            || !preg_match('/^[a-f0-9]{32}$/D', $requestId)) {
            throw new RuntimeException('The Cedar fairness inputs are invalid.');
        }

        $response = $this->post('/api/service/cedar/spin', [
            'game' => $game,
            'scope_id' => $scopeId,
            'request_id' => $requestId,
            'wager' => $wager,
            'client_seed' => $clientSeed,
            'server_seed_hash' => $serverSeedHash,
        ]);
        $this->verifySpin($response, $game, $scopeId, $wager, $clientSeed, $serverSeedHash, $requestId);

        return $response;
    }

    private function post(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        try {
            $response = Http::timeout(max(2, (int) config('licensing.hub_timeout', 8)))
                ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
                ->withHeaders(PromexInstallationService::signedHeaders('POST', $path, $body))
                ->withBody($body, 'application/json')
                ->post($this->hubUrl() . substr($path, strlen('/api/service')));
        } catch (ConnectionException $e) {
            throw new RuntimeException('The Cedar service is unavailable.', 0, $e);
        }
        $data = $response->json();
        if (!$response->successful() || !is_array($data)) {
            $message = is_array($data) && is_string($data['message'] ?? null)
                ? $data['message'] : 'The Cedar service denied the request.';
            throw new RuntimeException($message);
        }

        return $data;
    }

    private function verifySpin(
        array $response,
        string $game,
        string $scopeId,
        string $wager,
        string $clientSeed,
        string $serverSeedHash,
        string $requestId
    ): void {
        $proof = $response['proof'] ?? null;
        $outcome = $response['outcome'] ?? null;
        $outcomeProof = is_array($outcome) ? ($outcome['proof'] ?? null) : null;
        $signedPayload = is_array($proof) ? ($proof['signed_payload'] ?? null) : null;
        $signature = is_array($proof) ? base64_decode((string) ($proof['signature'] ?? ''), true) : false;
        $publicKey = (string) config('licensing.public_key', LicenseService::PROMEX_PUBLIC_KEY);
        if (!is_string($signedPayload) || $signature === false
            || openssl_verify($signedPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('The Cedar outcome proof is invalid.');
        }
        try {
            $signed = json_decode($signedPayload, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('The Cedar outcome proof is malformed.', 0, $e);
        }
        if (!is_array($signed) || !is_array($outcome)
            || !is_array($outcomeProof)
            || ($response['status'] ?? null) !== 'success'
            || ($response['round_id'] ?? null) !== ($signed['round_id'] ?? null)
            || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D', (string) ($signed['round_id'] ?? ''))
            || ($response['request_id'] ?? null) !== $requestId
            || ($signed['request_id'] ?? null) !== $requestId
            || ($signed['game'] ?? null) !== $game
            || ($signed['scope_id'] ?? null) !== $scopeId
            || ($signed['wager'] ?? null) !== $wager
            || ($signed['client_seed'] ?? null) !== $clientSeed
            || ($signed['server_seed_hash'] ?? null) !== $serverSeedHash
            || hash('sha256', (string) ($signed['server_seed'] ?? '')) !== $serverSeedHash
            || ($response['next_server_seed_hash'] ?? null) !== ($signed['next_server_seed_hash'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($signed['next_server_seed_hash'] ?? ''))
            || ($outcome['game'] ?? null) !== $game
            || ($outcome['math_version'] ?? null) !== ($signed['math_version'] ?? null)
            || ($outcome['wager'] ?? null) !== $wager
            || ($outcome['win_amount'] ?? null) !== ($signed['win_amount'] ?? null)
            || ($outcome['multiplier'] ?? null) !== ($signed['multiplier'] ?? null)
            || ($outcome['grid'] ?? null) !== ($signed['grid'] ?? null)
            || ($outcome['line_wins'] ?? null) !== ($signed['line_wins'] ?? null)
            || ($outcomeProof['server_seed'] ?? null) !== ($signed['server_seed'] ?? null)
            || ($outcomeProof['server_seed_hash'] ?? null) !== $serverSeedHash
            || ($outcomeProof['client_seed'] ?? null) !== $clientSeed
            || ($outcomeProof['nonce'] ?? null) !== ($signed['nonce'] ?? null)
            || !preg_match('/^[a-z0-9._-]{3,64}$/D', (string) ($signed['math_version'] ?? ''))
            || !preg_match('/^(0|[1-9][0-9]{0,15})\.[0-9]{2}$/D', (string) ($signed['win_amount'] ?? ''))
            || (int) ($signed['nonce'] ?? 0) < 1) {
            throw new RuntimeException('The Cedar outcome does not match its request or proof.');
        }
    }

    private function game(string $game): string
    {
        $game = trim($game);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $game)) {
            throw new RuntimeException('The Cedar game identifier is invalid.');
        }
        return $game;
    }

    private function scope(string $scopeId): string
    {
        $scopeId = strtolower(trim($scopeId));
        if (!preg_match('/^[a-f0-9]{32}$/D', $scopeId)) {
            throw new RuntimeException('The Cedar scope identifier is invalid.');
        }
        return $scopeId;
    }

    private function money(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/D', $value, $match)) {
            throw new RuntimeException('The Cedar wager is invalid.');
        }
        return $match[1] . '.' . str_pad((string) ($match[2] ?? ''), 2, '0');
    }

    private function validPresentation(mixed $value): bool
    {
        if (!is_array($value) || (int) ($value['rows'] ?? 0) < 1 || (int) ($value['rows'] ?? 0) > 10
            || (int) ($value['reels'] ?? 0) < 3 || (int) ($value['reels'] ?? 0) > 10
            || (int) ($value['lines'] ?? 0) < 1 || (int) ($value['lines'] ?? 0) > 1000
            || (int) ($value['wild'] ?? 0) < 1
            || !is_array($value['symbols'] ?? null)
            || (float) ($value['published_rtp'] ?? 0) <= 0 || (float) $value['published_rtp'] > 95) {
            return false;
        }
        if (!preg_match('/^(0|[1-9][0-9]{0,8})\.[0-9]{2}$/D', (string) ($value['min_wager'] ?? ''))
            || !preg_match('/^(0|[1-9][0-9]{0,8})\.[0-9]{2}$/D', (string) ($value['max_wager'] ?? ''))
            || (float) $value['min_wager'] <= 0 || (float) $value['max_wager'] < (float) $value['min_wager']) {
            return false;
        }
        foreach ($value['symbols'] as $symbol => $label) {
            if (!ctype_digit((string) $symbol) || !is_string($label) || !preg_match('/^[A-Za-z0-9 _-]{1,32}$/D', $label)) return false;
        }
        return count($value['symbols']) > 0 && array_key_exists((string) $value['wild'], $value['symbols']);
    }

    private function hubUrl(): string
    {
        $url = rtrim((string) config('licensing.hub_url', LicenseService::DEFAULT_SERVER), '/');
        if (!preg_match('#^https://[^/]+(?:/[^?\#]*)?$#iD', $url)) {
            throw new RuntimeException('PROMEX_HUB_URL must be an HTTPS URL.');
        }
        return $url;
    }
}
