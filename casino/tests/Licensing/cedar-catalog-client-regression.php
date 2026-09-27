<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use VanguardLTE\Services\PromexCedarCatalogService;
use VanguardLTE\Services\PromexInstallationService;

$checks = 0;
function catalogClientCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
}
$options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => __DIR__ . '/openssl-test.cnf'];
$private = openssl_pkey_new($options);
if (!$private || !openssl_pkey_export($private, $privatePem, null, $options)) throw new RuntimeException('Test key unavailable.');
$public = (string) openssl_pkey_get_details($private)['key'];
$temp = sys_get_temp_dir() . '/promex-catalog-client-' . bin2hex(random_bytes(8));
mkdir($temp . '/framework', 0700, true);
$app = new Application(dirname(__DIR__, 2));
$app->useStoragePath($temp);
$app->instance('config', new Repository([
    'app' => ['url' => 'https://operator.example', 'name' => 'Catalog test'],
    'licensing' => [
        'hub_url' => 'https://hub.invalid/api/service', 'hub_timeout' => 2,
        'public_key' => $public, 'cedar_public_origin' => 'https://clients.377.live',
    ],
]));
Facade::setFacadeApplication($app);
$fake = static function (array $response, int $status = 200): void {
    Http::swap(new Factory()); Http::preventStrayRequests();
    Http::fake(['https://hub.invalid/*' => Http::response($response, $status)]);
};
$sign = static function (array $payload) use ($private): array {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    openssl_sign($json, $signature, $private, OPENSSL_ALGO_SHA256);
    return ['signed_payload' => $json, 'signature' => base64_encode($signature)];
};
$installationId = 'inst_' . str_repeat('a', 32);
$secret = rtrim(strtr(base64_encode(str_repeat('s', 32)), '+/', '-_'), '=');
$activation = [
    'status' => 'active', 'installation_id' => $installationId, 'installation_secret' => $secret,
    'domain' => 'operator.example', 'features' => ['cedar_games'], 'games' => ['Cedarcules'],
    'valid_until' => '2030-01-01 00:00:00',
];

try {
    $fake($activation, 201);
    PromexInstallationService::activate('test-license');
    $service = new PromexCedarCatalogService();
    $now = time();
    $game = [
        'id' => 'Cedarcules', 'title' => 'Cedarcules', 'version' => '2.0.0', 'availability' => 'active',
        'entry_path' => '/cedar/games/Cedarcules/index.html',
        'manifest' => ['engine' => 'slot', 'layout' => 'standard-5x3'],
    ];
    $fake($sign([
        'version' => 1, 'type' => 'cedar_catalog', 'installation_id' => $installationId,
        'catalog_version' => '2026.09.21.1', 'issued_at' => $now, 'expires_at' => $now + 300, 'games' => [$game],
    ]));
    $catalog = $service->catalog();
    catalogClientCheck(count($catalog['games']) === 1 && $catalog['games'][0]['id'] === 'Cedarcules',
        'signed entitled catalog is accepted');
    Http::assertSent(fn ($request) => $request->url() === 'https://hub.invalid/api/service/cedar/catalog'
        && $request->method() === 'GET' && ($request->header('X-Promex-Installation')[0] ?? null) === $installationId);
    ++$checks;

    $token = rtrim(strtr(base64_encode(str_repeat('t', 32)), '+/', '-_'), '=');
    $launchPayload = [
        'version' => 1, 'type' => 'cedar_launch', 'launch_id' => '11111111-2222-4333-8444-555555555555',
        'installation_id' => $installationId, 'operator_domain' => 'operator.example',
        'game' => 'Cedarcules', 'game_version' => '2.0.0',
        'entry_path' => '/cedar/games/Cedarcules/index.html', 'launch_token' => $token,
        'issued_at' => $now, 'expires_at' => $now + 120,
    ];
    $fake($sign($launchPayload));
    $launch = $service->launch('Cedarcules');
    catalogClientCheck(
        $launch['launch_url'] === 'https://clients.377.live/cedar/games/Cedarcules/index.html?promex_remote=1#promex_launch=' . $token,
        'launch URL is constructed from the trusted origin and signed path'
    );
    Http::assertSent(fn ($request) => $request->url() === 'https://hub.invalid/api/service/cedar/launch'
        && $request->body() === '{"game":"Cedarcules"}' && !str_contains($request->body(), $secret));
    ++$checks;

    $malicious = $launchPayload;
    $malicious['entry_path'] = 'https://attacker.invalid/game.html';
    $fake($sign($malicious));
    try {
        $service->launch('Cedarcules');
        catalogClientCheck(false, 'signed arbitrary launch URL is rejected');
    } catch (RuntimeException) {
        catalogClientCheck(true, 'signed arbitrary launch URL is rejected');
    }
    $expired = $launchPayload; $expired['expires_at'] = $now - 1;
    $fake($sign($expired));
    try {
        $service->launch('Cedarcules');
        catalogClientCheck(false, 'expired launch is rejected');
    } catch (RuntimeException) {
        catalogClientCheck(true, 'expired launch is rejected');
    }
    echo "PASS: {$checks} Cedar catalog client checks\n";
} finally {
    PromexInstallationService::forget(); Facade::clearResolvedInstances();
    $credential = $temp . '/framework/promex.installation';
    if (is_file($credential)) @unlink($credential);
    if (is_dir($temp . '/framework')) @rmdir($temp . '/framework');
    if (is_dir($temp)) @rmdir($temp);
}
