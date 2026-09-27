<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use VanguardLTE\Services\PromexCedarService;
use VanguardLTE\Services\PromexInstallationService;

$checks = 0;
function cedarClientCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
}

$keyOptions = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'config' => __DIR__ . '/openssl-test.cnf',
];
$private = openssl_pkey_new($keyOptions);
if (!$private || !openssl_pkey_export($private, $privatePem, null, $keyOptions)) {
    throw new RuntimeException('OpenSSL test-key generation unavailable.');
}
$public = (string) openssl_pkey_get_details($private)['key'];
$temp = sys_get_temp_dir() . '/promex-cedar-client-' . bin2hex(random_bytes(8));
mkdir($temp . '/framework', 0700, true);
$app = new Application(dirname(__DIR__, 2));
$app->useStoragePath($temp);
$app->instance('config', new Repository([
    'app' => ['url' => 'https://audit.invalid', 'name' => 'Cedar regression'],
    'licensing' => [
        'hub_url' => 'https://hub.invalid/api/service',
        'hub_timeout' => 2,
        'public_key' => $public,
    ],
]));
Facade::setFacadeApplication($app);
$fake = static function (array $response, int $status = 200): void {
    Http::swap(new Factory());
    Http::preventStrayRequests();
    Http::fake(['https://hub.invalid/*' => Http::response($response, $status)]);
};

$installationId = 'inst_' . str_repeat('a', 32);
$secret = rtrim(strtr(base64_encode(str_repeat('s', 32)), '+/', '-_'), '=');
$activation = [
    'status' => 'active', 'installation_id' => $installationId,
    'installation_secret' => $secret, 'domain' => 'audit.invalid',
    'features' => ['cedar_games'], 'games' => ['Cedarcules'],
    'valid_until' => '2030-01-01 00:00:00',
];
$serverSeed = str_repeat('ab', 32);
$serverSeedHash = hash('sha256', $serverSeed);
$nextHash = hash('sha256', str_repeat('cd', 32));
$requestId = str_repeat('b', 32);
$scopeId = str_repeat('1', 32);
$roundId = '11111111-2222-4333-8444-555555555555';

