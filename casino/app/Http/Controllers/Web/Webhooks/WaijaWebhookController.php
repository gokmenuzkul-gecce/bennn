<?php

namespace VanguardLTE\Http\Controllers\Web\Webhooks;

use Illuminate\Http\Request;
use VanguardLTE\Casino\Waija\WaijaClient;
use VanguardLTE\Casino\Waija\WaijaWalletService;
use VanguardLTE\Http\Controllers\Controller;

/**
 * Waija (Slotsgateway) seamless-wallet webhook.
 *
 * Waija GETs a single callback URL with the action in the query string:
 * action=balance|debit|credit. Every callback carries timestamp and key, where
 * key must equal md5(timestamp + saltkey) and the timestamp must be recent; a
 * bad or stale signature is rejected with error 2.
 *
 * Unlike the Gregmorn Hub, Waija requires HTTP 200 on *every* answer — success
 * and failure alike travel in the JSON body {error, balance}. The balance is an
 * integer in cents.
 */
class WaijaWebhookController extends Controller
{
    public function __construct(
        private readonly WaijaClient $client,
        private readonly WaijaWalletService $wallet
    ) {
    }

    public function handle(Request $request)
    {
        $payload = $request->query();
        if ($payload === []) {
            // Tolerate a JSON/form POST body as well; Waija documents GET, but
            // some proxies re-encode the query.
            $payload = $request->all();
        }

        $timestamp = (string) ($payload['timestamp'] ?? '');
        $key = (string) ($payload['key'] ?? '');

        if (!$this->client->verify($timestamp, $key)) {
            return $this->respond([
                'error' => WaijaWalletService::PROCESSING_ERROR,
                'balance' => 0,
            ]);
        }

        $action = (string) ($payload['action'] ?? '');
        if ($action === '') {
            return $this->respond([
                'error' => WaijaWalletService::PROCESSING_ERROR,
                'balance' => 0,
            ]);
        }

        return $this->respond($this->wallet->handle($action, $payload, $this->client));
    }

    /** Waija requires HTTP 200 for both success and failure. */
    private function respond(array $body)
    {
        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
