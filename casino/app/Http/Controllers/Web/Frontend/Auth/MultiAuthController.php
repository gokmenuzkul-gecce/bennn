<?php

namespace VanguardLTE\Http\Controllers\Web\Frontend\Auth;

use VanguardLTE\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use VanguardLTE\User;
use VanguardLTE\Services\AccountSecurityService;
use VanguardLTE\Services\WhatsAppService;
use VanguardLTE\Services\AffiliateService;

class MultiAuthController extends Controller
{
    protected $whatsAppService;
    protected $affiliateService;

    public function __construct(WhatsAppService $whatsAppService, AffiliateService $affiliateService)
    {
        $this->whatsAppService = $whatsAppService;
        $this->affiliateService = $affiliateService;
        $this->middleware('auth')->only('updateProfile');
    }

    /**
     * Send OTP via WhatsApp
     */
    public function postPhoneOtp(Request $request)
    {
        try {
            if ((string) settings('enable_whatsapp_otp', '1') !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp code sign-in is disabled for this store. Use username, email, or phone with your password.',
                ], 403);
            }

            $phoneInput = $request->input('phone');
            if (empty($phoneInput)) {
                return response()->json(['success' => false, 'message' => 'Please enter a valid phone number!']);
            }

            $formattedPhone = WhatsAppService::formatE164($phoneInput);
            $otp = WhatsAppService::generateOtp();

            // Find existing user or store transient OTP in session
            $user = User::where('phone', $formattedPhone)->first();
            if ($user) {
                $user->otp_code = $otp;
                $user->otp_expires_at = now()->addMinutes(10);
                $user->save();
            } else {
                session([
                    'pending_phone' => $formattedPhone,
                    'pending_otp' => $otp,
                    'pending_otp_expires' => now()->addMinutes(10)->timestamp
                ]);
            }

            $sent = $this->whatsAppService->sendOtp($formattedPhone, $otp);
            $isDevMode = $this->whatsAppService->isDevelopmentMode();

