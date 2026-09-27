<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Format phone number to E.164 standard
     */
    public static function formatE164(string $phone, string $defaultCountryCode = '961'): string
    {
        $digits = preg_replace('/[^\d]/', '', $phone);

        if (empty($digits)) {
            return '';
        }

        // If phone already starts with +, remove + and format
        if (strpos($phone, '+') === 0) {
            return '+' . $digits;
        }

        // If local number without country code (e.g. 70123456 in Lebanon)
        if (strlen($digits) <= 8) {
            return '+' . $defaultCountryCode . $digits;
        }

        return '+' . $digits;
    }

    /**
     * Generate a 6-digit random OTP code
     */
    public static function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * Send OTP via WhatsApp Cloud API or Log Mode
     */
    public function sendOtp(string $phoneE164, string $otp): bool
    {
        // Backend selection is authoritative, not the retired WHATSAPP_MODE override.
        $mode = DeliveryGatewaySettings::provider('whatsapp');

        if ($mode === 'devmode') {
            return $this->isDevelopmentMode();
        }

        if ($mode === 'promex') {
            return $this->sendPromex($phoneE164, $otp);
        }

        if ($mode === 'custom') {
            return $this->sendCustom($phoneE164, $otp);
        }

        return false;
    }

    public function isDevelopmentMode(): bool
    {
        return DeliveryGatewaySettings::provider('whatsapp') === 'devmode'
            && app()->environment('local', 'testing');
    }

    private function sendPromex(string $phoneE164, string $otp): bool
    {
        try {
            $path = DeliveryGatewaySettings::PROMEX_WHATSAPP_PATH;
            $body = json_encode([
                'phone' => $phoneE164,
                'code' => $otp,
                'purpose' => 'login',
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $headers = PromexInstallationService::signedHeaders('POST', $path, $body);
            return Http::withHeaders($headers)
                ->withOptions((array) config('licensing.hub_http_options', ['allow_redirects' => false]))
                ->withBody($body, 'application/json')
                ->timeout(10)
                ->post(rtrim((string) config('licensing.cedar_public_origin'), '/') . $path)
                ->successful();
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp OTP] Promex delivery failed.', ['type' => get_class($e)]);
            return false;
        }
    }

    private function sendCustom(string $phoneE164, string $otp): bool
    {
        $endpoint = DeliveryGatewaySettings::customEndpoint('whatsapp');
        $token = DeliveryGatewaySettings::secret('whatsapp');
        if ($endpoint === '' || $token === '') {
            return false;
        }
        try {
            return Http::withToken($token)->acceptJson()->asJson()->timeout(10)->post($endpoint, [
                'phone' => $phoneE164,
                'code' => $otp,
                'purpose' => 'login',
            ])->successful();
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp OTP] Custom delivery failed.', ['type' => get_class($e)]);
            return false;
        }
    }
}
