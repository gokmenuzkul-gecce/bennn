<?php

// Isolated regression: no application .env, database, or live network access.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use VanguardLTE\Services\PromexInstallationService;

$checks = 0;
function installationCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    ++$checks;
}

$temp = sys_get_temp_dir() . '/promex-installation-test-' . bin2hex(random_bytes(8));
mkdir($temp . '/framework', 0700, true);
$app = new Application(dirname(__DIR__, 2));
$app->useStoragePath($temp);
$app->instance('config', new Repository([
    'app' => ['url' => 'https://audit.invalid', 'name' => 'Regression install'],
    'licensing' => ['hub_url' => 'https://hub.invalid/api/service', 'hub_timeout' => 2],
]));
Facade::setFacadeApplication($app);
Http::swap(new Factory());
Http::preventStrayRequests();

$installationId = 'inst_' . str_repeat('a', 32);
$secret = rtrim(strtr(base64_encode(str_repeat('s', 32)), '+/', '-_'), '=');
$response = [
    'status' => 'active',
    'installation_id' => $installationId,
    'installation_secret' => $secret,
    'domain' => 'audit.invalid',
    'features' => ['cedar_games', 'sportsbook_hub'],
    'games' => ['Cedarcules'],
    'valid_until' => '2030-01-01 00:00:00',
];

try {
    Http::fake(['https://hub.invalid/*' => Http::response($response, 201)]);
    $status = PromexInstallationService::activate('test-license');
    installationCheck(($status['status'] ?? '') === 'active', 'activation returns active public status');
    installationCheck(!array_key_exists('installation_secret', $status), 'public activation status excludes secret');
    installationCheck(is_file($temp . '/framework/promex.installation'), 'credential file created');
    installationCheck(PromexInstallationService::credentials()['installation_id'] === $installationId, 'stored credential reloads');
    installationCheck(strlen(PromexInstallationService::credentials()['license_fingerprint']) === 64, 'stored credential binds to license fingerprint');
    Http::assertSent(fn ($request) => $request->url() === 'https://hub.invalid/api/service/installations/activate'
        && ($request['license_key'] ?? null) === 'test-license'
        && ($request['domain'] ?? null) === 'audit.invalid');
    ++$checks;

    $body = '{"game":"Cedarcules"}';
    $timestamp = 1800000000;
    $requestId = str_repeat('b', 32);
    $headers = PromexInstallationService::signedHeaders('post', '/api/service/cedar/spin?ignored=yes', $body, $timestamp, $requestId);
    $canonical = "POST\n/api/service/cedar/spin?ignored=yes\n{$timestamp}\n{$requestId}\n" . hash('sha256', $body);
    installationCheck($headers['X-Promex-Installation'] === $installationId, 'signed request identifies installation');
    installationCheck($headers['X-Promex-Signature'] === hash_hmac('sha256', $canonical, $secret), 'signature matches Hub canonical request');
    $queryHeaders = PromexInstallationService::signedHeaders('GET', '/api/service/packs/download?pack=latest_core', '', $timestamp, str_repeat('c', 32));
    $queryCanonical = "GET\n/api/service/packs/download?pack=latest_core\n{$timestamp}\n" . str_repeat('c', 32) . "\n" . hash('sha256', '');
    installationCheck($queryHeaders['X-Promex-Signature'] === hash_hmac('sha256', $queryCanonical, $secret), 'signature binds GET query parameters');
    installationCheck(PromexInstallationService::ensureActivated('test-license')['installation_id'] === $installationId, 'matching license reuses installation without network');

    config()->set('app.url', 'https://copied.invalid');
    installationCheck(PromexInstallationService::credentials() === null, 'copied credential fails closed on another domain');
    try {
        PromexInstallationService::signedHeaders('GET', '/api/service/status');
        installationCheck(false, 'missing valid credential rejected');
    } catch (RuntimeException) {
        installationCheck(true, 'missing valid credential rejected');
    }

    config()->set('app.url', 'https://audit.invalid');
    Http::fake(['https://hub.invalid/*' => Http::response(array_replace($response, ['domain' => 'other.invalid']), 201)]);
    try {
        PromexInstallationService::activate('test-license');
        installationCheck(false, 'mismatched activation domain rejected');
    } catch (RuntimeException) {
        installationCheck(true, 'mismatched activation domain rejected');
    }
    installationCheck(PromexInstallationService::credentials()['installation_id'] === $installationId, 'invalid response does not overwrite credential');

    PromexInstallationService::forget();
    installationCheck(!is_file($temp . '/framework/promex.installation'), 'credential can be removed locally');
    echo "PASS: {$checks} installation client checks\n";
} finally {
    Facade::clearResolvedInstances();
    $credential = $temp . '/framework/promex.installation';
    if (is_file($credential)) { @unlink($credential); }
    if (is_dir($temp . '/framework')) { @rmdir($temp . '/framework'); }
    if (is_dir($temp)) { @rmdir($temp); }
}
