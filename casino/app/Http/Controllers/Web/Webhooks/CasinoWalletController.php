<?php

namespace VanguardLTE\Http\Controllers\Web\Webhooks;

use Illuminate\Http\Request;
use VanguardLTE\Casino\Models\CasinoProviderPlayer;
use VanguardLTE\Casino\Providers\CasinoProviderRegistry;
use VanguardLTE\Casino\Providers\Contracts\CasinoProvider;
use VanguardLTE\Casino\Wallet\CasinoWalletService;
use VanguardLTE\Http\Controllers\Controller;

/**
 * Seamless-wallet callbacks for the casino aggregator.
 *
 * The vendor posts to /webhooks/aggregator/{slug}/wallet/{Operation}. All four
 * brands share one callback URL, so the provider is resolved from the player
 * mapping (the vendor round-trips our provider-specific user code) and the
 * request signature is verified against that provider's secret.
 */
class CasinoWalletController extends Controller
{
    private const OPERATIONS = [
        'GetBalance',
        'Withdraw',
        'Deposit',
        'BetWin',
        'RollbackTransaction',
    ];

    /**
     * Vendors address the wallet in one of two shapes: the operation in the URL
     * path, or every call to the base URL with the operation carried in the
     * body (action=balance/bet/win/refund/rollback). Map the body vocabulary
     * onto our operation names so a single registered endpoint stays correct.
     */
    private const OPERATION_ALIASES = [
        'getbalance' => 'GetBalance',
        'balance' => 'GetBalance',
        'withdraw' => 'Withdraw',
        'bet' => 'Withdraw',
        'deposit' => 'Deposit',
        'win' => 'Deposit',
        'refund' => 'Deposit',
        'betwin' => 'BetWin',
        'rollbacktransaction' => 'RollbackTransaction',
        'rollback' => 'RollbackTransaction',
    ];

    public function __construct(
        private readonly CasinoProviderRegistry $registry,
        private readonly CasinoWalletService $wallet
    ) {
    }

    public function handle(Request $request, string $slug, ?string $operation = null)
    {
        $operation = $this->resolveOperation($request, $operation);

        if ($operation === null) {
            return $this->respond(['code' => CasinoWalletService::GENERAL_ERROR, 'message' => 'Unknown operation']);
        }

        $payload = $request->all();
        $provider = $this->resolveProvider($payload, $operation);

        if (!$provider) {
            return $this->respond(['code' => CasinoWalletService::INVALID_SIGN, 'message' => 'Invalid Sign']);
        }

        if (!$provider->verify($payload, $provider->signOrder($operation))) {
            return $this->respond(['code' => CasinoWalletService::INVALID_SIGN, 'message' => 'Invalid Sign']);
        }

        $result = $this->wallet->handle($operation, $provider, $payload);

        return $this->respond($result);
    }

    /**
     * Determine the wallet operation from the path first, then the body/query.
     *
     * The path is authoritative when present (our /wallet/{operation} routes),
     * but the base /wallet route arrives with the operation in the payload.
     */
    private function resolveOperation(Request $request, ?string $operation): ?string
    {
        if (is_string($operation) && in_array($operation, self::OPERATIONS, true)) {
            return $operation;
        }

        foreach (['operation', 'action', 'command', 'method'] as $field) {
            $candidate = $request->input($field);
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }
            $alias = self::OPERATION_ALIASES[strtolower($candidate)] ?? null;
            if ($alias !== null) {
                return $alias;
            }
            foreach (self::OPERATIONS as $known) {
                if (strcasecmp($known, $candidate) === 0) {
                    return $known;
                }
            }
        }

        return null;
    }

    /**
     * Identify the provider behind a callback.
     *
     * Preferred path is the player mapping, because the user code we hand to the
     * vendor embeds its provider. If that fails we fall back to matching the
     * signature against each enabled provider's secret.
     */
    private function resolveProvider(array $payload, string $operation): ?CasinoProvider
    {
        $userCode = (string) ($payload['userID'] ?? $payload['userid'] ?? '');
        if ($userCode !== '') {
            $player = CasinoProviderPlayer::query()->where('user_code', $userCode)->first();
            if ($player && $this->registry->has($player->provider_key)) {
                return $this->registry->make($player->provider_key);
            }
        }

        foreach ($this->registry->keys() as $key) {
            $provider = $this->registry->make($key);
            if (!$provider->isConfigured()) {
                continue;
            }
            if ($provider->verify($payload, $provider->signOrder($operation))) {
                return $provider;
            }
        }

        return null;
    }

    private function respond(array $body)
    {
        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
