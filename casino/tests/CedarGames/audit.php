<?php

declare(strict_types=1);

$casino = dirname(__DIR__, 2);
$repo = dirname($casino);
require_once $casino . '/app/Services/MultiRowSlotEngine.php';

use VanguardLTE\Services\MultiRowSlotEngine;

$checks = [];
$record = static function (string $name, bool $pass, array $details = []) use (&$checks): void {
    $checks[] = ['name' => $name, 'status' => $pass ? 'PASS' : 'FAIL'] + $details;
};
$gameName = $argv[1] ?? 'CedarHercules';
if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,99}$/D', $gameName)) throw new InvalidArgumentException('Invalid Cedar game name.');
$gameRoot = $repo . '/CedarGames/' . $gameName;
$manifest = json_decode((string) file_get_contents($gameRoot . '/game.json'), true, 64, JSON_THROW_ON_ERROR);
$mathPath = $gameName === 'Cedarcules'
    ? $repo . '/localscripts/licensing_hub/private/cedar/math/Cedarcules.json'
    : $gameRoot . '/math.json';
$math = json_decode((string) file_get_contents($mathPath), true, 128, JSON_THROW_ON_ERROR);
$index = (string) file_get_contents($gameRoot . '/index.html');
$runtime = (string) file_get_contents($repo . '/CedarGames/_runtime/cedar-slot.js');

$record('isolated manifest contract', ($manifest['name'] ?? '') === $gameName
    && ($manifest['engine'] ?? '') === 'slot' && is_file($gameRoot . '/' . ($manifest['entry'] ?? '')));
$record('licensed runtime bootstrap', str_contains($index, '/js/promex-html-game.js')
    && str_contains($index, 'data-game="' . $gameName . '"') && str_contains($runtime, "game.request('spin'"));
$record('global loader and theme controls', str_contains($runtime, 'config.brand.loader_css')
    && str_contains($runtime, 'config.brand.theme_css'));
$publishedRtp = (float) $math['published_rtp'];
$record('published RTP band', $publishedRtp >= 75.0 && $publishedRtp <= 95.0, ['published_rtp_percent' => $publishedRtp]);
$expected = ['classic-3x3' => [3,3], 'standard-5x3' => [5,3], 'tall-5x4' => [5,4], 'wide-6x4' => [6,4]];
$preset = $manifest['layout']['preset'] ?? null;
$dimensionsPass = !$preset || (isset($expected[$preset]) && count($math['reel_weights']) === $expected[$preset][0] && (int) $math['rows'] === $expected[$preset][1]);
$record('slot dimensions', $dimensionsPass && count($math['paylines']) > 0, ['preset' => $preset, 'columns' => count($math['reel_weights']), 'rows' => (int) $math['rows'], 'lines' => count($math['paylines'])]);

$state = 0x5eed1234;
$rng = static function (int $min, int $max) use (&$state): int {
    $state = (int) ((1103515245 * $state + 12345) & 0x7fffffff);
    return $min + ($state % ($max - $min + 1));
};
$spins = 100000; $betPerLine = 0.01; $stake = $betPerLine * count($math['paylines']); $returned = 0.0; $hits = 0;
for ($i = 0; $i < $spins; $i++) {
    $grid = MultiRowSlotEngine::generateWeightedGrid($math['reel_weights'], (int) $math['rows'], $rng);
    $win = (float) MultiRowSlotEngine::evaluateLines($grid, (int) $math['rows'], $math['paylines'], $math['paytable'], (int) $math['wild'], $betPerLine)['TotalWin'];
    $returned += $win; if ($win > 0) $hits++;
}
$rtp = $returned / ($spins * $stake); $hitRate = $hits / $spins;
$record('deterministic 100k spin audit', $rtp >= 0.75 && $rtp <= 0.95 && $hitRate >= 0.10 && $hitRate <= 0.50, [
    'spins' => $spins, 'base_rtp_percent' => round($rtp * 100, 3), 'published_rtp_percent' => $publishedRtp,
    'target_delta_points' => round($rtp * 100 - $publishedRtp, 3), 'hit_rate_percent' => round($hitRate * 100, 3),
]);

$failed = array_filter($checks, static fn (array $check): bool => $check['status'] !== 'PASS');
echo json_encode(['suite' => 'cedar-remake-audit', 'game' => $gameName, 'status' => $failed ? 'FAIL' : 'PASS', 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed ? 1 : 0);
