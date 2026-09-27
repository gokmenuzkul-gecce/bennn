<?php

namespace VanguardLTE\Services;

/** Compatibility facade. Full-package application has been retired. */
final class UpdaterService
{
    public static function getCurrentVersion(): string
    {
        $path = dirname(base_path()) . '/VERSION';
        $version = is_file($path) ? trim(file_get_contents($path)) : '0.0.0';
        return preg_match('/^\d+\.\d+\.\d+$/D', $version) ? $version : '0.0.0';
    }
    public static function applyUpdate(): array
    {
        return ['success' => false, 'message' => 'Full-package updates are retired. Use Backup & Update for signed incremental patches.'];
    }
}
