<?php

namespace VanguardLTE\Casino\Aggregator01;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use VanguardLTE\Casino\Models\CasinoFreespin;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\User;

/**
 * Back-office side of 01.tech free spins.
 *
 * Issues a campaign to the Aggregator (casino_a8r.Freespins/Issue) and records
 * it locally, so the later a8r_casino.Freespins/Finish callback can resolve the
 * player from the issue id and credit the winnings. Also cancels campaigns.
 *
 * Only prerequisites the Aggregator documents are enforced here: quantity >= 1,
 * a money-formatted bet amount, and a validity window that is in the future and
 * at most one month long.
 */
class Aggregator01FreespinsService
{
    public const PROVIDER_KEY = 'aggregator01';

    public function __construct(private readonly ?Aggregator01Client $client = null)
    {
    }

    /**
     * Issue free spins to a user and record the campaign.
     *
     * @param  array<string, mixed>  $input  game_id, quantity, bet_amount, valid_until
     * @return array{success: bool, message: string, campaign: ?CasinoFreespin}
     */
    public function issue(User $user, array $input): array
    {
        $gameId = trim((string) ($input['game_id'] ?? ''));
        $quantity = (int) ($input['quantity'] ?? 0);
        $betAmount = trim((string) ($input['bet_amount'] ?? ''));
        $validUntil = trim((string) ($input['valid_until'] ?? ''));

        if ($gameId === '' || !str_contains($gameId, ':')) {
            return $this->fail('Oyun "saglayici:oyun" biciminde olmali.');
        }
        if ($quantity < 1) {
            return $this->fail('Spin adedi en az 1 olmali.');
        }
        if ($betAmount === '' || !preg_match('/^\d{1,18}(\.\d{1,12})?$/', $betAmount)) {
            return $this->fail('Bahis miktari gecersiz.');
        }

        $until = $this->parseValidUntil($validUntil);
        if (!$until) {
            return $this->fail('Gecerlilik tarihi gelecekte olmali.');
        }
        if ($until->greaterThan(Carbon::now()->addMonth())) {
            return $this->fail('Gecerlilik en fazla 1 ay olabilir.');
        }

        $provider = explode(':', $gameId, 2)[0];
        $issueId = (string) Str::uuid();

        $result = $this->client()->issueFreespins(
            $issueId,
            $quantity,
            $betAmount,
            [$gameId],
            $this->playerFields($user),
            $until->format('Y-m-d\TH:i:s\Z')
        );

        if (!$result['success']) {
            return $this->fail($result['message'] !== '' ? $result['message'] : 'Freespin olusturulamadi.');
        }

        $campaign = CasinoFreespin::create([
            'provider_key' => self::PROVIDER_KEY,
            'user_id' => $user->id,
            'issue_id' => $issueId,
            'game_id' => $gameId,
            'game_provider' => $provider,
            'quantity' => $quantity,
            'bet_amount' => $betAmount,
            'valid_until' => $until,
            'status' => 'issued',
        ]);

        return ['success' => true, 'message' => 'Freespin gonderildi.', 'campaign' => $campaign];
    }

    /**
     * Cancel a campaign with the Aggregator and mark it cancelled locally.
     *
     * @return array{success: bool, message: string, campaign: ?CasinoFreespin}
     */
    public function cancel(CasinoFreespin $campaign): array
    {
        if ($campaign->status === 'finished') {
            return $this->fail('Tamamlanmis kampanya iptal edilemez.');
        }

        $result = $this->client()->cancelFreespins((string) $campaign->issue_id, (string) $campaign->game_provider);
        if (!$result['success']) {
            return $this->fail($result['message'] !== '' ? $result['message'] : 'Freespin iptal edilemedi.');
        }

        $campaign->status = 'cancelled';
        $campaign->save();

        return ['success' => true, 'message' => 'Freespin iptal edildi.', 'campaign' => $campaign];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CasinoFreespin> */
    public function listForUser(int $userId)
    {
        return CasinoFreespin::query()
            ->where('provider_key', self::PROVIDER_KEY)
            ->where('user_id', $userId)
            ->latest('id')
            ->get();
    }

    /**
     * Player fields the Aggregator requires, filled from the local user.
     *
     * @return array<string, mixed>
     */
    private function playerFields(User $user): array
    {
        return [
            'id' => CasinoProviderPlayer::codeFor((int) $user->id, self::PROVIDER_KEY, 'u'),
            'currency' => 'TRY',
            'country' => 'TR',
            'firstname' => (string) ($user->first_name ?: $user->username),
            'lastname' => (string) ($user->last_name ?: 'Player'),
            'nickname' => (string) $user->username,
            'gender' => 'm',
            'email' => (string) ($user->email ?: 'player@casino.local'),
            'date_of_birth' => $this->isoDate($user->birthday ?? null) ?: '1990-01-01T00:00:00Z',
            'registered_at' => $this->isoDate($user->created_at ?? null) ?: gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function isoDate($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value))->utc()->format('Y-m-d\TH:i:s\Z');
        }

        if (!$value) {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function parseValidUntil(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            $until = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $until->isFuture() ? $until : null;
    }

    /** @return array{success: bool, message: string, campaign: ?CasinoFreespin} */
    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message, 'campaign' => null];
    }

    private function client(): Aggregator01Client
    {
        return $this->client ?? new Aggregator01Client();
    }
}
