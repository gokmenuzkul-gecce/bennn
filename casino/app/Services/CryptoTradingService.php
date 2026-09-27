<?php

namespace VanguardLTE\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use VanguardLTE\CryptoAsset;
use VanguardLTE\CryptoPosition;
use VanguardLTE\CryptoPriceSnapshot;
use VanguardLTE\CryptoRound;
use VanguardLTE\User;

/**
 * Server-authoritative virtual crypto positions.
 *
 * Browsers only read locally persisted prices.  The single licensed provider
 * request is made by the scheduler/admin refresh, and the exact responses used
 * to settle a round are retained as immutable snapshots.
 */
class CryptoTradingService
{
    public const INTERVALS = ['hourly', 'daily', 'weekly'];
    private const SNAPSHOT_GRACE_SECONDS = 120;
    private const MAX_LEVERAGE = 3.0;
    private const MAX_PROFIT_PERCENT = 100.0;

    public function refreshCatalog(): array
    {
        // The legacy application can run without Laravel's cache_locks table.
        // A MySQL advisory lock still serializes the one provider/cache refresh.
        $lockName = 'crypto-trading:catalog-refresh';
        try {
            $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
            if ((int) ($lock->acquired ?? 0) !== 1) return ['success' => false, 'message' => 'The crypto market cache is already being refreshed.'];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'The crypto market refresh lock is unavailable.'];
        }

