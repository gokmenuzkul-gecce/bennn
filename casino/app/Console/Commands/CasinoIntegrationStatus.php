<?php

namespace VanguardLTE\Console\Commands;

use Illuminate\Console\Command;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;

/**
 * Readiness report for the casino aggregator integration.
 *
 * Prints, per provider, the credentials and callback URL the vendor needs from
 * us and we need from them, plus a live reachability check. The callback URLs
 * are the exact strings to register in each vendor portal.
 */
class CasinoIntegrationStatus extends Command
{
    protected $signature = 'casino:integration-status {--test : Also run a live connectivity check per provider}';

    protected $description = 'Casino agregatör entegrasyon hazırlık raporu (kimlikler, callback URL, erişilebilirlik)';

    public function handle(CasinoProviderRegistry $registry): int
    {
        $base = rtrim((string) config('casino_providers.callback_base', ''), '/');
        $slug = (string) config('casino_providers.callback_slug', 'gregmorn');

        $this->newLine();
        $this->line('== Genel ==');
        $this->line('Callback tabanı (CASINO_CALLBACK_BASE): ' . ($base !== '' ? $base : '(boş!)'));
        $this->line('Aggregator slug (CASINO_CALLBACK_SLUG): ' . $slug);
        $this->line('Para birimi: ' . config('casino_providers.currency', 'TRY'));
        if (str_contains($base, 'prod-runtime.all-hands.dev')) {
            $this->warn('UYARI: callback tabanı geçici bir sandbox host. Satıcı portallarına kaydetmeden önce kalıcı bir alan adı kullan.');
        }

        $this->newLine();
        $this->line('== Sağlayıcılara KAYDETTİRİLECEK callback URL ==');
        $this->line('Aggregator (Pragmatic/PGSoft/Amatic/Amusnet): ' . $registry->callbackUrl());
        $this->line('Gregmorn Hub: ' . rtrim($base, '/') . '/' . ltrim((string) config('casino_providers.gregmorn.callback_path', '/webhooks/gregmorn/callbacks'), '/'));
        $this->line('OroPlay: ' . rtrim($base, '/') . '/' . ltrim((string) config('casino_providers.oroplay.callback_path', '/webhooks/oroplay/api'), '/'));
        $this->line('smpl core: ' . rtrim($base, '/') . '/' . ltrim((string) config('casino_providers.smplcore.callback_path', '/webhooks/smplcore/callbacks'), '/'));
        $this->line('(Gregmorn için alternatif: ' . rtrim($base, '/') . '/webhooks/aggregator/gregmorn/wallet)');

        $rows = [];
        foreach ($registry->keys() as $key) {
            $provider = $registry->make($key);
            $status = $provider->configStatus();
            $config = $provider->config();

            $rows[] = [
                'key' => $key,
                'label' => $provider->label(),
                'configured' => $status['configured'] ? 'hazır' : 'EKSİK',
                'enabled' => $registry->isEnabled($key) ? 'açık' : 'kapalı',
                'endpoint' => (string) ($config['endpoint'] ?? ''),
                'detail' => $status['configured'] ? '' : $status['message'],
            ];
        }

        $this->newLine();
        $this->line('== Sağlayıcı durumu ==');
        $this->table(['key', 'marka', 'durum', 'oyun', 'endpoint'], array_map(
            static fn (array $r): array => [$r['key'], $r['label'], $r['configured'], $r['enabled'], $r['endpoint']],
            $rows
        ));

        foreach ($rows as $row) {
            if ($row['detail'] !== '') {
                $this->line('  - ' . $row['key'] . ': ' . $row['detail']);
            }
        }

        if ($this->option('test')) {
            $this->newLine();
            $this->line('== Canlı erişilebilirlik ==');
            foreach ($rows as $row) {
                $result = $registry->make($row['key'])->testConnectivity();
                $this->line(sprintf(
                    '  %-10s %s  %s',
                    $row['key'],
                    $result['success'] ? 'OK  ' : 'HATA',
                    $result['message']
                ));
            }
        }

        $this->newLine();
        $this->line('== Bizden sağlayıcıya verilecekler ==');
        $this->line('  1) Yukarıdaki callback URL (kayıt için)');
        $this->line('  2) Para birimi: ' . config('casino_providers.currency', 'TRY'));
        $this->line('  3) Oyuncu kimliği formatı: <prefix><site user id> (kayıt sırasında üretilir, satıcı aynen geri gönderir)');

        $this->newLine();
        $this->line('== Sağlayıcıdan bize gerekenler ==');
        $this->line('  Aggregator: endpoint host + agentID + API token + secret (ve callback URL kaydı)');
        $this->line('  Gregmorn Hub: operator login + password + secret key + user id + IP allowlist');
        $this->line('  OroPlay: doğru base URL + clientId + clientSecret + agent kaydı');
        $this->line('  smpl core: base URL + merchant id + merchant key');

        return self::SUCCESS;
    }
}
