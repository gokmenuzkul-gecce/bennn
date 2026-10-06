<?php

namespace VanguardLTE\Http\Controllers\Web\Webhooks;

use Illuminate\Http\Request;
use VanguardLTE\Casino\Gregmorn\GregmornClient;
use VanguardLTE\Casino\Gregmorn\GregmornWalletService;
use VanguardLTE\Http\Controllers\Controller;

/**
 * Gregmorn Hub seamless-wallet webhook.
 *
 * The Hub posts every command (getBalance / writeBet / rollback) to one URL as
 * JSON, signed with X-Signature: hex HMAC-SHA256 over the exact raw body, keyed
 * by the account secret. The reply is always HTTP 200 with a JSON body carrying
 * balance, currency, login, status and error.
 */
class GregmornWebhookController extends Controller
{
    public function __construct(
        private readonly GregmornClient $client,
        private readonly GregmornWalletService $wallet
    ) {
    }

    public function handle(Request $request)
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Signature', '');

        if (!$this->client->verifyWebhook($raw, $signature)) {
            return $this->respond([
                'balance' => 0,
                'currency' => $this->client->currency(),
                'duration' => 0,
                'error' => 'Invalid signature',
                'login' => '',
                'status' => 'fail',
            ]);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $request->all();
        }

        $cmd = (string) ($payload['cmd'] ?? $request->input('cmd', ''));
        if ($cmd === '') {
            return $this->respond([
                'balance' => 0,
                'currency' => $this->client->currency(),
                'duration' => 0,
                'error' => 'Missing cmd',
                'login' => '',
                'status' => 'fail',
            ]);
        }

        return $this->respond($this->wallet->handle($cmd, $payload, $this->client));
    }

    private function respond(array $body)
    {
        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
