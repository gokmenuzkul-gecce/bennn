<?php
declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use VanguardLTE\Services\AccountPhoneVerification;
use VanguardLTE\Services\WhatsAppService;
$check = function ($ok, $label) { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; };
$reject = function ($callback) { try { $callback(); return false; } catch (ValidationException) { return true; } };
$service = new class extends AccountPhoneVerification { public function unavailableReason(): ?string { return null; } };
$delivery = new class extends WhatsAppService {
    public string $code = '';
    public bool $success = true;
    public function sendOtp(string $phone, string $otp): bool { $this->code = $otp; return $this->success; }
};
$request = Illuminate\Http\Request::create('/test', 'POST', ['current_password' => 'Phone-Test-Password-42']);
$session = new Illuminate\Session\Store('phone-test', new Illuminate\Session\ArraySessionHandler(120));
$session->start(); $request->setLaravelSession($session);
DB::beginTransaction();
$key = null;
try {
    $user = VanguardLTE\User::where('username', 'admin')->firstOrFail();
    $user->password = 'Phone-Test-Password-42';
    $user->phone = '+15550109999'; $user->phone_verified_at = null; $user->phone_verified = 0;
    $user->must_change_password = true; $user->save();
    $request->setUserResolver(fn () => $user);
    $key = 'account-phone-send:' . $user->id; RateLimiter::clear($key);
    $check($reject(fn () => $service->send($request, $delivery)), 'Temporary password must be replaced first');
    $user->must_change_password = false; $user->save();
    $request->merge(['current_password' => 'wrong']);
    $check($reject(fn () => $service->send($request, $delivery)), 'Sending requires current password');
    $request->merge(['current_password' => 'Phone-Test-Password-42']);
    $service->send($request, $delivery);
    $check($session->get('account_phone_verification.hash') !== $delivery->code, 'Session stores a hash, not the OTP');
    $check($reject(fn () => $service->send($request, $delivery)), 'Resend cooldown enforced');
    $request->merge(['otp_code' => '000000']);
    $check($reject(fn () => $service->verify($request)), 'Wrong code rejected');
    $request->merge(['otp_code' => $delivery->code]);
    $service->verify($request); $user->refresh();
    $check((bool) $user->phone_verified_at && (int) $user->phone_verified === 1, 'Correct code verifies saved number');
    $check($reject(fn () => $service->verify($request)), 'Consumed code cannot be replayed');
    RateLimiter::clear($key); $service->send($request, $delivery);
    $user->phone = '+15550109998';
    $check($reject(fn () => $service->verify($request)), 'Changed phone invalidates challenge');
    RateLimiter::clear($key); $service->send($request, $delivery);
    $session->put('account_phone_verification.expires', time() - 1);
    $check($reject(fn () => $service->verify($request)), 'Expired code rejected');
    RateLimiter::clear($key); $service->send($request, $delivery);
    $request->merge(['otp_code' => '000000']);
    for ($i = 0; $i < 5; $i++) $reject(fn () => $service->verify($request));
    $request->merge(['otp_code' => $delivery->code]);
    $check($reject(fn () => $service->verify($request)), 'Five incorrect attempts invalidate challenge');
    RateLimiter::clear($key); $delivery->success = false;
    $check($reject(fn () => $service->send($request, $delivery)) && !$session->has('account_phone_verification'), 'Failed delivery creates no usable challenge');
} finally {
    if ($key) RateLimiter::clear($key);
    DB::rollBack();
}
echo "ALL PHONE VERIFICATION CHECKS PASSED (fake delivery, database rolled back)\n";
