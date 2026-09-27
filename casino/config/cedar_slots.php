<?php

// Public integration metadata only. No outcomes, weights, seeds or private assets.
$base = '/CedarGames/Cedarcules/';
$symbols = ['wild', 'hero', 'lion', 'temple', 'thunder-club', 'silver-coin', 'laurel', 'amphora', 'golden-apple', 'shield'];
$symbolPaths = [];
foreach ($symbols as $i => $name) $symbolPaths[$i + 1] = $base . 'assets/symbols/' . $name . '.png';
$audio = [];
foreach (['reel_start','reel_loop','reel_stop_1','reel_stop_2','reel_stop_3','result_no_win','win_small','win_medium','win_large','wild_land','button_click','fast_toggle','signature_cue'] as $event) {
    $audio[$event] = $base . 'audio/' . str_replace('_', '-', $event) . '.ogg';
}
$audio['ambient_loop'] = $base . 'audio/ambient-talisman-v1.ogg';
return ['Cedarcules' => [
    'name' => 'Cedarcules', 'title' => 'Cedarcules', 'engine' => 'slot',
    'theme_css' => $base . 'theme.css', 'layout' => ['preset' => 'standard-5x3'],
    'assets' => ['background' => $base . 'assets/background-desktop.jpg',
        'mobile_background' => $base . 'assets/background-mobile.jpg',
        'frame' => $base . 'assets/frame.png', 'symbols' => $symbolPaths],
    'audio' => ['mode' => 'files', 'files' => $audio, 'win_tiers' => ['medium_multiplier' => 5, 'large_multiplier' => 20]],
    'bet_steps' => [0.2, 0.5, 1, 2, 5, 10, 25, 50, 100], 'max_payout' => 1000000,
]];
