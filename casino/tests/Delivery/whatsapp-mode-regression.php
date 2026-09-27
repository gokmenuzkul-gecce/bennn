<?php
declare(strict_types=1);
// Isolated settings/environment doubles: no database changes or network delivery.
$values = [];
$environment = 'production';
function settings($key, $default = null) { global $values; return $values[$key] ?? $default; }
function app() { return new class { public function environment(...$names) { global $environment; return in_array($environment, $names, true); } }; }
require __DIR__ . '/../../app/Services/DeliveryGatewaySettings.php';
require __DIR__ . '/../../app/Services/WhatsAppService.php';
require __DIR__ . '/../../app/Services/AccountPhoneVerification.php';
use VanguardLTE\Services\DeliveryGatewaySettings;
use VanguardLTE\Services\WhatsAppService;
$service = new WhatsAppService();
$check = function ($ok, $name) { if (!$ok) throw new RuntimeException($name); echo "PASS: $name\n"; };
$check(DeliveryGatewaySettings::provider('whatsapp') === 'promex', 'Fresh settings default to PROMEX');
putenv('WHATSAPP_MODE=devmode');
$environment = 'local';
$check(!$service->isDevelopmentMode(), 'Legacy environment override cannot enable simulation');
$values['whatsapp_delivery_provider'] = 'devmode';
$check($service->isDevelopmentMode() && $service->sendOtp('+15550109999', '123456'), 'Explicit local simulation succeeds without network');
$check(str_contains((new VanguardLTE\Services\AccountPhoneVerification())->unavailableReason(), 'sends no WhatsApp'), 'Account verification explains simulation rather than claiming delivery');
$environment = 'production';
$check(!$service->isDevelopmentMode() && !$service->sendOtp('+15550109999', '123456'), 'Production fails closed even with stale development selection');
$values['whatsapp_delivery_provider'] = 'promex';
$check(!$service->isDevelopmentMode(), 'Backend PROMEX selection disables simulation');
$values['whatsapp_delivery_provider'] = 'custom';
$check(!$service->isDevelopmentMode(), 'Custom delivery never exposes a development code');
echo "ALL WHATSAPP MODE CHECKS PASSED\n";
