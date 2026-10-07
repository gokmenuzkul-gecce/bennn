<?php

namespace VanguardLTE\Http\Controllers\Web\Webhooks;

use Illuminate\Http\Request;
use VanguardLTE\Casino\SoftAggregator\SoftAggregatorClient;
use VanguardLTE\Casino\SoftAggregator\SoftAggregatorWalletService;
use VanguardLTE\Http\Controllers\Controller;

/**
 * SoftAggregator seamless-wallet webhook.
 *
 * SoftAggregator GETs a single callback URL with the action in the query string:
 * action=balance|debit|credit. Every callback carries timestamp and key, where
 * key must equal md5(timestamp + salt_key) and the timestamp must be recent; a
 * bad or stale signature is rejected with error 2. Every answer is HTTP 200
 * with the outcome in the JSON body {error, balance}, the balance in cents.
 */
class SoftAggregatorWebhookController extends Controller
{
    public function __construct(
        private readonly SoftAggregatorClient $client,
        private readonly SoftAggregatorWalletService $wallet
    ) {
    }

    public function handle(Request $request)
    {
        $payload = $request->query();
        if ($payload === []) {
            // Tolerate a JSON/form POST body as well; the docs specify GET, but
            // some proxies re-encode the query.
            $payload = $request->all();
        }

        $timestamp = (string) ($payload['timestamp'] ?? '');
        $key = (string) ($payload['key'] ?? '');

        if (!$this->client->verify($timestamp, $key)) {
            return $this->respond([
                'error' => SoftAggregatorWalletService::PROCESSING_ERROR,
                'balance' => 0,
            ]);
        }

        $action = (string) ($payload['action'] ?? '');
        if ($action === '') {
            return $this->respond([
                'error' => SoftAggregatorWalletService::PROCESSING_ERROR,
                'balance' => 0,
            ]);
        }

        return $this->respond($this->wallet->handle($action, $payload, $this->client));
    }

    /** SoftAggregator requires HTTP 200 for both success and failure. */
    private function respond(array $body)
    {
        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