        try {
            $feed = app(CryptoPriceService::class)->markets();
            if (($feed['success'] ?? false) !== true) return $feed;
            if (($feed['stale'] ?? false) === true) return ['success' => false, 'message' => 'The licensed crypto cache is stale; no round price will be captured from it.'];

            $firstSync = CryptoAsset::count() === 0;
            $now = now('UTC');
            foreach ((array) ($feed['coins'] ?? []) as $coin) {
                if (!is_array($coin) || !is_string($coin['id'] ?? null) || !is_numeric($coin['price_usd'] ?? null)) continue;
                $rank = isset($coin['rank']) ? (int) $coin['rank'] : null;
                $values = [
                    'symbol' => strtoupper((string) ($coin['symbol'] ?? '')),
                    'name' => (string) ($coin['name'] ?? $coin['id']),
                    'market_rank' => $rank,
                    'price_usd' => round((float) $coin['price_usd'], 10),
                    'change_24h' => isset($coin['change_24h']) && is_numeric($coin['change_24h']) ? (float) $coin['change_24h'] : null,
                    'provider_updated_at' => $this->providerTime($coin['updated_at'] ?? null),
                    'updated_at' => $now,
                ];
                $asset = CryptoAsset::where('provider_id', $coin['id'])->first();
                if (!$asset) {
                    $values['provider_id'] = $coin['id'];
                    $values['is_enabled'] = $firstSync && $rank !== null && $rank <= 20;
                    $values['created_at'] = $now;
                    CryptoAsset::insert($values);
                } else {
                    $asset->fill($values)->save();
                }
            }
            return ['success' => true, 'count' => CryptoAsset::count(), 'enabled' => CryptoAsset::where('is_enabled', true)->count(), 'feed' => $feed];
        } finally {
            try { DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]); } catch (\Throwable) { /* Connection cleanup releases advisory locks. */ }
        }
    }

    /** Runs each minute. One cached Hub request supplies all enabled assets. */
    public function processDueRounds(): array
    {
        $refresh = $this->refreshCatalog();
        $now = now('UTC');
        $opened = $settled = $voided = 0;

        $scheduled = CryptoRound::with('asset')->where('status', 'scheduled')->where('starts_at', '<=', $now)->orderBy('starts_at')->get();
        foreach ($scheduled as $round) {
            if (($refresh['success'] ?? false) === true && $this->capture($round, 'open', $now)) $opened++;
            elseif ($round->starts_at->copy()->addSeconds(self::SNAPSHOT_GRACE_SECONDS)->lte($now)) { $this->voidRound($round, 'Opening price was unavailable.'); $voided++; }
        }

        $openRounds = CryptoRound::with('asset')->where('status', 'open')->where('ends_at', '<=', $now)->orderBy('ends_at')->get();
        foreach ($openRounds as $round) {
            if (($refresh['success'] ?? false) === true && $this->capture($round, 'close', $now)) { $this->settleRound($round); $settled++; }
            elseif ($round->ends_at->copy()->addSeconds(self::SNAPSHOT_GRACE_SECONDS)->lte($now)) { $this->voidRound($round, 'Closing price was unavailable.'); $voided++; }
        }

        return ['refresh' => $refresh, 'opened' => $opened, 'settled' => $settled, 'voided' => $voided];
    }

    public function queuePosition(User $user, int $assetId, string $requestedInterval, string $direction, float $stake, float $leverage): CryptoPosition
    {
        if (!in_array($requestedInterval, array_merge(self::INTERVALS, ['manual']), true)) throw new \RuntimeException('Choose Hourly, Daily, Weekly, or Manual queue.');
        if (!in_array($direction, ['long', 'short'], true)) throw new \RuntimeException('Choose a long or short position.');
        if ($stake < 1 || $stake > 1000000) throw new \RuntimeException('Position cost must be between 1 and 1,000,000 Cedar Coins.');
        if (!in_array($leverage, [1.0, 2.0, 3.0], true)) throw new \RuntimeException('Choose 1×, 2×, or 3× leverage.');

        return DB::transaction(function () use ($user, $assetId, $requestedInterval, $direction, $stake, $leverage) {
            $asset = CryptoAsset::whereKey($assetId)->where('is_enabled', true)->lockForUpdate()->first();
            if (!$asset) throw new \RuntimeException('This currency is not available for new positions.');

            // Manual means an operator-independent queued order in the next hourly batch.
            $roundInterval = $requestedInterval === 'manual' ? 'hourly' : $requestedInterval;
            $round = $this->roundFor($asset, $roundInterval, now('UTC'));
            $dbUser = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            if (!$dbUser || (float) $dbUser->balance < $stake) throw new \RuntimeException('Insufficient Cedar Coins balance.');

            $before = (float) $dbUser->balance;
            $after = round($before - $stake, 2);
            DB::table('users')->where('id', $user->id)->update(['balance' => $after, 'updated_at' => now()]);
            $position = CryptoPosition::create([
                'user_id' => $user->id, 'crypto_asset_id' => $asset->id, 'crypto_round_id' => $round->id,
                'requested_interval' => $requestedInterval, 'direction' => $direction, 'leverage' => $leverage,
                'stake' => $stake, 'status' => 'queued',
            ]);
            $this->transaction($user->id, 'deduct', $stake, $before, $after, 'crypto_position_stake', "Crypto {$direction} {$asset->symbol} {$round->round_code} (Position #{$position->id})");
            $user->balance = $after;
            return $position->load(['asset', 'round']);
        });
    }

    public function nextBatchStart(string $interval, ?Carbon $from = null): Carbon
    {
        $from = ($from ?: now('UTC'))->copy()->utc();
        if ($interval === 'manual' || $interval === 'hourly') return $from->copy()->startOfHour()->addHour();
        if ($interval === 'daily') return $from->copy()->startOfDay()->addDay();
        return $from->copy()->startOfWeek(Carbon::MONDAY)->addWeek();
    }

    private function roundFor(CryptoAsset $asset, string $interval, Carbon $from): CryptoRound
    {
        $start = $this->nextBatchStart($interval, $from);
        $end = match ($interval) {
            'hourly' => $start->copy()->addHour(),
            'daily' => $start->copy()->addDay(),
            'weekly' => $start->copy()->addWeek(),
        };
        $code = strtoupper($asset->symbol) . '-' . strtoupper($interval) . '-' . $start->format('YmdHi') . 'Z';
        return CryptoRound::firstOrCreate(
            ['crypto_asset_id' => $asset->id, 'interval' => $interval, 'starts_at' => $start],
            ['round_code' => $code, 'ends_at' => $end, 'status' => 'scheduled']
        );
    }

    private function capture(CryptoRound $round, string $checkpoint, Carbon $now): bool
    {
        return DB::transaction(function () use ($round, $checkpoint, $now) {
            $locked = CryptoRound::with('asset')->whereKey($round->id)->lockForUpdate()->first();
            if (!$locked || ($checkpoint === 'open' && $locked->status !== 'scheduled') || ($checkpoint === 'close' && $locked->status !== 'open')) return false;
            $asset = $locked->asset;
            if (!$asset || !is_numeric($asset->price_usd) || (float) $asset->price_usd <= 0) return false;
            $coin = ['id' => $asset->provider_id, 'symbol' => $asset->symbol, 'price_usd' => (float) $asset->price_usd, 'rank' => $asset->market_rank, 'updated_at' => optional($asset->provider_updated_at)->toIso8601String()];
            $payload = json_encode($coin, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            CryptoPriceSnapshot::firstOrCreate(
                ['crypto_round_id' => $locked->id, 'checkpoint' => $checkpoint],
                ['crypto_asset_id' => $asset->id, 'price_usd' => $asset->price_usd, 'observed_at' => $now, 'provider_updated_at' => $asset->provider_updated_at, 'payload_hash' => hash('sha256', $payload), 'provider_payload' => $payload]
            );
            if ($checkpoint === 'open') {
                $locked->update(['open_price_usd' => $asset->price_usd, 'status' => 'open', 'opened_at' => $now]);
                CryptoPosition::where('crypto_round_id', $locked->id)->where('status', 'queued')->update(['entry_price_usd' => $asset->price_usd, 'status' => 'open', 'updated_at' => $now]);
            } else {
                $locked->update(['close_price_usd' => $asset->price_usd]);
            }
            return true;
        });
    }

    private function settleRound(CryptoRound $round): void
    {
        DB::transaction(function () use ($round) {
            $round = CryptoRound::whereKey($round->id)->lockForUpdate()->firstOrFail();
            if ($round->status !== 'open' || !$round->open_price_usd || !$round->close_price_usd) return;
            $now = now();
            foreach (CryptoPosition::where('crypto_round_id', $round->id)->whereIn('status', ['queued', 'open'])->lockForUpdate()->get() as $position) {
                $raw = $position->direction === 'long'
                    ? (((float) $round->close_price_usd / (float) $round->open_price_usd) - 1)
                    : (((float) $round->open_price_usd / (float) $round->close_price_usd) - 1);
                $percent = max(-100.0, min(self::MAX_PROFIT_PERCENT, $raw * (float) $position->leverage * 100));
                $payout = round((float) $position->stake * max(0, 1 + ($percent / 100)), 2);
                $status = $payout > (float) $position->stake ? 'won' : ($payout > 0 ? 'lost_partial' : 'lost');
                $position->update(['entry_price_usd' => $round->open_price_usd, 'exit_price_usd' => $round->close_price_usd, 'return_percent' => $percent, 'payout_amount' => $payout, 'status' => $status, 'settled_at' => $now]);
                if ($payout > 0) $this->credit($position->user_id, $payout, 'crypto_position_settle', "Crypto {$round->round_code} position #{$position->id}");
            }
            $round->update(['status' => 'settled', 'settled_at' => $now]);
        });
    }

    private function voidRound(CryptoRound $round, string $reason): void
    {
        DB::transaction(function () use ($round, $reason) {
            $round = CryptoRound::whereKey($round->id)->lockForUpdate()->firstOrFail();
            if (in_array($round->status, ['settled', 'void'], true)) return;
            foreach (CryptoPosition::where('crypto_round_id', $round->id)->whereIn('status', ['queued', 'open'])->lockForUpdate()->get() as $position) {
                $stake = (float) $position->stake;
                $position->update(['payout_amount' => $stake, 'status' => 'refunded', 'settled_at' => now()]);
                $this->credit($position->user_id, $stake, 'crypto_position_refund', "{$reason} Position #{$position->id}");
            }
            $round->update(['status' => 'void', 'settled_at' => now()]);
        });
    }

    private function credit(int $userId, float $amount, string $source, string $note): void
    {
        $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
        if (!$user) return;
        $before = (float) $user->balance; $after = round($before + $amount, 2);
        DB::table('users')->where('id', $userId)->update(['balance' => $after, 'count_balance' => (float) $user->count_balance + $amount, 'updated_at' => now()]);
        $this->transaction($userId, 'add', $amount, $before, $after, $source, $note);
    }

    private function transaction(int $userId, string $direction, float $amount, float $before, float $after, string $source, string $note): void
    {
        DB::table('transactions')->insert(['user_id' => $userId, 'admin_id' => null, 'direction' => $direction, 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after, 'source' => $source, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function providerTime(mixed $value): ?Carbon
    {
        try { return $value ? Carbon::parse($value)->utc() : null; } catch (\Throwable) { return null; }
    }
}
