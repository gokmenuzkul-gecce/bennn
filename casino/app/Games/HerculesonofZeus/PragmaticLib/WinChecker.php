<?php

namespace VanguardLTE\Games\HerculesonofZeus\PragmaticLib;

use VanguardLTE\Games\HerculesonofZeus\MathConfig;
use VanguardLTE\Services\MultiRowSlotEngine;

class WinChecker
{
    private array $paytable;
    private array $paylines;

    public function __construct($gameSettings)
    {
        $this->paytable = array_map(static fn ($item) => array_map('floatval', explode(',', $item)), explode(';', $gameSettings['paytable']));
        $this->paylines = array_map(static fn ($item) => array_map('intval', explode(',', $item)), explode(';', $gameSettings['payline']));
    }

    public function getWin($pur, $log, $bet, $slotArea)
    {
        return MultiRowSlotEngine::evaluateLines($slotArea['SlotArea'], MathConfig::ROWS, $this->paylines, $this->paytable, MathConfig::WILD, (float) $bet);
    }
}
