<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Static regression checks for the modernised homepage (game lobby).
 *
 * Guards the new presentation layers (hero aurora, quick-access tiles, live
 * stat strip, scroll reveal) and the wiring they depend on, without touching
 * the DB. Also asserts the original lobby building blocks are still present so
 * a restyle cannot quietly drop the hero, wins ticker, provider marquee, grid
 * or in-site game player.
 *
 * Usage: php tests/Casino/homepage-modernization-regression.php
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$blade = $read('resources/views/frontend/Minimal/games/list.blade.php');
$css = $read('resources/views/frontend/Minimal/layouts/clean.blade.php');

$checks = [
    'hero keeps its slider, dots and in-site player' => str_contains($blade, 'id="hero-slider"')
        && str_contains($blade, 'hero-track')
        && str_contains($blade, 'hero-dot')
        && str_contains($blade, 'id="game-player"'),

    'hero gets a per-slide aurora layer' => str_contains($blade, 'hero-aurora')
        && substr_count($blade, 'class="hero-aurora"') === 3
        && str_contains($blade, '--ha1:rgba(16,185,129,.5)')
        && str_contains($css, '.hero-aurora')
        && str_contains($css, '@keyframes heroAurora'),

    'hero panel has an animated top light line and glow' => str_contains($css, '@keyframes heroTopLine')
        && str_contains($css, '@keyframes heroPanelGlow')
        && str_contains($css, 'hero-slider::before'),

    'quick access exposes six real destinations' => substr_count($blade, 'class="quick-tile"') === 6
        && str_contains($blade, "route('frontend.sports.index')")
        && str_contains($blade, "route('frontend.lotto.index')")
        && str_contains($blade, "route('frontend.predictions.index')")
        && str_contains($blade, "route('frontend.vip.index')")
        && str_contains($blade, "route('frontend.bonuses')")
        && str_contains($blade, "route('frontend.affiliates.index')")
        && str_contains($css, '.quick-tile'),

    'live stat strip counts real games and providers' => str_contains($blade, 'class="stat-strip"')
        && str_contains($blade, "number_format(count(\$games)")
        && str_contains($blade, '$providerList->count()')
        && str_contains($css, '.stat-card'),

    'stat strip carries no hardcoded payout or player figures' => !preg_match('/stat-value[^>]*>\s*[₺$]?\s?\d{2,}[.,]\d{3}/u', $blade),

    'sections reveal on scroll with a no-JS fallback' => str_contains($blade, 'class="space-y-5 reveal"')
        && str_contains($blade, 'class="space-y-4 reveal"')
        && str_contains($blade, 'IntersectionObserver')
        && str_contains($blade, '<noscript><style>.reveal')
        && str_contains($css, '.reveal.is-in'),

    'reveal motion collapses under reduced motion' => str_contains($css, 'prefers-reduced-motion: reduce')
        && str_contains($css, '.hero-aurora, .hero-slider, .hero-slider::before { animation: none !important; }'),

    'provider list is computed once and shared' => substr_count($blade, "\$providerList = \\VanguardLTE\\Category::where('parent', 0)") === 1
        && str_contains($blade, '$providerList as $i => $cat'),

    'wins ticker, provider marquee and games grid survive' => str_contains($blade, 'class="wins-bar"')
        && str_contains($blade, 'marquee-track')
        && str_contains($blade, 'provider-tile')
        && str_contains($blade, 'id="games-grid"')
        && str_contains($blade, 'id="home-game-search"'),

    'lobby section headers stay removed' => !str_contains($blade, 'Oyun Arenası')
        && !str_contains($blade, 'Oyun Sağlayıcıları')
        && !str_contains($blade, 'tek çatı altında'),

    'ambient background keeps its layered depth' => str_contains($css, '.bg-shots')
        && str_contains($css, '.bg-wash')
        && str_contains($css, '.bg-scrim')
        && str_contains($css, '.bg-bursts')
        && str_contains($css, '.bg-ticker')
        && str_contains($css, '<div class="casino-bg"')
        && str_contains($css, 'class="bg-wash"')
        && str_contains($css, 'class="bg-scrim"'),

    'background gains lattice, spotlight and suit watermarks' => str_contains($css, '.bg-pattern')
        && str_contains($css, 'repeating-linear-gradient(45deg')
        && str_contains($css, '.bg-spot')
        && str_contains($css, '.bg-suits')
        && str_contains($css, '@keyframes suitDrift')
        && str_contains($css, 'class="bg-pattern"')
        && str_contains($css, 'class="bg-spot"')
        && str_contains($css, 'class="bg-suits"')
        && substr_count($css, '&#98') === 7,

    'decorative background layers sit above the scrim' => strpos($css, 'class="bg-scrim"') < strpos($css, 'class="bg-pattern"')
        && strpos($css, 'class="bg-scrim"') < strpos($css, 'class="bg-suits"'),

    'casino theme adds felt, wheel, cards and table rail' => str_contains($css, '.bg-felt')
        && str_contains($css, '.bg-wheel')
        && str_contains($css, '@keyframes wheelSpin')
        && str_contains($css, '.bg-cards')
        && str_contains($css, '.bg-card')
        && str_contains($css, '.bg-rail')
        && str_contains($css, 'class="bg-felt"')
        && str_contains($css, 'class="bg-wheel"')
        && str_contains($css, 'class="bg-cards"')
        && str_contains($css, 'class="bg-rail"'),

    'roulette wheel is a masked rim that turns slowly' => str_contains($css, 'repeating-conic-gradient')
        && str_contains($css, 'wheelSpin 120s linear infinite')
        && str_contains($css, 'mask-image: radial-gradient(circle, transparent 56%'),

    'felt floor and wooden rail read as a real table' => str_contains($css, 'repeating-linear-gradient(0deg, rgba(255,255,255,0.016)')
        && str_contains($css, 'inset 0 0 120px 30px rgba(28, 12, 4, 0.60)')
        && str_contains($css, 'linear-gradient(180deg, rgba(120, 53, 15, 0.42)'),

    'a three-card hand rests on the felt' => substr_count($css, 'class="bg-card"') === 1
        && substr_count($css, 'class="bg-card is-red"') === 2
        && str_contains($css, '&#9824;')
        && str_contains($css, '&#9829;')
        && str_contains($css, '&#9830;'),

    'background art stays subtle over the felt' => str_contains($css, 'opacity: 0.22;')
        && str_contains($css, '.casino-bg.is-scrolled .bg-shots { opacity: 0.32; }')
        && str_contains($css, 'mix-blend-mode: screen;')
        && str_contains($css, '.bg-suits span:nth-child(3), .bg-suits span:nth-child(4) { display: none; }'),

    'background motion collapses under reduced motion' => str_contains($css, '.bg-shot, .bg-wash, .burst, .bg-ticker span, .bg-suits span, .bg-wheel, .bg-card { animation: none !important; }'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' homepage modernization checks' . PHP_EOL;
