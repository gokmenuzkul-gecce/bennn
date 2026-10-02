<?php

namespace VanguardLTE\Console\Commands;

use Illuminate\Console\Command;
use VanguardLTE\Casino\CasinoCatalogSyncService;

/**
 * Sync the local lobby with the live aggregator catalogue.
 *
 * Links the legacy placeholder rows to their real vendor games (provider key,
 * numeric launch id, cover art) and optionally imports titles that exist only
 * on the vendor side.
 */
class CasinoSyncCatalog extends Command
{
    protected $signature = 'casino:sync-catalog
        {--provider= : Only sync one provider key (pragmatic, pgsoft, amatic, amusnet)}
        {--link-only : Match existing rows without importing new games}
        {--prune : Hide rows the vendor no longer lists (they fail to launch)}
        {--hide-unlinked : Hide lobby rows no aggregator provider owns (license-gated legacy titles)}
        {--shop=1 : Shop id the games belong to}';

    protected $description = 'Aggregator oyun kataloğunu yerel lobiye senkronize et';

    public function handle(CasinoCatalogSyncService $service): int
    {
        $shopId = (int) $this->option('shop');
        $createMissing = !$this->option('link-only');
        $prune = (bool) $this->option('prune');
        $provider = (string) $this->option('provider');

        try {
            $report = $provider !== ''
                ? [$provider => $service->sync($provider, $createMissing, $shopId, $prune)]
                : $service->syncAll($createMissing, $shopId, $prune);

            if ($this->option('hide-unlinked')) {
                $this->line('gizlenen (bağlantısız) = ' . $service->hideUnlinked($shopId));
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($report as $row) {
            $this->line(sprintf(
                '%-10s  getirilen=%-5d  eşleşen=%-5d  eklenen=%-5d  atlanan=%-5d  gizlenen=%-4d',
                $row['provider'],
                $row['fetched'],
                $row['linked'],
                $row['created'],
                $row['skipped'],
                $row['pruned'] ?? 0
            ));
        }

        $this->info('Katalog senkronizasyonu tamamlandı.');

        return self::SUCCESS;
    }
}
