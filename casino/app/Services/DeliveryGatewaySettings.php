<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\Crypt;

class DeliveryGatewaySettings
{
    public const PROMEX_WHATSAPP_PATH = '/api/service/delivery/whatsapp/otp';
    public const PROMEX_EMAIL_PATH = '/api/service/delivery/email';

    public static function provider(string $channel): string
    {
        $key = $channel === 'email' ? 'email_delivery_provider' : 'whatsapp_delivery_provider';
        $default = $channel === 'email' ? 'disabled' : 'promex';
        $provider = strtolower(trim((string) settings($key, $default)));
        $allowed = $channel === 'email'
            ? ['disabled', 'brevo', 'resend', 'postmark', 'custom']
            : ['promex', 'custom', 'devmode'];
        return in_array($provider, $allowed, true) ? $provider : $default;
    }

    public static function customEndpoint(string $channel): string
    {
        $key = $channel === 'email' ? 'email_api_endpoint' : 'whatsapp_api_endpoint';
        return trim((string) settings($key, ''));
    }

    public static function secret(string $channel): string
    {
        $key = $channel === 'email' ? 'email_api_token' : 'whatsapp_api_token';
        $stored = (string) settings($key, '');
        if (!str_starts_with($stored, 'enc:')) {
            return $stored;
        }
        try {
            return Crypt::decryptString(substr($stored, 4));
        } catch (\Throwable) {
            return '';
        }
    }

    public static function storeSecret(string $channel, string $secret): void
    {
        $key = $channel === 'email' ? 'email_api_token' : 'whatsapp_api_token';
        $secret = trim($secret);
        if ($secret !== '') {
            settings()->set($key, 'enc:' . Crypt::encryptString($secret));
        }
    }

    public static function hasSecret(string $channel): bool
    {
        return self::secret($channel) !== '';
    }

    public static function emailSender(): array
    {
        return [
            'address' => trim((string) settings('email_from_address', '')),
            'name' => trim((string) settings('email_from_name', config('app.name', ''))),
        ];
    }
}
