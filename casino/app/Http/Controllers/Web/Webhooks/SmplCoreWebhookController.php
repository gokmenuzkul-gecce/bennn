<?php

namespace VanguardLTE\Http\Controllers\Web\Webhooks;

use Illuminate\Http\Request;
use VanguardLTE\Casino\SmplCore\SmplCoreClient;
use VanguardLTE\Casino\SmplCore\SmplCoreWalletService;
use VanguardLTE\Http\Controllers\Controller;

/**
 * smpl core seamless-wallet webhook.
 *
 * smpl core posts every action (balance/bet/win/refund/rollback) to one URL
 * with the action in the form body. The response must always be HTTP 200 with
 * a JSON body: success carries {"balance", "transaction_id"}, failure carries
 * {"error_code", "error_description"}.
 */
class SmplCoreWebhookController extends Controller
{
    public function __construct(
        private readonly SmplCoreClient $client,
        private readonly SmplCoreWalletService $wallet
    ) {
    }

    public function handle(Request $request)
    {
        $payload = $request->all();

        $headers = [
            'x-merchant-id' => (string) $request->header('X-Merchant-Id', ''),
            'x-timestamp' => (string) $request->header('X-Timestamp', ''),
            'x-nonce' => (string) $request->header('X-Nonce', ''),
            'x-sign' => (string) $request->header('X-Sign', ''),
        ];

        if (!$this->client->verify($payload, $headers)) {
            return $this->respond([
                'error_code' => SmplCoreWalletService::INTERNAL_ERROR,
                'error_description' => 'Invalid signature',
            ]);
        }

        $action = (string) ($payload['action'] ?? '');
        if ($action === '') {
            return $this->respond([
                'error_code' => SmplCoreWalletService::INTERNAL_ERROR,
                'error_description' => 'Missing action',
            ]);
        }

        return $this->respond($this->wallet->handle($action, $payload));
    }

    private function respond(array $body)
    {
        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
