<?php

namespace VanguardLTE\Casino\SoftAggregator;

use VanguardLTE\Casino\Waija\WaijaWalletService;

/**
 * SoftAggregator seamless-wallet callbacks.
 *
 * SoftAggregator documents the identical wallet protocol to Waija (GET callbacks
 * with action=balance|debit|credit, integer cents, md5(timestamp+salt_key)
 * signature, always HTTP 200), so the shared implementation is reused verbatim;
 * only the ledger provider key changes so the two aggregators never collide on
 * a transaction id.
 */
class SoftAggregatorWalletService extends WaijaWalletService
{
    public const PROVIDER_KEY = 'softaggregator';
}
