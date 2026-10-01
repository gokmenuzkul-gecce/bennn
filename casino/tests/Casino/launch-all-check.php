<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Launch every viewable game for the four aggregator providers and report any
 * title the vendor refuses to open. Read-only: no wallet callbacks are fired.
 *
 * Usage: php tests/Casino/launch-all-check.php [provider ...]
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use VanguardLTE\Casino\CasinoGameLaunchService;
use VanguardLTE\Game;
use VanguardLTE\User;

$providers = array_slice($argv, 1) ?: ['pragmatic', 'pgsoft', 'amatic', 'amusnet'];
$service = app(CasinoGameLaunchService::class);
$user = User::orderBy('id')->first();

$totalOk = 0;
$totalFail = 0;
$failures = [];

foreach ($providers as $pk) {
    $games = Game::where('provider_key', $pk)->where('view', 1)->get(['id', 'name', 'provider_key', 'launch_code', 'provider_game_id']);
    $ok = 0;
    $fail = 0;
    $start = microtime(true);
    echo '[' . $pk . '] ' . count($games) . ' oyun' . PHP_EOL;

    foreach ($games as $g) {
        try {
            $r = $service->launch($g, $user, 'tr');
            $url = $r['url'] ?? ($r['form']['action'] ?? '');
            if ($url === '') {
                $fail++;
                $failures[] = "$pk/$g->name: bos URL";
            } else {
                $ok++;
            }
        } catch (\Throwable $e) {
            $fail++;
            $failures[] = "$pk/$g->name: " . substr($e->getMessage(), 0, 120);
        }
        if (($ok + $fail) % 100 === 0) {
            echo '  ...' . ($ok + $fail) . ' (ok=' . $ok . ' fail=' . $fail . ')' . PHP_EOL;
        }
    }

    printf('  => ok=%d fail=%d  (%.1fs)%s', $ok, $fail, microtime(true) - $start, PHP_EOL);
    $totalOk += $ok;
    $totalFail += $fail;
}

echo PHP_EOL . 'TOPLAM: ok=' . $totalOk . ' fail=' . $totalFail . PHP_EOL;
if ($failures) {
    echo 'ACILMAYAN OYUNLAR:' . PHP_EOL;
    foreach ($failures as $f) {
        echo '  - ' . $f . PHP_EOL;
    }
}
