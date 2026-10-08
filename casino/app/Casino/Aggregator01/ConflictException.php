<?php

namespace VanguardLTE\Casino\Aggregator01;

/**
 * Thrown when a transaction id is replayed with different data.
 *
 * The 01.tech checklist requires an "Already exists" answer (api_code 409) when
 * the same `id` arrives again but the request differs in another field (type,
 * player, ...), rather than a silent idempotent 200.
 */
class ConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Already exists.');
    }
}
