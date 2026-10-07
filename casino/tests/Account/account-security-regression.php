<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use VanguardLTE\Http\Middleware\RequirePasswordChange;
use VanguardLTE\Services\AccountSecurityService;
use VanguardLTE\Services\LoginCredentialResolver;
use VanguardLTE\User;

$checks = [];
// Test both sign-in methods independently of the operator's current configuration.
$app->instance('anlutro\LaravelSettings\SettingStore', new anlutro\LaravelSettings\MemorySettingStore([
    'enable_password_login' => '1', 'enable_whatsapp_otp' => '1',
]));
$admin = User::withoutGlobalScopes()->where('username', 'admin')->firstOrFail();
$service = app(AccountSecurityService::class);

DB::beginTransaction();
try {
    $admin->password = 'Current-Test-Password-49';
    $admin->must_change_password = true;
    $admin->preferred_login_method = 'password';
    $admin->phone = null;
    $admin->phone_verified = 1;
    $admin->phone_verified_at = now();
    $admin->save();

    $wrongPasswordRejected = false;
    try {
        $service->update($admin, [
            'email' => 'account-test@example.invalid',
            'phone' => '+15550102030',
            'preferred_login_method' => 'phone',
            'current_password' => 'wrong-password',
            'password' => 'New-Test-Password-73',
        ]);
    } catch (ValidationException) {
        $wrongPasswordRejected = true;
    }
    $checks['sensitive changes reject a wrong current password'] = $wrongPasswordRejected;

    $result = $service->update($admin, [
        'email' => 'account-test@example.invalid',
        'phone' => '+1 (555) 010-2030',
        'preferred_login_method' => 'phone',
        'current_password' => 'Current-Test-Password-49',
        'password' => 'New-Test-Password-73',
    ]);
    $admin->refresh();

    $checks['forced password flag clears only after a valid replacement'] = !$admin->must_change_password
        && Hash::check('New-Test-Password-73', $admin->password)
        && ($result['password_changed'] ?? false);
    $checks['contact changes clear verification state'] = $admin->email_verified_at === null
        && $admin->phone_verified_at === null
        && (int) $admin->phone_verified === 0
        && ($result['phone_verification_required'] ?? false);
    $checks['phone values normalize to E.164'] = $admin->phone === '+15550102030';
    $checks['unverified phone keeps password preference during setup'] = $admin->preferred_login_method === 'password';
    $checks['phone can resolve password credentials'] = array_key_exists(
        'phone',
        LoginCredentialResolver::resolve('+1 (555) 010-2030', 'New-Test-Password-73')
    );
    $checks['email can resolve password credentials'] = array_key_exists(
        'email',
        LoginCredentialResolver::resolve('ACCOUNT-TEST@EXAMPLE.INVALID', 'New-Test-Password-73')
    );
} finally {
    DB::rollBack();
}

$middleware = file_get_contents(__DIR__ . '/../../app/Http/Middleware/RequirePasswordChange.php');
$checks['forced setup permits only account setup and logout'] = is_string($middleware)
    && str_contains($middleware, "'liteback.profile.password.update'")
    && str_contains($middleware, "'frontend.auth.logout'")
    && str_contains($middleware, 'must_change_password');

$forcedUser = new User();
$forcedUser->must_change_password = true;
$blockedRequest = Request::create('/private', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$blockedRequest->setUserResolver(static fn () => $forcedUser);
$blockedRoute = (new Route(['GET'], '/private', static fn () => null))->name('private');
$blockedRequest->setRouteResolver(static fn () => $blockedRoute);
$blockedResponse = app(RequirePasswordChange::class)->handle($blockedRequest, static fn () => response('unexpected'));
$blockedPayload = json_decode((string) $blockedResponse->getContent(), true);
$checks['forced setup blocks other JSON requests with an account-setup redirect'] = $blockedResponse->getStatusCode() === 409
    && str_ends_with((string) ($blockedPayload['redirect'] ?? ''), '/liteback/profile/password');

$allowedRequest = Request::create('/liteback/profile/password', 'GET');
$allowedRequest->setUserResolver(static fn () => $forcedUser);
$allowedRoute = (new Route(['GET'], '/liteback/profile/password', static fn () => null))->name('liteback.profile.password');
$allowedRequest->setRouteResolver(static fn () => $allowedRoute);
$allowedResponse = app(RequirePasswordChange::class)->handle($allowedRequest, static fn () => response('allowed', 204));
$checks['forced setup still allows the account security screen'] = $allowedResponse->getStatusCode() === 204;

$phoneAuthController = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Web/Frontend/Auth/MultiAuthController.php');
$checks['phone auto-registration lets the User model hash its generated password once'] = is_string($phoneAuthController)
    && str_contains($phoneAuthController, "'password' => Str::random(32)")
    && !str_contains($phoneAuthController, "'password' => Hash::make(Str::random");

$litebackUserController = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Web/Liteback/UserController.php');
$checks['admin user creation lets the User model hash the password once'] = is_string($litebackUserController)
    && str_contains($litebackUserController, '$user->password = $request->input(\'password\');')
    && !str_contains($litebackUserController, 'bcrypt(')
    && !str_contains($litebackUserController, 'Hash::make(');

$settingsController = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Web/Liteback/SystemSettingsController.php');
$settingsView = file_get_contents(__DIR__ . '/../../resources/views/liteback/settings/index.blade.php');
$loginController = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Web/Frontend/Auth/AuthController.php');
$apiLoginController = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Api/Auth/AuthController.php');
$loginModal = file_get_contents(__DIR__ . '/../../resources/views/frontend/Minimal/partials/modals.blade.php');

$checks['store controls expose independent WhatsApp and password sign-in methods first'] = is_string($settingsView)
    && str_contains($settingsView, 'name="enable_whatsapp_otp"')
    && str_contains($settingsView, 'name="enable_password_login"')
    && strpos($settingsView, 'Store-wide Player Sign-in Methods') < strpos($settingsView, 'Module Killswitches');
$checks['store controls reject disabling every player sign-in method'] = is_string($settingsController)
    && str_contains($settingsController, "'enable_password_login' => 'required|in:0,1'")
    && str_contains($settingsController, "'enable_whatsapp_otp' => 'required|in:0,1'")
    && str_contains($settingsController, "Keep at least one player sign-in method enabled for the store.");
$checks['WhatsApp sign-in toggle is enforced by both OTP endpoints'] = is_string($phoneAuthController)
    && substr_count($phoneAuthController, "settings('enable_whatsapp_otp', '1')") >= 2;
$checks['password sign-in toggle is enforced by web and API logins'] = is_string($loginController)
    && is_string($apiLoginController)
    && str_contains($loginController, "settings('enable_password_login', '1')")
    && str_contains($apiLoginController, "settings('enable_password_login', '1')");
$checks['login modal only shows enabled store sign-in methods'] = is_string($loginModal)
    && str_contains($loginModal, '$whatsAppLoginEnabled')
    && str_contains($loginModal, '$passwordLoginEnabled');

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' account security checks' . PHP_EOL;
