<?php
namespace VanguardLTE\Services;
use Illuminate\Support\Facades\Http;
class StockPriceService {
    public function markets(): array {
        if (!LicenseService::isLicensed()) return $this->fail('PROMEX Stock Prices requires an active license.');
        try { $path='/api/service/stocks/markets'; $hub=rtrim((string) config('licensing.hub_url', LicenseService::DEFAULT_SERVER), '/'); $response=Http::timeout(max(2,(int)config('licensing.hub_timeout',8)))->withOptions((array)config('licensing.hub_http_options',['allow_redirects'=>false]))->withHeaders(PromexInstallationService::signedHeaders('GET',$path))->get($hub.'/stocks/markets'); $payload=$response->json(); return $response->successful() && is_array($payload) && ($payload['success']??false) === true && is_array($payload['stocks']??null) ? $payload : $this->fail(is_array($payload) && is_string($payload['message']??null) ? $payload['message'] : 'The PROMEX Stock Prices service is unavailable.'); } catch (\Throwable) { return $this->fail('The PROMEX Stock Prices service could not be reached.'); }
    }
    private function fail(string $message): array { return ['success'=>false,'message'=>$message,'stocks'=>[]]; }
}