try {
    $fake($activation, 201);
    PromexInstallationService::activate('test-license');
    $client = new PromexCedarService();

    $fake([
        'status' => 'success', 'game' => 'Cedarcules',
        'server_seed_hash' => $serverSeedHash, 'nonce' => 1,
        'presentation' => [
            'rows' => 3, 'reels' => 5, 'lines' => 20, 'wild' => 1,
            'symbols' => ['1' => 'S1', '2' => 'S2'], 'published_rtp' => 92.0,
            'min_wager' => '0.20', 'max_wager' => '100.00',
        ],
    ]);
    $init = $client->init('Cedarcules', $scopeId);
    cedarClientCheck($init['server_seed_hash'] === $serverSeedHash, 'init accepts a valid committed seed');
    Http::assertSent(fn ($request) => $request->url() === 'https://hub.invalid/api/service/cedar/init'
        && $request->method() === 'POST'
        && ($request->header('X-Promex-Installation')[0] ?? null) === $installationId
        && $request->body() === '{"game":"Cedarcules","scope_id":"' . str_repeat('1', 32) . '"}');
    ++$checks;

    $signed = [
        'version' => 1, 'round_id' => $roundId, 'request_id' => $requestId,
        'game' => 'Cedarcules', 'scope_id' => $scopeId, 'math_version' => 'cedarcules.v1',
        'wager' => '2.00', 'win_amount' => '4.00', 'multiplier' => 2.0,
        'grid' => ['1', '2', '3'], 'line_wins' => [],
        'server_seed' => $serverSeed, 'server_seed_hash' => $serverSeedHash,
        'next_server_seed_hash' => $nextHash, 'client_seed' => 'browser-seed',
        'nonce' => 1, 'issued_at' => time(),
    ];
    $signedPayload = json_encode($signed, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    openssl_sign($signedPayload, $signature, $private, OPENSSL_ALGO_SHA256);
    $outcome = [
        'game' => 'Cedarcules', 'math_version' => 'cedarcules.v1',
        'wager' => '2.00', 'win_amount' => '4.00', 'multiplier' => 2.0,
        'grid' => ['1', '2', '3'], 'line_wins' => [],
        'proof' => [
            'server_seed' => $serverSeed, 'server_seed_hash' => $serverSeedHash,
            'client_seed' => 'browser-seed', 'nonce' => 1,
        ],
    ];
    $spinResponse = [
        'status' => 'success', 'recovered' => false, 'round_id' => $roundId,
        'request_id' => $requestId, 'outcome' => $outcome,
        'next_server_seed_hash' => $nextHash,
        'proof' => ['signed_payload' => $signedPayload, 'signature' => base64_encode($signature)],
    ];
    $fake($spinResponse);
    $spin = $client->spin('Cedarcules', $scopeId, '2', 'browser-seed', $serverSeedHash, $requestId);
    cedarClientCheck($spin['round_id'] === $roundId && $spin['outcome']['win_amount'] === '4.00',
        'spin accepts an RSA-signed outcome bound to the request');
    Http::assertSent(function ($request) use ($secret, $installationId): bool {
        $body = $request->body();
        $transportId = (string) ($request->header('X-Promex-Request')[0] ?? '');
        $sentAt = (string) ($request->header('X-Promex-Time')[0] ?? '');
        $canonical = "POST\n/api/service/cedar/spin\n{$sentAt}\n{$transportId}\n" . hash('sha256', $body);
        return $request->url() === 'https://hub.invalid/api/service/cedar/spin'
            && ($request->header('X-Promex-Installation')[0] ?? null) === $installationId
            && hash_equals(hash_hmac('sha256', $canonical, $secret), (string) ($request->header('X-Promex-Signature')[0] ?? ''))
            && !str_contains($body, $secret);
    });
    ++$checks;

    $tampered = $spinResponse;
    $tampered['outcome']['win_amount'] = '999.00';
    $fake($tampered);
    try {
        $client->spin('Cedarcules', $scopeId, '2.00', 'browser-seed', $serverSeedHash, $requestId);
        cedarClientCheck(false, 'tampered outcome is rejected');
    } catch (RuntimeException) {
        cedarClientCheck(true, 'tampered outcome is rejected');
    }

    $badSignature = $spinResponse;
    $badSignature['proof']['signature'] = base64_encode(str_repeat("\0", 256));
    $fake($badSignature);
    try {
        $client->spin('Cedarcules', $scopeId, '2.00', 'browser-seed', $serverSeedHash, $requestId);
        cedarClientCheck(false, 'invalid proof signature is rejected');
    } catch (RuntimeException) {
        cedarClientCheck(true, 'invalid proof signature is rejected');
    }

    $arcadeInput = ['game'=>'CedarPlinko','scope_id'=>$scopeId,'command_id'=>$requestId,'payload'=>['action'=>'init']];
    $arcadeSigned = ['type'=>'cedar_arcade_command','installation_id'=>$installationId,'game'=>'CedarPlinko',
        'scope_id'=>$scopeId,'command_id'=>$requestId,'input_hash'=>hash('sha256',json_encode($arcadeInput,JSON_UNESCAPED_SLASHES)),
        'result'=>['status'=>'success','server_seed_hash'=>$serverSeedHash]];
    $signArcade = static function(array $payload) use($private): array {
        $raw=json_encode($payload,JSON_UNESCAPED_SLASHES);openssl_sign($raw,$sig,$private,OPENSSL_ALGO_SHA256);
        return ['signed_payload'=>$raw,'signature'=>base64_encode($sig)];
    };
    $fake($signArcade($arcadeSigned));
    cedarClientCheck($client->arcade('CedarPlinko',$scopeId,$requestId,['action'=>'init'])['server_seed_hash']===$serverSeedHash,'signed arcade response');
    foreach(['installation_id','game','scope_id','command_id','input_hash','type'] as $binding) {
        $bad=$arcadeSigned;$bad[$binding]='wrong';$fake($signArcade($bad));$rejected=false;
        try{$client->arcade('CedarPlinko',$scopeId,$requestId,['action'=>'init']);}catch(RuntimeException){$rejected=true;}
        cedarClientCheck($rejected,'arcade rejects mismatched '.$binding);
    }
    $bad=$signArcade($arcadeSigned);$bad['signed_payload'].=' ';$fake($bad);$rejected=false;
    try{$client->arcade('CedarPlinko',$scopeId,$requestId,['action'=>'init']);}catch(RuntimeException){$rejected=true;}
    cedarClientCheck($rejected,'arcade rejects altered signature');
    echo "PASS: {$checks} Cedar client checks\n";
} finally {
    PromexInstallationService::forget();
    Facade::clearResolvedInstances();
    $credential = $temp . '/framework/promex.installation';
    if (is_file($credential)) @unlink($credential);
    if (is_dir($temp . '/framework')) @rmdir($temp . '/framework');
    if (is_dir($temp)) @rmdir($temp);
}
