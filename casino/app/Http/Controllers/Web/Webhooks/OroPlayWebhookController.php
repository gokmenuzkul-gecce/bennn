<?php

namespace VanguardLTE\Http\Controllers\Web\Webhooks;

use Illuminate\Http\Request;
use VanguardLTE\Casino\OroPlay\OroPlayClient;
use VanguardLTE\Casino\OroPlay\OroPlayWalletService;
use VanguardLTE\Http\Controllers\Controller;

/**
 * OroPlay seamless-wallet callbacks.
 *
 * OroPlay posts JSON to three operator endpoints, all authenticated with HTTP
 * Basic (base64 of clientId:clientSecret) and all answered with a JSON body
 * shaped {"success", "message", "errorCode"}. HTTP 200 is the only status the
 * vendor treats as an acknowledgement; failures travel in "errorCode".
 *
 *   POST /webhooks/oroplay/api/balance
 *   POST /webhooks/oroplay/api/transaction
 *   POST /webhooks/oroplay/api/batch-transactions
 */
class OroPlayWebhookController extends Controller
{
    public function __construct(
        private readonly OroPlayClient $client,
        private readonly OroPlayWalletService $wallet
    ) {
    }

    public function balance(Request $request)
    {
        return $this->guard($request, fn (array $payload) => $this->wallet->balance($payload));
    }

    public function transaction(Request $request)
    {
        return $this->guard($request, fn (array $payload) => $this->wallet->transaction($payload));
    }

    public function batchTransactions(Request $request)
    {
        return $this->guard($request, fn (array $payload) => $this->wallet->batchTransactions($payload));
    }

    /**
     * Verify the Basic credential, then hand the JSON body to the wallet.
     *
     * @param  callable(array<string, mixed>): array{success: bool, message: float|string, errorCode: int}  $handler
     */
    private function guard(Request $request, callable $handler)
    {
        if (!$this->client->verifyBasic((string) $request->header('Authorization', ''))) {
            return $this->respond([
                'success' => false,
                'message' => 'Unauthorized',
                'errorCode' => OroPlayWalletService::UNAUTHORIZED,
            ]);
        }

        return $this->respond($handler($request->json()->all()));
    }

    private function respond(array $body)
    {
        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
