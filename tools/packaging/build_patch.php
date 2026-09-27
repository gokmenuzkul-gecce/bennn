<?php
/** Build an immutable delta from two clean source snapshots (or extracted Git tags).
 * Usage: php build_patch.php before-dir after-dir recipe.json signing-key.pem output.zip
 * Only files explicitly named in recipe.files / recipe.delete are packaged.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!class_exists(\VanguardLTE\Services\PatchPackage::class)) require_once __DIR__ . '/../../casino/app/Services/PatchPackage.php';
use VanguardLTE\Services\PatchPackage;

function buildPromexPatch(string $before, string $after, array $recipe, string $key, string $output): void
{
    if (file_exists($output)) throw new RuntimeException('Patch packages are immutable; choose a new filename/version.');
    $m = $recipe; $m['format'] = 1; $m['files'] = []; $m['delete'] = [];
    foreach ($recipe['files'] as $path) {
        if (!PatchPackage::allowedPath($path, $recipe['component']) || !is_file($after . '/' . $path)) throw new RuntimeException('Invalid source file: ' . $path);
        $m['files'][$path] = ['before' => is_file($before . '/' . $path) ? hash_file('sha256', $before . '/' . $path) : null, 'after' => hash_file('sha256', $after . '/' . $path)];
    }
    foreach ($recipe['delete'] ?? [] as $path) {
        if (!PatchPackage::allowedPath($path, $recipe['component']) || !is_file($before . '/' . $path) || file_exists($after . '/' . $path)) throw new RuntimeException('Invalid removed file: ' . $path);
        $m['delete'][$path] = hash_file('sha256', $before . '/' . $path);
    }
    $payload = json_encode($m, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Signing failed.');
    $envelope = ['signed_payload' => $payload, 'signature' => base64_encode($signature)];
    $private = openssl_pkey_get_private($key); $public = openssl_pkey_get_details($private)['key'];
    PatchPackage::manifest($envelope, $public);
    $zip = new ZipArchive(); if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create ZIP.');
    $zip->addFromString('patch.json', json_encode($envelope, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    foreach ($m['files'] as $path => $hash) $zip->addFile($after . '/' . $path, 'files/' . $path);
    if (!$zip->close()) throw new RuntimeException('Cannot finalize ZIP.');
    PatchPackage::inspect($output, $public);
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($argc !== 6) { fwrite(STDERR, "Usage: php build_patch.php before-dir after-dir recipe.json signing-key.pem output.zip\n"); exit(2); }
    buildPromexPatch($argv[1], $argv[2], json_decode(file_get_contents($argv[3]), true, 32, JSON_THROW_ON_ERROR), file_get_contents($argv[4]), $argv[5]);
    echo 'Patch verified: ' . $argv[5] . '\nSHA256: ' . hash_file('sha256', $argv[5]) . PHP_EOL;
}
