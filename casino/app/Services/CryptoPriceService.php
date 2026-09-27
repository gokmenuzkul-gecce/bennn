<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\Http;

class CryptoPriceService
{
    public function markets(?string $providerOverride = null, ?string $customEndpoint = null): array
    {
        $provider = $providerOverride ?? (function_exists('settings') ? settings('crypto_prices_provider', 'promex') : 'promex');
        if ($provider === 'promex') {
            if (!LicenseService::canUseCentralCryptoPrices()) return $this->fail('PROMEX Crypto Prices requires an active license.');
            try {
                $path = '/api/service/crypto/markets';
                $hub = rtrim((string) config('licensing.hub_url', LicenseService::DEFAULT_SERVER), '/');
                $response = Http::timeout(max(2, (int) config('licensing.hub_timeout', 8)))
                    ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
                    ->withHeaders(PromexInstallationService::signedHeaders('GET', $path))->get($hub . '/crypto/markets');
                $payload = $response->json();
                if ($response->successful() && is_array($payload) && ($payload['success'] ?? false) === true && is_array($payload['coins'] ?? null)) return $payload;
                return $this->fail(is_array($payload) && is_string($payload['message'] ?? null) ? $payload['message'] : 'The PROMEX Crypto Prices service is unavailable.');
            } catch (\Throwable) { return $this->fail('The PROMEX Crypto Prices service could not be reached.'); }
        }
        $url = trim((string) ($customEndpoint ?? settings('crypto_prices_api_endpoint', '')));
        if (!$this->safeHttpsUrl($url)) return $this->fail('Add a valid public HTTPS URL for the custom crypto-price provider.');
        try {
            $response = Http::timeout(10)->acceptJson()->get($url);
            $payload = $response->json();
            $items = is_array($payload) && array_is_list($payload) ? $payload : (is_array($payload['coins'] ?? null) ? $payload['coins'] : []);
            $coins = $this->normalize($items);
            return $coins === [] ? $this->fail('The custom provider returned no usable coin prices.') : ['success' => true, 'feed' => 'markets', 'source' => 'custom', 'count' => count($coins), 'currency' => 'USD', 'coins' => $coins, 'attribution' => null];
        } catch (\Throwable) { return $this->fail('The custom crypto-price provider could not be reached.'); }
    }

    private function normalize(array $items): array
    {
        $coins = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $price = $item['price_usd'] ?? $item['current_price'] ?? null;
            if (!is_numeric($price) || !is_string($item['id'] ?? null) || !is_string($item['symbol'] ?? null)) continue;
            $coins[] = ['id' => $item['id'], 'symbol' => strtoupper($item['symbol']), 'name' => (string) ($item['name'] ?? $item['symbol']), 'image' => $item['image'] ?? null, 'rank' => isset($item['rank']) ? (int) $item['rank'] : (isset($item['market_cap_rank']) ? (int) $item['market_cap_rank'] : null), 'price_usd' => (float) $price, 'market_cap_usd' => isset($item['market_cap_usd']) ? (float) $item['market_cap_usd'] : (isset($item['market_cap']) ? (float) $item['market_cap'] : null), 'volume_24h_usd' => isset($item['volume_24h_usd']) ? (float) $item['volume_24h_usd'] : (isset($item['total_volume']) ? (float) $item['total_volume'] : null), 'change_24h' => isset($item['change_24h']) ? (float) $item['change_24h'] : (isset($item['price_change_percentage_24h']) ? (float) $item['price_change_percentage_24h'] : null), 'updated_at' => $item['updated_at'] ?? $item['last_updated'] ?? null];
        }
        return $coins;
    }

    private function safeHttpsUrl(string $url): bool
    {
        $parts = parse_url($url); $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        if (($parts['scheme'] ?? '') !== 'https' || !is_string($host) || $host === '' || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP) !== false) return false;
        return true;
    }

    private function fail(string $message): array { return ['success' => false, 'message' => $message, 'coins' => []]; }
}
