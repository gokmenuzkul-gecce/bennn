<?php
declare(strict_types=1);

namespace VanguardLTE\Services {
    // Boundary doubles: no network, license credentials, real wallet or wagers.
    final class PromexCedarCatalogService {
        public static bool $entitled = true;
        public function catalog(): array { return ['games' => self::$entitled ? [['id'=>'Cedarcules','manifest'=>['engine'=>'slot']]] : []]; }
    }
    final class PromexCedarSettlementService {
        public static int $calls = 0;
        public function initialize(int $userId, string $game): array {
            ++self::$calls;
            return ['server_seed_hash'=>str_repeat('a',64),'client_seed'=>'fixture','nonce'=>1,
                'presentation'=>['rows'=>3,'reels'=>5,'lines'=>20,'wild'=>1,'symbols'=>['1'=>'wild'],
                    'published_rtp'=>92,'min_wager'=>'0.20','max_wager'=>'100.00']];
        }
    }
}
namespace {
    require __DIR__ . '/fixture-bootstrap.php';
    $previousConfig = $container->make('config');
    $container->instance('config', new \Illuminate\Config\Repository(array_merge($previousConfig->all(), [
        'cedar_slots' => require __DIR__.'/../../config/cedar_slots.php',
        'licensing'=>['cedar_public_origin'=>'https://clients.377.live'],
    ])));
    $sourceManifest = json_decode(file_get_contents(__DIR__.'/../../../CedarGames/Cedarcules/game.json'), true, 64, JSON_THROW_ON_ERROR);
    foreach (['assets','audio','layout','bet_steps','max_payout','theme_css'] as $key) {
        if (config('cedar_slots.Cedarcules.'.$key) != $sourceManifest[$key]) throw new \RuntimeException('Public integration metadata drift: '.$key);
    }
    // This harness has no Laravel basePath(): any local-manifest access fails.
    if (!\VanguardLTE\Services\CedarGameRegistry::isRegisteredSlot('Cedarcules')) throw new \RuntimeException('Remote slot not dispatched');
    $service = new \VanguardLTE\Services\CedarSlotService();
    $result = $service->handle(\Illuminate\Http\Request::create('/', 'POST', ['action'=>'init']), 'Cedarcules');
    if (($result['status'] ?? '') !== 'success' || count($result['assets']['symbols'] ?? []) !== 10) throw new \RuntimeException('Remote init failed');
    if (!str_starts_with($result['assets']['symbols'][1], '/cedar/games/Cedarcules/')) throw new \RuntimeException('Wrong hosted asset URL');
    \VanguardLTE\Services\PromexCedarCatalogService::$entitled = false;
    $denied = $service->handle(\Illuminate\Http\Request::create('/', 'POST', ['action'=>'init']), 'Cedarcules');
    if (($denied['status'] ?? '') !== 'error' || \VanguardLTE\Services\PromexCedarSettlementService::$calls !== 1) throw new \RuntimeException('Missing catalog entitlement was not blocked');
    echo "PASS: slot dispatch/init without local manifests; hosted artwork; entitlement denial. No network/wagers.\n";
}
