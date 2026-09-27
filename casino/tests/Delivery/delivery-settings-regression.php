<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use VanguardLTE\Services\DeliveryGatewaySettings;
use VanguardLTE\Services\WhatsAppService;

$checks = [];

$checks['Promex WhatsApp is gated while email remains opt-in'] = DeliveryGatewaySettings::provider('whatsapp') === 'promex'
    && DeliveryGatewaySettings::provider('email') === 'disabled';
$checks['Promex WhatsApp path remains an installation-authenticated API route'] = DeliveryGatewaySettings::PROMEX_WHATSAPP_PATH === '/api/service/delivery/whatsapp/otp';

$whatsAppSource = file_get_contents(__DIR__ . '/../../app/Services/WhatsAppService.php');
$emailSource = file_get_contents(__DIR__ . '/../../app/Services/EmailDeliveryService.php');
$controllerSource = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Web/Frontend/Auth/MultiAuthController.php');
$settingsView = file_get_contents(__DIR__ . '/../../resources/views/liteback/settings/index.blade.php');
$routes = file_get_contents(__DIR__ . '/../../routes/web.php');

$checks['Promex WhatsApp requests use installation signatures'] = str_contains((string) $whatsAppSource, 'PromexInstallationService::signedHeaders');
$checks['email presets use their documented provider APIs'] = str_contains((string) $emailSource, 'https://api.brevo.com/v3/smtp/email')
    && str_contains((string) $emailSource, "'api-key' => DeliveryGatewaySettings::secret('email')")
    && str_contains((string) $emailSource, 'https://api.resend.com/emails')
    && str_contains((string) $emailSource, 'https://api.postmarkapp.com/email')
    && str_contains((string) $emailSource, "'X-Postmark-Server-Token' => DeliveryGatewaySettings::secret('email')");
$checks['custom providers require HTTPS endpoints and encrypted tokens'] = str_contains(
    (string) file_get_contents(__DIR__ . '/../../app/Http/Controllers/Web/Liteback/SystemSettingsController.php'),
    "'regex:/^https:\\/\\//i'"
) && str_contains(
    (string) file_get_contents(__DIR__ . '/../../app/Services/DeliveryGatewaySettings.php'),
    "'enc:' . Crypt::encryptString"
);
$checks['saved provider tokens are never rendered back into forms'] = substr_count((string) $settingsView, 'autocomplete="new-password"') >= 2
    && !str_contains((string) $settingsView, "value=\"{{ settings('whatsapp_api_token'")
    && !str_contains((string) $settingsView, "value=\"{{ settings('email_api_token'");
$checks['email presets require a verified sender and encrypted token'] = str_contains((string) $emailSource, 'filter_var($sender[\'address\'], FILTER_VALIDATE_EMAIL)')
    && str_contains((string) $settingsView, 'id="emailDeliveryProvider"')
    && str_contains((string) $settingsView, 'Brevo — recommended free transactional email');
$checks['failed WhatsApp sends clear pending OTP state'] = str_contains((string) $controllerSource, "session()->forget(['pending_phone', 'pending_otp', 'pending_otp_expires'])")
    && str_contains((string) $controllerSource, '$user->otp_code = null;');
$checks['OTP generation is cryptographically secure'] = strlen(WhatsAppService::generateOtp()) === 6
    && str_contains((string) $whatsAppSource, 'random_int(100000, 999999)');
$checks['OTP send and verification endpoints are throttled'] = str_contains((string) $routes, "->middleware('throttle:5,1')")
    && str_contains((string) $routes, "->middleware('throttle:10,1')");

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' delivery configuration checks' . PHP_EOL;
