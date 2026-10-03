<?php

declare(strict_types=1);

/**
 * Guards the fix for PG Soft games rendering as a narrow vertical strip.
 *
 * The aggregator answers /userauth with the phone build regardless of caller,
 * so the session is portrait. The lobby must size the player iframe to the
 * vendor's aspect (9:16 for PG Soft) or half the panel stays blank.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$config = $read('config/casino_providers.php');
$contract = $read('app/Casino/Providers/Contracts/CasinoProvider.php');
$abstract = $read('app/Casino/Providers/AbstractCasinoProvider.php');
$registry = $read('app/Casino/Providers/CasinoProviderRegistry.php');
$launch = $read('app/Casino/CasinoGameLaunchService.php');
$controller = $read('app/Http/Controllers/Web/Frontend/GamesController.php');
$blade = $read('resources/views/frontend/Minimal/games/list.blade.php');
$external = $read('resources/views/frontend/games/external.blade.php');

$checks = [
    'pgsoft config declares a 9:16 embed aspect' => str_contains($config, "'embed_aspect' => env('PGSOFT_EMBED_ASPECT', '9:16')"),

    'provider contract exposes embedAspect' => str_contains($contract, 'public function embedAspect(): string;'),

    'abstract provider resolves the configured aspect with auto default' => str_contains($abstract, "public function embedAspect(): string")
        && str_contains($abstract, "\$this->config()['embed_aspect'] ?? 'auto'"),

    'registry exposes the aspect per provider key' => str_contains($registry, 'public function embedAspect(string $key): string')
        && str_contains($registry, "->make(\$key)->embedAspect()"),

    'launch payload carries the aspect to the frontend' => str_contains($launch, "'aspect' => \$provider->embedAspect(),"),

    'launch JSON exposes the aspect to the player' => str_contains($controller, "'aspect' => \$launch['aspect'] ?? 'auto',"),

    'player iframe has a wrapper for aspect fitting' => str_contains($blade, 'id="game-player-frame-wrap"')
        && str_contains($blade, 'id="game-player-stage"'),

    'player fits the frame to the aspect and reacts to resize' => str_contains($blade, 'function applyAspect(aspect)')
        && str_contains($blade, 'function fitFrame()')
        && str_contains($blade, 'new ResizeObserver(fitFrame)')
        && str_contains($blade, "frame.style.width = Math.floor(w * scale) + 'px'"),

    'opening and closing the player apply and reset the aspect' => str_contains($blade, 'applyAspect(data.aspect);')
        && str_contains($blade, "applyAspect('auto');"),

    'standalone game view fits fixed-orientation builds too' => str_contains($external, 'id="game-stage"')
        && str_contains($external, 'function fit()')
        && str_contains($external, "window.innerWidth / w"),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' casino embed aspect checks' . PHP_EOL;
