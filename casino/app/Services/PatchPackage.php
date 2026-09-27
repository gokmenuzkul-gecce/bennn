<?php

namespace VanguardLTE\Services;

use RuntimeException;
use ZipArchive;

/** Signed, immutable delta package. No application boot or network required. */
final class PatchPackage
{
    public static function allowedPath(string $path, string $component): bool
    {
        if (!preg_match('#^[A-Za-z0-9_./-]+$#D', $path) || str_contains($path, '..') || str_starts_with($path, '/')
            || preg_match('#(^|/)(vendor|node_modules|storage|uploads?|\.env[^/]*|\.git|tests|cache)(/|$)#i', $path)) return false;
        if ($component === 'demo') return $path === 'public/promex-update-demo.txt'
            || preg_match('#^casino/database/migrations/2099_01_01_00000[12]_promex_update_demo\.php$#D', $path) === 1;
        if (str_starts_with($component, 'legacy:')) {
            $game = substr($component, 7);
            return preg_match('/^[A-Za-z0-9_]+$/D', $game) && !preg_match('/^(Cedar|RoyalSteps)/i', $game)
                && str_starts_with($path, 'games/' . $game . '/') && !str_ends_with($path, '.php');
        }
        if ($path === 'VERSION') return $component === 'core';
        if (in_array($path, ['.htaccess', 'index.php', 'casino/artisan', 'casino/composer.json', 'casino/composer.lock'], true)) return true;
        if (str_starts_with($path, 'casino/app/Games/') || $path === 'casino/app/Services/CedarLegacyGameService.php'
            || str_starts_with($path, 'casino/resources/views/frontend/games/list/')
            || preg_match('#^(public/(games|originals|cedar)|js/(mock-websocket|ws-bridge))#i', $path)) return false;
        foreach (['casino/app/', 'casino/bootstrap/', 'casino/config/', 'casino/database/migrations/', 'casino/resources/', 'casino/routes/', 'frontend/', 'minimal/', 'js/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }

    public static function manifest(array $envelope, string $publicKey): array
    {
        $payload = $envelope['signed_payload'] ?? null;
        $signature = base64_decode((string) ($envelope['signature'] ?? ''), true);
        if (!is_string($payload) || $signature === false || openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Patch signature is invalid.');
        }
        $m = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        if (($m['format'] ?? null) !== 1 || !preg_match('/^[a-z0-9][a-z0-9_-]{0,95}$/D', $m['id'] ?? '')
            || !preg_match('/^(core|demo|feature:[a-z0-9_-]+|cedar:[A-Za-z0-9_-]+|legacy:[A-Za-z0-9_]+)$/D', $m['component'] ?? '')
            || !preg_match('/^\d+\.\d+\.\d+$/D', $m['from'] ?? '') || !preg_match('/^\d+\.\d+\.\d+$/D', $m['to'] ?? '')
            || !version_compare($m['to'], $m['from'], '>') || !is_array($m['files'] ?? null)
            || !is_array($m['delete'] ?? null) || !is_array($m['requires'] ?? null) || !is_array($m['migrations'] ?? null)
            || !is_string($m['title'] ?? null) || !is_string($m['notes'] ?? null)) throw new RuntimeException('Patch manifest is invalid.');
        if (count($m['files']) + count($m['delete']) < 1 || count($m['files']) + count($m['delete']) > 5000) throw new RuntimeException('Invalid patch file count.');
        $seen = [];
        foreach (array_merge(array_keys($m['files']), array_keys($m['delete'])) as $path) {
            if (!self::allowedPath($path, $m['component']) || isset($seen[strtolower($path)])) throw new RuntimeException('Forbidden or duplicate patch path: ' . $path);
            $seen[strtolower($path)] = true;
        }
        foreach ($m['files'] as $path => $hashes) {
            if (in_array($path, ['casino/composer.json', 'casino/composer.lock'], true)) throw new RuntimeException('Dependency changes require a separate deployment; this MVP cannot update vendor.');
            if (!is_array($hashes) || !array_key_exists('before', $hashes) || !self::hash($hashes['after'] ?? null)
                || ($hashes['before'] !== null && !self::hash($hashes['before']))) throw new RuntimeException('Invalid file hashes.');
            if (str_starts_with($path, 'casino/database/migrations/') && ($hashes['before'] !== null || !in_array($path, $m['migrations'], true))) throw new RuntimeException('Migrations must be new and explicitly listed.');
        }
        foreach ($m['delete'] as $path => $hash) {
            if (in_array($path, ['casino/composer.json', 'casino/composer.lock'], true)) throw new RuntimeException('Dependency manifests cannot be removed by a patch.');
            if (!self::hash($hash) || $path === 'VERSION' || str_starts_with($path, 'casino/database/migrations/')) throw new RuntimeException('Invalid deletion.');
        }
        foreach ($m['migrations'] as $path) {
            if (!is_string($path) || !str_starts_with($path, 'casino/database/migrations/') || !str_ends_with($path, '.php') || !isset($m['files'][$path])) throw new RuntimeException('Invalid migration list.');
        }
        foreach ($m['requires'] as $id) if (!is_string($id) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,95}$/D', $id)) throw new RuntimeException('Invalid dependency.');
        if ($m['component'] === 'core' && !isset($m['files']['VERSION'])) throw new RuntimeException('Core patches must include VERSION.');
        if (str_starts_with($m['component'], 'legacy:') && $m['migrations']) throw new RuntimeException('Legacy asset patches cannot run migrations.');
        return $m;
    }

    private static function hash(mixed $value): bool { return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value); }

    public static function inspect(string $archive, string $key): array
    {
        if ($archive === '' || !is_file($archive) || !is_readable($archive)) {
            throw new RuntimeException('Patch upload is missing or unreadable. Select the ZIP and upload it again.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Cannot read patch ZIP.');
        try {
            $raw = $zip->getFromName('patch.json', 2 * 1024 * 1024);
            if ($raw === false) throw new RuntimeException('Missing signed patch.json.');
            $m = self::manifest(json_decode($raw, true, 32, JSON_THROW_ON_ERROR), $key);
            $expected = array_fill_keys(array_merge(['patch.json'], array_map(fn ($p) => 'files/' . $p, array_keys($m['files']))), true);
            $seen = []; $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $name = $stat['name'];
                if (!isset($expected[$name]) || isset($seen[$name])) throw new RuntimeException('Unexpected or duplicate ZIP entry: ' . $name);
                $seen[$name] = true; $size += $stat['size'];
                if ($size > 256 * 1024 * 1024) throw new RuntimeException('Patch exceeds the 256 MB unpacked MVP limit.');
                if ($name !== 'patch.json') {
                    $stream = $zip->getStream($name); if (!$stream) throw new RuntimeException('Unreadable ZIP entry.');
                    $ctx = hash_init('sha256'); hash_update_stream($ctx, $stream); fclose($stream);
                    if (!hash_equals($m['files'][substr($name, 6)]['after'], hash_final($ctx))) throw new RuntimeException('Patch file checksum mismatch.');
                }
            }
            if (count($seen) !== count($expected)) throw new RuntimeException('Patch ZIP is incomplete.');
            if ($m['component'] === 'core' && trim($zip->getFromName('files/VERSION')) !== $m['to']) throw new RuntimeException('VERSION does not match the patch.');
            return $m;
        } finally { $zip->close(); }
    }

    public static function target(string $root, string $path): string
    {
        $target = $root;
        foreach (explode('/', $path) as $segment) {
            $target .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($target)) throw new RuntimeException('Patch targets cannot contain symbolic links: ' . $path);
            if (file_exists($target)) {
                $resolved = realpath($target);
                if (!$resolved || !str_starts_with(strtolower($resolved), strtolower(realpath($root) . DIRECTORY_SEPARATOR))) throw new RuntimeException('Patch target escapes the installation.');
            }
        }
        return $target;
    }

    public static function eligibility(array $m, array $state, string $root): void
    {
        foreach ($state['history'] ?? [] as $run) if (in_array($run['status'], ['installing', 'failed'], true)) throw new RuntimeException('A previous patch needs manual recovery. Resolve it before installing another patch.');
        if (isset($state['installed'][$m['id']])) throw new RuntimeException('This patch is already installed.');
        $current = $state['versions'][$m['component']] ?? ($m['component'] === 'core' ? trim(file_get_contents($root . '/VERSION')) : '0.0.0');
        if (str_starts_with($m['component'], 'legacy:') && !isset($state['versions'][$m['component']])) {
            $current = is_dir($root . '/games/' . substr($m['component'], 7)) ? '2.0.0' : '0.0.0';
        }
        if ($current !== $m['from']) throw new RuntimeException("Requires {$m['component']} {$m['from']}; installed version is {$current}.");
        foreach ($m['requires'] as $id) if (!isset($state['installed'][$id])) throw new RuntimeException('Install prerequisite first: ' . $id);
        foreach ($m['files'] as $path => $hashes) self::precondition(self::target($root, $path), $hashes['before'], $path);
        foreach ($m['delete'] as $path => $hash) self::precondition(self::target($root, $path), $hash, $path);
    }

    private static function precondition(string $target, ?string $hash, string $path): void
    {
        if ($hash === null ? file_exists($target) : (!is_file($target) || !hash_equals($hash, hash_file('sha256', $target)))) throw new RuntimeException('Local file differs from the required baseline: ' . $path);
    }
}
