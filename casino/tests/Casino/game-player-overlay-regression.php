<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Guards the in-page game player overlay against a CSS containing-block
 * regression.
 *
 * <main> carries the `.motion-intro` entrance animation and the player overlay
 * (`#game-player`) is a `position: fixed` descendant of it. An animation with a
 * forwards-filling mode (`both`/`forwards`) keeps the final keyframe applied
 * after it ends; because `motion-fade-up` animates `transform`, the retained
 * `transform: none` still computes to an identity matrix and turns <main> into
 * the containing block for the fixed overlay. The player then positions itself
 * relative to the tall page column instead of the viewport and lands far below
 * the fold, which on mobile looks like the game never opens.
 *
 * The intro must therefore fill `backwards` (or not fill at all) so no
 * transform survives once the animation completes. Both the source stylesheet
 * and the compiled bundle served to browsers are checked.
 *
 * Usage: php tests/Casino/game-player-overlay-regression.php
 */

$root = dirname(__DIR__, 2);
$repoRoot = dirname($root);

$read = static function (string $path) use ($root, $repoRoot): string {
    $candidates = [$root . '/' . $path, $repoRoot . '/' . $path];
    foreach ($candidates as $candidate) {
        $value = @file_get_contents($candidate);
        if (is_string($value)) {
            return $value;
        }
    }
    throw new RuntimeException('Unable to read ' . $path);
};

$sourceCss = $read('resources/css/tailwind.css');
$compiledCss = $read('minimal/css/tailwind.css');
$blade = $read('resources/views/frontend/Minimal/games/list.blade.php');
$layout = $read('resources/views/frontend/Minimal/layouts/clean.blade.php');

// Pull the exact animation shorthand for `.motion-intro` out of a stylesheet.
// Minified bundles omit the trailing semicolon, so the value terminator is
// `;` or the closing brace.
$introAnimation = static function (string $css): string {
    if (!preg_match('/\.motion-intro\s*\{[^}]*?animation:\s*([^;}]+)/s', $css, $m)) {
        return '';
    }
    return trim($m[1]);
};

$sourceIntro = $introAnimation($sourceCss);
$compiledIntro = $introAnimation($compiledCss);

$checks = [
    'main still carries the motion-intro entrance class' => str_contains($layout, 'class="motion-intro')
        && preg_match('/<main[^>]*motion-intro/', $layout) === 1,

    'in-page player overlay is a fixed descendant of the animated main' => str_contains($blade, 'id="game-player"')
        && preg_match('/id="game-player"[^>]*class="[^"]*\bfixed\b/', $blade) === 1,

    'source motion-intro animation is found' => $sourceIntro !== '',

    'source motion-intro does not retain a transform after it ends' => $sourceIntro !== ''
        && !preg_match('/\b(both|forwards)\b/', $sourceIntro)
        && preg_match('/\bbackwards\b/', $sourceIntro) === 1,

    'compiled motion-intro animation is found' => $compiledIntro !== '',

    'compiled motion-intro does not retain a transform after it ends' => $compiledIntro !== ''
        && !preg_match('/\b(both|forwards)\b/', $compiledIntro)
        && preg_match('/\bbackwards\b/', $compiledIntro) === 1,

    'motion-fade-up still animates transform (the reason the fill matters)' => str_contains($sourceCss, '@keyframes motion-fade-up')
        && str_contains($sourceCss, 'transform: translateY(16px)')
        && str_contains($sourceCss, 'transform: none'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' game player overlay checks' . PHP_EOL;
