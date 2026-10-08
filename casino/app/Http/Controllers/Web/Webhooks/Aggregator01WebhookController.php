<?php

namespace VanguardLTE\Http\Controllers\Web\Webhooks;

use Illuminate\Http\Request;
use VanguardLTE\Casino\Aggregator01\Aggregator01Client;
use VanguardLTE\Casino\Aggregator01\Aggregator01WalletService;
use VanguardLTE\Http\Controllers\Controller;

/**
 * 01.tech Aggregator (A8R) seamless-wallet webhook.
 *
 * The Aggregator POSTs each command to its own method path as JSON, signed with
 * X-REQUEST-SIGN: hex HMAC-SHA256 over the exact raw body, keyed by AUTH_TOKEN.
 * The routes mirror the Aggregator's own paths so the registered callback URL
 * works regardless of whether it calls the service path or the flattened one:
 *
 *   POST /webhooks/aggregator01/callbacks/Player/Balance   -> balance
 *   POST /webhooks/aggregator01/callbacks/Round/BetWin     -> bet/win
 *   POST /webhooks/aggregator01/callbacks/Round/Rollback   -> rollback
 *   POST /webhooks/aggregator01/callbacks/Round/Finish     -> finish
 *
 * There is also a single flattened route (POST /webhooks/aggregator01/callbacks)
 * that reads a "type" field from the body, for accounts configured with one URL.
 *
 * Success answers HTTP 200 with the operation body. A rejection (bad signature,
 * unknown player, insufficient funds, missing original) answers the Twirp error
 * body with HTTP 400 (invalid_argument) / 500 (internal), so the Aggregator
 * retries and refuses to continue the spin.
 */
class Aggregator01WebhookController extends Controller
{
    public function __construct(
        private readonly Aggregator01Client $client,
        private readonly Aggregator01WalletService $wallet
    ) {
    }

    /** Player/Balance. */
    public function balance(Request $request)
    {
        return $this->run($request, 'Balance');
    }

    /** Round/BetWin. */
    public function betWin(Request $request)
    {
        return $this->run($request, 'BetWin');
    }

    /** Round/Rollback. */
    public function rollback(Request $request)
    {
        return $this->run($request, 'Rollback');
    }

    /** Round/Finish. */
    public function finish(Request $request)
    {
        return $this->run($request, 'Finish');
    }

    /** Freespins/Finish. */
    public function freespinsFinish(Request $request)
    {
        return $this->run($request, 'FreespinsFinish');
    }

    /**
     * Flattened entry point: the operation travels in the body ("type").
     *
     * Accepts "Balance" / "BetWin" / "Rollback" / "Finish" (case-insensitive) and
     * also the Aggregator's own dotted forms ("Player/Balance", "Round/BetWin").
     */
    public function handle(Request $request)
    {
        [$payload, $error] = $this->payload($request);
        if ($error) {
            return $error;
        }

        $operation = $this->operationFromBody($payload);

        return $this->run($request, $operation, $payload, true);
    }

    /** Verify the signature and decode the JSON body. */
    private function run(Request $request, string $operation, ?array $preDecoded = null, bool $alreadyVerified = false)
    {
        if (!$alreadyVerified) {
            [$payload, $error] = $this->payload($request);
            if ($error) {
                return $error;
            }
        } else {
            $payload = $preDecoded ?? [];
        }

        $result = $this->wallet->handle($operation, $payload, $this->client);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Verify the signature and decode the JSON body.
     *
     * @return array{0: array<string, mixed>, 1: \Illuminate\Http\JsonResponse|null}
     */
    private function payload(Request $request): array
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-REQUEST-SIGN', '');

        if (!$this->client->verifyWebhook($raw, $signature)) {
            return [[], response()->json([
                'code' => 'invalid_argument',
                'msg' => 'Forbidden.',
                'meta' => [
                    'api_code' => '403',
                    'api_message' => 'Forbidden.',
                ],
            ], 400, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $request->all();
        }

        return [$payload, null];
    }

    /**
     * Map a body "type"/"cmd" value onto a wallet operation.
     *
     * @param  array<string, mixed>  $payload
     */
    private function operationFromBody(array $payload): string
    {
        $raw = strtolower(trim((string) ($payload['type'] ?? $payload['cmd'] ?? $payload['operation'] ?? '')));

        return match (true) {
            str_contains($raw, 'balance') => 'Balance',
            str_contains($raw, 'freespin') => 'FreespinsFinish',
            str_contains($raw, 'betwin') || str_contains($raw, 'bet') || str_contains($raw, 'win') => 'BetWin',
            str_contains($raw, 'rollback') => 'Rollback',
            str_contains($raw, 'finish') => 'Finish',
            default => 'Unknown',
        };
    }
}
