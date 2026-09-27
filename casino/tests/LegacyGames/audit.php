<?php

declare(strict_types=1);

$casino = dirname(__DIR__, 2);
$repo = dirname($casino);

require_once $casino . '/app/Services/MultiRowSlotEngine.php';
require_once $casino . '/app/Games/HerculesonofZeus/MathConfig.php';

use VanguardLTE\Games\HerculesonofZeus\MathConfig;
use VanguardLTE\Services\MultiRowSlotEngine;

function parseInit(string $path): array
{
    $result = [];
    foreach (require $path as $entry) {
        [$key, $value] = array_pad(explode('=', $entry, 2), 2, '');
        $result[$key] = $value;
    }
    return $result;
}

function record(array &$checks, string $name, bool $pass, array $details = []): void
{
    $checks[] = ['name' => $name, 'status' => $pass ? 'PASS' : 'FAIL'] + $details;
}

$checks = [];
$init = parseInit($casino . '/app/Games/HerculesonofZeus/init.php');
$paytable = array_map(static fn ($row) => array_map('floatval', explode(',', $row)), explode(';', $init['paytable']));
$paylines = array_map(static fn ($row) => array_map('intval', explode(',', $row)), explode(';', $init['payline']));

$state = 0x5eed1234;
$rng = static function (int $min, int $max) use (&$state): int {
    $state = (int) ((1103515245 * $state + 12345) & 0x7fffffff);
    return $min + ($state % ($max - $min + 1));
};

$spins = 100000;
$betPerLine = 0.01;
$stake = $betPerLine * count($paylines);
$returned = 0.0;
$hits = 0;
$maxWin = 0.0;
for ($spin = 0; $spin < $spins; $spin++) {
    $grid = MultiRowSlotEngine::generateWeightedGrid(MathConfig::reelWeights(), MathConfig::ROWS, $rng);
    $win = (float) MultiRowSlotEngine::evaluateLines($grid, MathConfig::ROWS, $paylines, $paytable, MathConfig::WILD, $betPerLine)['TotalWin'];
    $returned += $win;
    if ($win > 0) {
        $hits++;
        $maxWin = max($maxWin, $win);
    }
}

$baseRtp = $returned / ($spins * $stake);
$hitRate = $hits / $spins;
$spinCode = file_get_contents($casino . '/app/Games/HerculesonofZeus/PragmaticLib/Spin.php');
record($checks, 'Hercules fixed base math', $baseRtp >= 0.89 && $baseRtp <= 0.94 && $hitRate >= 0.18 && $hitRate <= 0.32, [
    'spins' => $spins,
    'base_rtp_percent' => round($baseRtp * 100, 3),
    'hit_rate_percent' => round($hitRate * 100, 3),
    'max_win_x_total_bet' => round($maxWin / $stake, 2),
]);
record($checks, 'Hercules has no adaptive historical-RTP gate', !str_contains($spinCode, 'new CheckRtp'));
record($checks, 'Hercules published RTP ceiling', (float) $init['rtp'] <= 95.0, ['published_rtp_percent' => (float) $init['rtp']]);

$missIndex = file_get_contents($repo . '/games/MissKittyAT/index.html');
record($checks, 'Miss Kitty calls its own server', str_contains($missIndex, '/game/MissKittyAT/server') && !str_contains($missIndex, '/game/DolphinsTreasureAT/server'));

$roulettePath = $repo . '/games/VirtualRouletteEGT/index.html';
$rouletteIndex = is_file($roulettePath) ? file_get_contents($roulettePath) : '';
record($checks, 'Virtual Roulette launch contract',
    str_contains($rouletteIndex, "gameName = 'VirtualRouletteEGT'")
    && str_contains($rouletteIndex, "gameIdentificationNumber: '550'")
    && str_contains($rouletteIndex, '/socket_config.json')
);

$failed = array_filter($checks, static fn ($check) => $check['status'] !== 'PASS');
echo json_encode([
    'suite' => 'legacy-three-game-audit',
    'status' => $failed ? 'FAIL' : 'PASS',
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($failed ? 1 : 0);
