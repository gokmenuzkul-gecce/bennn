<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$installer = file_get_contents(__DIR__ . '/../../../install.php');
if (!is_string($installer)) {
    throw new RuntimeException('Installer source is unavailable.');
}

$checks = [
    'requires PHP 8.3 for Laravel 13' => str_contains($installer, "version_compare(PHP_VERSION, '8.3.0', '>=')"),
    'collects the license in a non-URL form field' => str_contains($installer, 'name="license_key"')
        && str_contains($installer, 'type="password"'),
    'stores the submitted license in application settings' => str_contains($installer, "WHERE `key` = 'license_key'")
        && str_contains($installer, "VALUES ('license_key', ?)"),
    'runs versioned migrations non-interactively' => str_contains($installer, "->call('migrate', ['--force' => true])"),
    'verifies a signed active license' => str_contains($installer, 'LicenseService::getStatus(true)')
        && str_contains($installer, "!== 'active'"),
    'binds a protected-service installation credential' => str_contains($installer, 'PromexInstallationService::ensureActivated'),
    'uses the fixed first-login administrator credentials' => str_contains($installer, '$adminUser = \'admin\'')
        && str_contains($installer, '$adminPass = \'123456\'')
        && !str_contains($installer, 'name="admin_pass"'),
    'forces the temporary administrator to choose a permanent password' => str_contains($installer, '`must_change_password` = 1')
        && str_contains($installer, 'You must replace this password immediately'),
    'defaults delivery routing to licensed Promex APIs' => str_contains($installer, "'whatsapp_delivery_provider' => 'promex'")
        && str_contains($installer, "'email_delivery_provider' => 'promex'")
        && str_contains($installer, "'WHATSAPP_MODE' => 'promex'"),
    'locks only after migration and activation' => strpos($installer, "->call('migrate'") < strpos($installer, 'file_put_contents($lockFile')
        && strpos($installer, 'PromexInstallationService::ensureActivated') < strpos($installer, 'file_put_contents($lockFile'),
    'keeps Legacy Compatibility explicitly opt-in' => str_contains($installer, 'Legacy Compatibility remains off'),
    'advertises the installed framework generation' => str_contains($installer, 'Laravel 13 • Turnkey Edition'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' one-click installer checks' . PHP_EOL;
