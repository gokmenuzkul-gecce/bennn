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
 * by the account secret. The reply body carries balance, currency, login, status
 * and error.
 *
 * The Hub reads the HTTP status, not just the body: a confirmed operation must
 * answer 2xx with status "success", while a rejection (bad signature, unknown
 * player, insufficient funds, unknown reference) must answer HTTP 400 so the
 * provider retries and refuses to start the spin. Returning 200 for a failure
 * would let a bet through without funds.
 *
 * The Hub is configured with a callback base URL and appends its own paths, so
 * the same logic is exposed at the generic "/callbacks" route and at the three
 * endpoints the Hub actually calls: /api/balance, /api/transaction and
 * /api/batch-transaction.
 */
class GregmornWebhookController extends Controller
{
    public function __construct(
        private readonly GregmornClient $client,
        private readonly GregmornWalletService $wallet
    ) {
    }

    /** Generic callback route: the command travels in the body. */
    public function handle(Request $request)
    {
        [$payload, $error] = $this->payload($request);
        if ($error) {
            return $error;
        }

        $cmd = (string) ($payload['cmd'] ?? '');
        if ($cmd === '') {
            return $this->respond($this->failBody('Missing cmd'), 400);
        }

        return $this->respond($this->wallet->handle($cmd, $payload, $this->client));
    }

    /** POST /api/balance — player balance lookup. */
    public function balance(Request $request)
    {
        [$payload, $error] = $this->payload($request);
        if ($error) {
            return $error;
        }

        return $this->respond($this->wallet->apiBalance($payload, $this->client));
    }

    /** POST /api/transaction — one stake/win (or a rollback). */
    public function transaction(Request $request)
    {
        [$payload, $error] = $this->payload($request);
        if ($error) {
            return $error;
        }

        return $this->respond($this->wallet->apiTransaction($payload, $this->client));
    }

    /** POST /api/batch-transaction — several transactions applied in order. */
    public function batchTransaction(Request $request)
    {
        [$payload, $error] = $this->payload($request);
        if ($error) {
            return $error;
        }

        return $this->respond($this->wallet->apiBatchTransaction($payload, $this->client));
    }

    /**
     * Verify the signature and decode the JSON body.
     *
     * @return array{0: array<string, mixed>, 1: \Illuminate\Http\JsonResponse|null}
     */
    private function payload(Request $request): array
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Signature', '');

        if (!$this->client->verifyWebhook($raw, $signature)) {
            return [[], $this->respond($this->failBody('Invalid signature'), 400)];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $request->all();
        }

        return [$payload, null];
    }

    /** @return array<string, mixed> */
    private function failBody(string $error): array
    {
        return [
            'balance' => 0,
            'currency' => $this->client->currency(),
            'duration' => 0,
            'error' => $error,
            'login' => '',
            'status' => 'fail',
        ];
    }

    /**
     * Answer the Hub. A "fail" body is sent with HTTP 400 because the provider
     * gates the spin on the status code: 2xx lets it continue, 400 makes it
     * retry and abort. Only a confirmed "success" is answered with HTTP 200.
     */
    private function respond(array $body)
    {
        $status = (($body['status'] ?? '') === 'success') ? 200 : 400;

        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
