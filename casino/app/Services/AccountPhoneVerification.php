<?php

namespace VanguardLTE\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AccountPhoneVerification
{
    public function unavailableReason(): ?string
    {
        if (DeliveryGatewaySettings::provider('whatsapp') === 'devmode') {
            return 'Development simulation sends no WhatsApp messages. Test codes appear only on the local/test login screen. Choose PROMEX or Custom in System & API Keys to verify a real phone number. Production blocks simulation.';
        }
        if ((string) settings('enable_whatsapp_otp', '1') !== '1') {
            return 'WhatsApp sign-in is disabled. Enable it in System & API Keys after completing password setup.';
        }
        if (DeliveryGatewaySettings::provider('whatsapp') === 'promex') {
            return LicenseService::isLicensed() ? null : 'PROMEX WhatsApp delivery requires an active license. After password setup, activate it in Store & License, or configure your own WhatsApp provider in System & API Keys. A license alone does not guarantee delivery availability.';
        }
        return DeliveryGatewaySettings::customEndpoint('whatsapp') !== '' && DeliveryGatewaySettings::hasSecret('whatsapp')
            ? null : 'Configure your own WhatsApp endpoint and token in System & API Keys after password setup. Custom delivery does not require a PROMEX license.';
    }

    public function send(Request $request, WhatsAppService $delivery): void
    {
        $user = $request->user();
        if ($user->must_change_password) $this->fail('Choose your permanent password first. Phone verification is optional during initial setup.');
        if (!Hash::check((string) $request->input('current_password'), $user->password)) $this->fail('The current password is incorrect.');
        if (!$user->phone || !preg_match('/^\+[1-9][0-9]{6,14}$/D', $user->phone)) $this->fail('Save a valid phone number including its country code first.');
        if ($reason = $this->unavailableReason()) $this->fail($reason);
        $key = 'account-phone-send:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) $this->fail('Wait one minute before requesting another code.');
        RateLimiter::hit($key, 60);
        $request->session()->forget('account_phone_verification');
        $code = WhatsAppService::generateOtp();
        if (!$delivery->sendOtp($user->phone, $code)) $this->fail('WhatsApp delivery failed. Check provider configuration and, for PROMEX delivery, license/service availability. You can continue using your password.');
        $request->session()->put('account_phone_verification', [
            'user' => (string) $user->id, 'phone' => $user->phone,
            'hash' => Hash::make($code), 'expires' => time() + 600, 'attempts' => 0,
        ]);
    }

    public function verify(Request $request): void
    {
        $user = $request->user();
        $challenge = $request->session()->get('account_phone_verification');
        if (!$challenge || $challenge['user'] !== (string) $user->id || $challenge['phone'] !== $user->phone
            || $challenge['expires'] <= time() || $challenge['attempts'] >= 5) {
            $request->session()->forget('account_phone_verification');
            $this->fail('Request a new code for your saved phone number.');
        }
        $challenge['attempts']++;
        $request->session()->put('account_phone_verification', $challenge);
        if (!Hash::check((string) $request->input('otp_code'), $challenge['hash'])) $this->fail('The verification code is incorrect.');
        // Conditional update prevents a concurrent phone change from verifying a different number.
        $updated = $user->newQuery()->whereKey($user->id)->where('phone', $challenge['phone'])->update([
            'phone_verified' => 1, 'phone_verified_at' => now(),
        ]);
        $request->session()->forget('account_phone_verification');
        if (!$updated) $this->fail('Your phone changed. Request a new code.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['phone_verification' => $message]);
    }
}