            if (!$sent) {
                if ($user) {
                    $user->otp_code = null;
                    $user->otp_expires_at = null;
                    $user->save();
                } else {
                    session()->forget(['pending_phone', 'pending_otp', 'pending_otp_expires']);
                }

                return response()->json([
                    'success' => false,
                    'message' => \VanguardLTE\Services\DeliveryGatewaySettings::provider('whatsapp') === 'devmode'
                        ? 'Development simulation is blocked in this environment. Select PROMEX or Custom delivery in System & API Keys. Use standard login meanwhile.'
                        : 'The WhatsApp delivery provider is unavailable. Try standard login or ask the operator to check Delivery settings.',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'message' => $isDevMode 
                    ? 'DEVELOPMENT SIMULATION: No WhatsApp was sent. Use the displayed test code only on this private test site.'
                    : "6-Digit Verification Code sent to {$formattedPhone} via WhatsApp!",
                'phone' => $formattedPhone,
                'devmode' => $isDevMode,
                'otp' => $isDevMode ? $otp : null
            ]);
        } catch (\Exception $e) {
            Log::error("[MultiAuth OTP Send Error] " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Verify WhatsApp OTP & Login / Auto-Register
     */
    public function verifyPhoneOtp(Request $request)
    {
        try {
            if ((string) settings('enable_whatsapp_otp', '1') !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp code sign-in is disabled for this store. Use username, email, or phone with your password.',
                ], 403);
            }

            $phoneInput = $request->input('phone');
            $otpInput = trim($request->input('otp_code'));
            $refCode = trim($request->input('ref') ?: ($request->cookie('cedar_ref') ?: (session('cedar_ref') ?: '')));

            if (empty($phoneInput) || empty($otpInput)) {
                return response()->json(['success' => false, 'message' => 'Please provide phone and 6-digit OTP code!']);
            }

            $formattedPhone = WhatsAppService::formatE164($phoneInput);
            $user = User::where('phone', $formattedPhone)->first();

            $verified = false;

            if ($user) {
                if ($user->otp_code === $otpInput && $user->otp_expires_at && now()->lt($user->otp_expires_at)) {
                    $verified = true;
                    $user->otp_code = null;
                    $user->phone_verified_at = now();
                    $user->save();
                }
            } else {
                $sessionPhone = session('pending_phone');
                $sessionOtp = session('pending_otp');
                $sessionExpires = session('pending_otp_expires');

                if ($sessionPhone === $formattedPhone && $sessionOtp === $otpInput && time() < $sessionExpires) {
                    $verified = true;
                }
            }

            if (!$verified) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired OTP code. Please try again!']);
            }

            // Auto-Register new user if not exists
            if (!$user) {
                $parentId = 0;
                if (!empty($refCode)) {
                    $inviter = User::where('invite_code', $refCode)->orWhere('username', $refCode)->first();
                    if ($inviter) {
                        $parentId = $inviter->id;
                        $inviter->increment('count_invite');
                    }
                }

                $username = 'player_' . random_int(10000, 99999);
                while (User::where('username', $username)->exists()) {
                    $username = 'player_' . random_int(10000, 99999);
                }

                $user = User::create([
                    'username' => $username,
                    'phone' => $formattedPhone,
                    'phone_verified_at' => now(),
                    'parent_id' => $parentId,
                    'password' => Str::random(32),
                    'role_id' => 1,
                    'status' => 'Active',
                    'balance' => (float) (function_exists('settings') ? settings('default_starting_coins', 50000) : 50000),
                    'shop_id' => 1
                ]);

                // Process Affiliate Referral Rewards (Tier 1 / Tier 2)
                $this->affiliateService->processReferralRewards($user);
                AffiliateService::generateInviteCode($user);
            }

            // Auto-promote to admin if phone number matches ADMIN_PHONE in .env
            $adminEnv = env('ADMIN_PHONE', '');
            if (!empty($adminEnv)) {
                $adminPhones = array_filter(array_map('trim', explode(',', $adminEnv)));
                $cleanFormatted = preg_replace('/[^\d]/', '', $formattedPhone);
                foreach ($adminPhones as $ap) {
                    $cleanAp = preg_replace('/[^\d]/', '', $ap);
                    if (!empty($cleanAp) && str_ends_with($cleanFormatted, $cleanAp)) {
                        if ($user->role_id != 6) {
                            $user->role_id = 6;
                            $user->save();
                        }
                        \Illuminate\Support\Facades\DB::table('role_user')->updateOrInsert(
                            ['user_id' => $user->id],
                            ['role_id' => 6]
                        );
                        break;
                    }
                }
            }

            Auth::login($user, true);

            return response()->json([
                'success' => true,
                'message' => 'Authentication Successful!',
                'redirect' => '/',
                'user' => [
                    'username' => $user->username,
                    'balance' => number_format($user->balance, 0)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("[MultiAuth Verify Error] " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update User Profile & Reward Verification Fields
     */
    public function updateProfile(Request $request, AccountSecurityService $security)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Please log in to update profile!']);
            }

            $phone = trim((string) $request->input('phone', ''));
            $request->merge(['phone' => $phone === '' ? null : WhatsAppService::formatE164($phone)]);
            $request->validate([
                'username' => ['required', 'string', 'max:191', Rule::unique('users', 'username')->ignore($user->id)],
                'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
                'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
                'preferred_login_method' => ['required', Rule::in(['phone', 'password'])],
                'current_password' => ['nullable', 'string'],
                'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
                'first_name' => ['nullable', 'string', 'max:191'],
                'last_name' => ['nullable', 'string', 'max:191'],
            ]);

            $result = $security->update($user, [
                'username' => $request->input('username'),
                'email' => $request->input('email', ''),
                'phone' => $request->input('phone'),
                'preferred_login_method' => $request->input('preferred_login_method'),
                'current_password' => $request->input('current_password', ''),
                'password' => $request->input('new_password', ''),
                'first_name' => $request->input('first_name', ''),
                'last_name' => $request->input('last_name', ''),
            ]);

            $user->refresh();

            // Check $99+ Real Reward Claim Eligibility
            $isEligible99 = !empty($user->first_name) && !empty($user->last_name) && !empty($user->email);

            return response()->json([
                'success' => true,
                'message' => $result['phone_verification_required']
                    ? 'Profile saved. Verify the new phone with WhatsApp OTP before using phone login.'
                    : 'Profile and login settings updated.',
                'eligible_99' => $isEligible99,
                'user' => [
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'preferred_login_method' => $user->preferred_login_method,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $message = collect($errors)->flatten()->first() ?: 'Check the highlighted account fields.';
            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422);
        } catch (\Exception $e) {
            Log::error("[Profile Update Error] " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
