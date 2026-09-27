<?php

namespace VanguardLTE\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Services\LegacyCompatibilityService;

/** Optional, explicit adapter injection for operator-owned legacy HTML. */
final class InjectMockWebSocket extends Command
{
    protected $signature = 'games:legacy-adapter {game* : Registered Legacy Compatibility game names}';
    protected $description = 'Inject the Promex compatibility adapter into explicitly selected operator-supplied games.';

    public function handle(): int
    {
        $service = new LegacyCompatibilityService();
        if (!$service->enabled()) {
            $this->error('Legacy Compatibility is disabled.');
            return self::FAILURE;
        }
        $discovered = [];
        foreach ($service->discover() as $row) $discovered[$row['name']] = $row;
        $changed = 0;
        $skipped = 0;
        foreach (array_unique($this->argument('game')) as $name) {
            if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $name)) {
                $this->warn('Skipped invalid game name.'); ++$skipped; continue;
            }
            $game = DB::table('games')->where('name', $name)->first();
            if (!$game || ($game->source_type ?? '') !== LegacyCompatibilityService::SOURCE_TYPE
                || empty($game->legacy_rights_attested_at) || !isset($discovered[$name])) {
                $this->warn("Skipped {$name}: register it with rights attestation first."); ++$skipped; continue;
            }
            $path = base_path('../games/' . $name . '/' . $discovered[$name]['entry']);
            $content = file_get_contents($path);
            if (!is_string($content)) { $this->warn("Skipped {$name}: entry is unreadable."); ++$skipped; continue; }
            if (stripos($content, 'mock-websocket.js') !== false) {
                $this->line("Already adapted: {$name}"); ++$skipped; continue;
            }
            $tag = "\n\t<!-- Promex Legacy Compatibility Adapter -->\n\t<script src=\"/js/mock-websocket.js\"></script>\n";
            $adapted = preg_replace('/<head\b[^>]*>/i', '$0' . $tag, $content, 1, $count);
            if (!$count) $adapted = $tag . $content;
            $backup = $path . '.promex-original';
            if (!is_file($backup) && !copy($path, $backup)) {
                $this->warn("Skipped {$name}: original could not be preserved."); ++$skipped; continue;
            }
            $temporary = $path . '.promex-new';
            if (file_put_contents($temporary, $adapted, LOCK_EX) === false || !rename($temporary, $path)) {
                @unlink($temporary);
                $this->warn("Skipped {$name}: adapter write failed."); ++$skipped; continue;
            }
            $this->info("Adapted: {$name} (original preserved beside entry)");
            ++$changed;
        }
        $this->info("Legacy adapter complete: {$changed} changed, {$skipped} skipped.");
        return self::SUCCESS;
    }
}
