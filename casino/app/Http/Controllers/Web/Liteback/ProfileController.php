<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\Services\AccountSecurityService;
use VanguardLTE\Services\WhatsAppService;

class ProfileController extends Controller
{
    public function editPassword(\VanguardLTE\Services\AccountPhoneVerification $verification)
    {
        return view('liteback.profile.password', ['user' => auth()->user(), 'phoneUnavailableReason' => $verification->unavailableReason()]);
    }

    public function sendPhoneCode(Request $request, \VanguardLTE\Services\AccountPhoneVerification $verification, WhatsAppService $delivery)
    {
        $request->validate(['current_password' => ['required', 'string']]);
        $verification->send($request, $delivery);
        return redirect()->route('liteback.profile.password')->with('success', 'Code sent to your saved WhatsApp number. Enter it below within 10 minutes.');
    }

    public function verifyPhoneCode(Request $request, \VanguardLTE\Services\AccountPhoneVerification $verification)
    {
        $request->validate(['otp_code' => ['required', 'digits:6']]);
        $verification->verify($request);
        return redirect()->route('liteback.profile.password')->with('success', 'Phone number verified. You can now select WhatsApp as your preferred sign-in method.');
    }

    public function updatePassword(Request $request, AccountSecurityService $security)
    {
        $user = auth()->user();
        $phone = trim((string) $request->input('phone', ''));
        $request->merge(['phone' => $phone === '' ? null : WhatsAppService::formatE164($phone)]);

        $request->validate([
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
            'preferred_login_method' => ['required', Rule::in(['phone', 'password'])],
            'current_password' => ['required', 'string'],
            'password' => [Rule::requiredIf((bool) $user->must_change_password), 'nullable', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $result = $security->update($user, $request->only([
            'email', 'phone', 'preferred_login_method', 'current_password', 'password',
        ]));

        $message = $result['password_changed'] ? 'Account security and password updated.' : 'Account security settings updated.';
        if ($result['phone_verification_required']) {
            $message .= ' Verify the new phone with WhatsApp OTP before relying on phone login.';
        }

        return redirect()->route('liteback.profile.password')->with('success', $message);
    }
}
