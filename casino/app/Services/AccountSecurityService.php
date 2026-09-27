<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use VanguardLTE\User;

class AccountSecurityService
{
    public function update(User $user, array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? $user->email)));
        $phoneInput = trim((string) (array_key_exists('phone', $data) ? $data['phone'] : $user->phone));
        $phone = $phoneInput === '' ? null : WhatsAppService::formatE164($phoneInput);
        $preferred = (string) ($data['preferred_login_method'] ?? ($user->preferred_login_method ?: 'phone'));
        $newPassword = trim((string) ($data['password'] ?? ''));

        if (!in_array($preferred, ['phone', 'password'], true)) {
            throw ValidationException::withMessages(['preferred_login_method' => 'Choose phone or username/password login.']);
        }
        if ($preferred === 'phone' && (string) settings('enable_whatsapp_otp', '1') !== '1') {
            throw ValidationException::withMessages(['preferred_login_method' => 'Phone sign-in is disabled for this store.']);
        }
        if ($preferred === 'password' && (string) settings('enable_password_login', '1') !== '1') {
            throw ValidationException::withMessages(['preferred_login_method' => 'Username and password sign-in is disabled for this store.']);
        }
        if ($preferred === 'phone' && $phone === null) {
            throw ValidationException::withMessages(['phone' => 'Add a phone number before choosing phone-first login.']);
        }

        $emailChanged = !hash_equals(strtolower((string) $user->email), $email);
        $phoneChanged = !hash_equals((string) ($user->phone ?? ''), (string) ($phone ?? ''));
        // Saving an optional contact must not switch initial setup to unverified OTP login.
        if ($preferred === 'phone' && ($phoneChanged || !$user->phone_verified_at)
            && (string) settings('enable_password_login', '1') === '1') {
            $preferred = 'password';
        }
        $preferenceChanged = !hash_equals((string) ($user->preferred_login_method ?: 'phone'), $preferred);
        $sensitiveChange = $emailChanged || $phoneChanged || $preferenceChanged || $newPassword !== '' || (bool) $user->must_change_password;

        if ($sensitiveChange && !Hash::check((string) ($data['current_password'] ?? ''), (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        if ((bool) $user->must_change_password && $newPassword === '') {
            throw ValidationException::withMessages(['password' => 'Choose a new password before continuing.']);
        }

        DB::transaction(function () use ($user, $data, $email, $phone, $preferred, $newPassword, $emailChanged, $phoneChanged): void {
            foreach (['username', 'first_name', 'last_name'] as $field) {
                if (array_key_exists($field, $data)) {
                    $user->{$field} = trim((string) $data[$field]);
                }
            }

            if ($emailChanged) {
                $user->email = $email;
                $user->email_verified_at = null;
            }
            if ($phoneChanged) {
                $user->phone = $phone;
                $user->phone_verified = 0;
                $user->phone_verified_at = null;
                $user->otp_code = null;
                $user->otp_expires_at = null;
            }

            $user->preferred_login_method = $preferred;
            if ($newPassword !== '') {
                $user->password = $newPassword;
                $user->must_change_password = false;
            }
            $user->save();
        });

        return [
            'email_changed' => $emailChanged,
            'phone_changed' => $phoneChanged,
            'phone_verification_required' => $phoneChanged && $phone !== null,
            'password_changed' => $newPassword !== '',
        ];
    }
}
