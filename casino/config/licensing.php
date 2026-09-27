<?php

$publicKey = \VanguardLTE\Services\LicenseService::PROMEX_PUBLIC_KEY;
$localPublicKeyFile = (string) env('PROMEX_LOCAL_PUBLIC_KEY_FILE', '');
if (env('APP_ENV') === 'local' && $localPublicKeyFile !== '' && is_file($localPublicKeyFile)) {
    $candidate = file_get_contents($localPublicKeyFile);
    if (is_string($candidate) && str_contains($candidate, 'BEGIN PUBLIC KEY')) {
        $publicKey = $candidate;
    }
}
$runtimeProfile = strtolower(trim((string) env('PROMEX_RUNTIME_PROFILE', 'live')));
if (!in_array($runtimeProfile, ['live', 'local'], true)) {
    $runtimeProfile = 'live';
}
$hubHttpOptions = ['allow_redirects' => false];
$localCaFile = (string) env('PROMEX_LOCAL_CA_FILE', '');
if (env('APP_ENV') === 'local' && $localCaFile !== '' && is_file($localCaFile)) {
    $hubHttpOptions['verify'] = $localCaFile;
}

return [
    // Deployment trust anchor. Never load this value from a request, database setting, or the hub response.
    // A development key file is accepted only in APP_ENV=local and is excluded from release packages.
    'public_key' => $publicKey,
    'hub_url' => env('PROMEX_HUB_URL', \VanguardLTE\Services\LicenseService::DEFAULT_SERVER),
    'hub_timeout' => (int) env('PROMEX_HUB_TIMEOUT', 8),
    'cedar_public_origin' => env('PROMEX_CEDAR_PUBLIC_ORIGIN', 'https://clients.377.live'),
    'hub_version_url' => env('PROMEX_HUB_VERSION_URL', 'https://clients.377.live/api/service/version'),
    'runtime_profile' => $runtimeProfile,
    'hub_http_options' => $hubHttpOptions,
    'auto_activate_installation' => filter_var(env('PROMEX_AUTO_ACTIVATE', true), FILTER_VALIDATE_BOOL),
    'official_url' => 'https://promex.me/platforms/promex-gaming-suite/',
];
