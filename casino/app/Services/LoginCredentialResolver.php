<?php

namespace VanguardLTE\Services;

use VanguardLTE\User;

class LoginCredentialResolver
{
    public static function resolve(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return ['email' => strtolower($identifier), 'password' => $password];
        }

        if (preg_match('/^\+?[0-9][0-9\s().-]{5,}$/D', $identifier)) {
            $phone = WhatsAppService::formatE164($identifier);
            if ($phone !== '' && User::where('phone', $phone)->exists()) {
                return ['phone' => $phone, 'password' => $password];
            }
        }

        return ['username' => $identifier, 'password' => $password];
    }
}
