<?php

namespace VanguardLTE\Casino\Providers\Contracts;

/**
 * Contract for a casino game provider that speaks the seamless-wallet protocol.
 *
 * All four providers (Pragmatic, PGSoft, Amatic, Amusnet) expose the same
 * callbacks, so the wallet controller depends on this interface rather than on
 * any concrete vendor. Implementations only differ in endpoint + credentials.
 */
interface CasinoProvider
{
    /** Stable machine identifier persisted in settings (e.g. "pragmatic"). */
    public function key(): string;

    /** Operator-facing label rendered in Liteback. */
    public function label(): string;

    /** Resolved connection settings (endpoint, agent id, token, secret, callback). */
    public function config(): array;

    /** Whether endpoint + agent id + secret are all present. */
    public function isConfigured(): bool;

    /**
     * Human readable configuration state for the admin panel.
     *
     * @return array{configured: bool, message: string}
     */
    public function configStatus(): array;

    /**
     * Compute the HMAC-SHA256 signature over the ordered parameter values.
     *
     * @param  array<string, mixed>  $params  operation payload (any key casing)
     * @param  array<int, string>    $order   ordered logical field names
     */
    public function sign(array $params, array $order): string;

    /**
     * Verify the "sign" field of an incoming callback.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>    $order
     */
    public function verify(array $payload, array $order): bool;

    /**
     * Verify connectivity without moving any money.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnectivity(): array;

    /** Vendor-facing launch URL for a player session. */
    public function launchUrl(string $userCode, string $gameId, string $lang = 'tr'): string;

    /**
     * Ordered field list used to build the signature for a wallet operation.
     *
     * @return array<int, string>
     */
    public function signOrder(string $operation): array;

    /**
     * Everything the lobby needs to open a session in an in-site frame.
     *
     * Returning a "form" means the vendor answered with an auto-submitting POST
     * page rather than a final game URL; the caller replays that POST from our
     * own page so the browser will allow it inside the iframe.
     *
     * @return array{url: string, form?: array{action: string, fields: array<string, string>}}
     */
    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array;
}
