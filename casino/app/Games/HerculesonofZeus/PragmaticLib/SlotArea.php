<?php

namespace VanguardLTE\Games\HerculesonofZeus\PragmaticLib;

use VanguardLTE\Games\HerculesonofZeus\MathConfig;
use VanguardLTE\Services\MultiRowSlotEngine;

class SlotArea
{
    public static function getSlotArea($gameSettings, $reelset, $log)
    {
        $slotArea = MultiRowSlotEngine::generateWeightedGrid(MathConfig::reelWeights(), MathConfig::ROWS);
        $reels = array_fill(0, count(MathConfig::reelWeights()), []);
        foreach (array_chunk($slotArea, count($reels)) as $row) {
            foreach ($row as $reel => $symbol) {
                $reels[$reel][] = $symbol;
            }
        }
        $symbolsAfter = [];
        $symbolsBelow = [];
        foreach ($reels as $reel => $symbols) {
            $extra = MultiRowSlotEngine::generateWeightedGrid([MathConfig::reelWeights()[$reel]], 2);
            $symbolsAfter[] = $extra[0];
            $symbolsBelow[] = $extra[1];
        }
        return ['SlotArea' => $slotArea, 'SymbolsAfter' => $symbolsAfter, 'SymbolsBelow' => $symbolsBelow, 'ScatterCount' => 0];
    }

    public static function getPsym($gameSettings, $slotarea, $bet, $lines)
    {
        $scatterTmp = explode('~', $gameSettings['scatters']);
        $scatter = $scatterTmp[0];
        $scatterCount = count(array_keys($slotarea, $scatter));
        $scatterPayTable = explode(',', $scatterTmp[1]);
        $pay = $scatterCount > 0 ? round(((float) ($scatterPayTable[$scatterCount - 1] ?? 0)) * $bet * $lines, 2) : 0;
        return ['psym' => $scatter . '~' . $pay . '~' . implode(',', array_keys($slotarea, $scatter)), 'psymwin' => $pay];
    }
}
