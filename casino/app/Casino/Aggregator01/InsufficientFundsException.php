<?php

namespace VanguardLTE\Casino\Aggregator01;

/**
 * Thrown inside a BetWin transaction when a bet would overdraw the balance.
 *
 * It carries the balance at the moment of refusal so the caller can return the
 * documented insufficient-funds error (api_code 100) with an accurate balance
 * after the transaction has been rolled back.
 */
class InsufficientFundsException extends \RuntimeException
{
    public function __construct(private readonly string $balance = '0')
    {
        parent::__construct('Not enough funds.');
    }

    public function balance(): string
    {
        return $this->balance;
    }
}
