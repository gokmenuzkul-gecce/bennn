<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class PatchManager
{
    public function root(): string { return dirname(base_path()); }
    public function directory(): string
    {
        $dir = storage_path('app/patch-manager');
        File::ensureDirectoryExists($dir, 0700);
        return $dir;
    }
    public function demo(): bool
    {
        return app()->environment('local') && (bool) config('patches.demo')
            && str_ends_with(strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)), '.test');
    }
    private function key(): string
    {
        if ($this->demo()) {
            $path = config('patches.demo_public_key');
            if (!is_string($path) || !is_file($path)) throw new RuntimeException('Configure the practice public-key file first.');
            return file_get_contents($path);
        }
        return (string) config('licensing.public_key');
    }
    public function authorize(): void
    {
        if (!LicenseService::isLicensed()) throw new RuntimeException('An active license is required for managed updates, including hosted practice patches.');
    }
    public function state(): array
    {
        $path = $this->directory() . '/history.json';
        return is_file($path) ? json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR) : ['versions' => [], 'installed' => [], 'history' => []];
    }
    private function save(array $state): void
    {
        $path = $this->directory() . '/history.json';
        $temp = $path . '.tmp';
        if (file_put_contents($temp, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temp, $path)) throw new RuntimeException('Cannot save patch history.');
    }
    public function locked(callable $work): mixed
    {
        $handle = fopen($this->directory() . '/operation.lock', 'c');
        if (!$handle) throw new RuntimeException('Cannot open maintenance lock.');
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); throw new RuntimeException('Another backup or patch operation is running.'); }
        try { return $work(); } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
    public function package(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $token)) throw new RuntimeException('Invalid staged patch.');
        $path = $this->directory() . '/' . $token . '.zip';
        if (!is_file($path)) throw new RuntimeException('Staged patch is missing. Fetch it from the update catalog again.');
        return $path;
    }
    public function preview(string $archive): array
    {
        $this->authorize();
        $m = PatchPackage::inspect($archive, $this->key());
        if (($m['component'] === 'demo') !== $this->demo()) throw new RuntimeException('Practice packages and release packages use separate modes.');
        $reason = null;
        try { PatchPackage::eligibility($m, $this->state(), $this->root()); } catch (RuntimeException $e) { $reason = $e->getMessage(); }
        return ['manifest' => $m, 'blocked' => $reason, 'sha256' => hash_file('sha256', $archive)];
    }
    public function stage(string $source): array
    {
        $preview = $this->preview($source);
        $token = bin2hex(random_bytes(16));
        if (!copy($source, $this->directory() . '/' . $token . '.zip')) throw new RuntimeException('Cannot stage patch.');
        return $preview + ['token' => $token];
    }
    public function catalog(): array
    {
        $this->authorize();
        $target = '/api/service/updates?channel=' . ($this->demo() ? 'practice' : 'stable');
        $url = rtrim((string) config('licensing.hub_url'), '/') . '/updates?channel=' . ($this->demo() ? 'practice' : 'stable');
        $response = Http::timeout(15)->withHeaders(PromexInstallationService::signedHeaders('GET', $target))
            ->withOptions((array) config('licensing.hub_http_options'))->get($url);
        if (!$response->successful()) throw new RuntimeException('Update catalog unavailable or access denied. Check your license and service connection.');
        $rows = $response->json('patches');
        if (!is_array($rows) || count($rows) > 500) throw new RuntimeException('Invalid patch catalog.');
        foreach ($rows as &$row) {
            if (!preg_match('/^[a-z0-9_-]+$/D', $row['slug'] ?? '')) throw new RuntimeException('Invalid patch catalog item.');
            $row['manifest'] = PatchPackage::manifest($row['envelope'], $this->key());
            if (($row['manifest']['component'] === 'demo') !== $this->demo()) throw new RuntimeException('Unexpected patch channel.');
            try { PatchPackage::eligibility($row['manifest'], $this->state(), $this->root()); $row['blocked'] = null; }
            catch (RuntimeException $e) { $row['blocked'] = $e->getMessage(); }
        }
        return $rows;
    }
    public function fetch(string $slug): array
    {
        $this->authorize();
        if (!preg_match('/^patch-[a-z0-9_-]{1,90}$/D', $slug)) throw new RuntimeException('Invalid patch selection.');
        $query = 'channel=' . ($this->demo() ? 'practice' : 'stable') . '&patch=' . $slug;
        $target = '/api/service/updates/download?' . $query;
        $temp = $this->directory() . '/' . bin2hex(random_bytes(16)) . '.download';
        try {
            $response = Http::timeout(180)->withHeaders(PromexInstallationService::signedHeaders('GET', $target))
                ->withOptions(array_merge((array) config('licensing.hub_http_options'), ['sink' => $temp, 'progress' => function ($total, $downloaded) {
                    if ($total > 128 * 1024 * 1024 || $downloaded > 128 * 1024 * 1024) throw new RuntimeException('Patch exceeds the 128 MB download limit.');
                }]))->get(rtrim((string) config('licensing.hub_url'), '/') . '/updates/download?' . $query);
            if (!$response->successful()) throw new RuntimeException('Patch download failed.');
            return $this->stage($temp);
        } finally { if (is_file($temp)) unlink($temp); }
    }
    public function install(string $token, string $checksum): string
    {
        return $this->locked(function () use ($token, $checksum) {
            $archive = $this->package($token); $preview = $this->preview($archive); $m = $preview['manifest'];
            if (!hash_equals($preview['sha256'], $checksum)) throw new RuntimeException('The staged patch changed. Review it again.');
            if (str_starts_with($m['component'], 'legacy:')) throw new RuntimeException('Legacy folders must be replaced manually, then verified.');
            if ($preview['blocked']) throw new RuntimeException($preview['blocked']);
            $php = new Process([(string) config('patches.php_binary'), '-r', 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'], base_path());
            $php->setTimeout(15)->run();
            if (!$php->isSuccessful()) throw new RuntimeException('Set PROMEX_PHP_BINARY to a working PHP 8.3+ CLI before installing patches.');
            if (app()->isDownForMaintenance()) throw new RuntimeException('The site is already in maintenance mode. Finish that operation first.');
            // Exercise the web worker's CLI environment before changing files or going offline.
            $this->artisan(['optimize:clear']);
            $this->artisan(['route:list', '--name=liteback', '--json']);
            $state = $this->state(); $run = bin2hex(random_bytes(8));
            $state['history'][$run] = ['id' => $m['id'], 'component' => $m['component'], 'from' => $m['from'], 'to' => $m['to'], 'status' => 'installing', 'at' => gmdate('c'), 'message' => 'Installation started'];
            $this->save($state);
            try {
                if (Artisan::call('down') !== 0) throw new RuntimeException('Cannot enable maintenance mode.');
                $zip = new ZipArchive(); if ($zip->open($archive) !== true) throw new RuntimeException('Cannot open patch.');
                try {
                    foreach ($m['files'] as $path => $hashes) {
                        $target = PatchPackage::target($this->root(), $path); File::ensureDirectoryExists(dirname($target));
                        $stream = $zip->getStream('files/' . $path); $temp = $target . '.patch-tmp'; $output = fopen($temp, 'wb');
                        if (!$stream || !$output) throw new RuntimeException('Cannot write: ' . $path);
                        stream_copy_to_stream($stream, $output); fclose($stream); fclose($output);
                        if (!hash_equals($hashes['after'], hash_file('sha256', $temp)) || !rename($temp, $target)) throw new RuntimeException('Cannot replace: ' . $path);
                        if (function_exists('opcache_invalidate')) opcache_invalidate($target, true);
                    }
                } finally { $zip->close(); }
                foreach ($m['delete'] as $path => $hash) if (!unlink(PatchPackage::target($this->root(), $path))) throw new RuntimeException('Cannot remove: ' . $path);
                // Each approved new migration runs in a fresh PHP process against this installation.
                foreach ($m['migrations'] as $path) $this->artisan(['migrate', '--force', '--realpath', '--path=' . PatchPackage::target($this->root(), $path)]);
                $this->artisan(['optimize:clear']);
                $this->artisan(['route:list', '--name=liteback', '--json']);
                $state['versions'][$m['component']] = $m['to']; $state['installed'][$m['id']] = $checksum;
                $state['history'][$run]['status'] = 'installed'; $state['history'][$run]['message'] = 'Installed successfully';
                $this->save($state);
                Artisan::call('up');
                return 'Patch installed: ' . $m['id'];
            } catch (\Throwable $e) {
                unset($state['installed'][$m['id']]);
                $state['versions'][$m['component']] = $m['from'];
                $state['history'][$run]['status'] = 'failed'; $state['history'][$run]['message'] = $e->getMessage();
                $this->save($state);
                // Do not reopen a potentially inconsistent installation or pretend to restore its database.
                throw new RuntimeException('Patch failed. Site remains in maintenance mode. Restore your backup manually; see PATCHES.md. ' . $e->getMessage(), 0, $e);
            }
        });
    }
    public function verifyLegacy(string $token): string
    {
        return $this->locked(function () use ($token) {
            $this->authorize(); $archive = $this->package($token); $m = PatchPackage::inspect($archive, $this->key());
            if ($this->demo() || !str_starts_with($m['component'], 'legacy:')) throw new RuntimeException('Only legacy folders support manual verification.');
            $state = $this->state();
            // Check versions/dependencies without the pre-replacement file conditions.
            $eligibility = $m; $eligibility['files'] = []; $eligibility['delete'] = [];
            PatchPackage::eligibility($eligibility, $state, $this->root());
            foreach ($m['files'] as $path => $hashes) {
                $target = PatchPackage::target($this->root(), $path);
                if (!is_file($target) || !hash_equals($hashes['after'], hash_file('sha256', $target))) throw new RuntimeException('Replacement not verified: ' . $path);
            }
            foreach ($m['delete'] as $path => $hash) if (file_exists(PatchPackage::target($this->root(), $path))) throw new RuntimeException('Remove obsolete file: ' . $path);
            $state['versions'][$m['component']] = $m['to']; $state['installed'][$m['id']] = hash_file('sha256', $archive);
            $state['history'][bin2hex(random_bytes(8))] = ['id' => $m['id'], 'component' => $m['component'], 'from' => $m['from'], 'to' => $m['to'], 'status' => 'installed', 'at' => gmdate('c'), 'message' => 'Manual folder replacement verified'];
            $this->save($state); return 'Legacy game replacement verified.';
        });
    }
    private function artisan(array $arguments): void
    {
        $process = new Process(array_merge([(string) config('patches.php_binary'), base_path('artisan')], $arguments), base_path());
        $process->setTimeout(180)->run();
        if (!$process->isSuccessful()) {
            $output = $process->getOutput() . "\n" . $process->getErrorOutput();
            foreach (array_merge($_SERVER, $_ENV) as $name => $value) {
                if (preg_match('/password|secret|token|key/i', (string) $name) && is_string($value) && strlen($value) >= 4) {
                    $output = str_replace($value, '[REDACTED]', $output);
                }
            }
            $output = preg_replace('#(https?://)[^\\s/@]+:[^\\s/@]+@#i', '$1[REDACTED]@', $output);
            $id = 'command-failure-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
            $diagnostic = ['command' => $arguments[0], 'exit_code' => $process->getExitCode(),
                'php_binary' => (string) config('patches.php_binary'), 'output' => substr($output, 0, 16000)];
            $saved = file_put_contents($this->directory() . '/' . $id, json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
            throw new RuntimeException('Updater command failed: ' . $arguments[0] . ' (exit ' . $process->getExitCode() . '). '
                . ($saved === false ? 'Could not save private diagnostics.' : 'Private diagnostics: storage/app/patch-manager/' . $id));
        }
    }
}
