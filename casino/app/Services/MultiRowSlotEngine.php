<?php

namespace VanguardLTE\Services;

use InvalidArgumentException;

/** Provider-neutral math core for fixed-row slot games. */
final class MultiRowSlotEngine
{
    public static function generateWeightedGrid(array $reelWeights, int $rows, ?callable $randomInt = null): array
    {
        if ($rows < 1 || count($reelWeights) < 1) {
            throw new InvalidArgumentException('A slot grid needs at least one row and one reel.');
        }

        $randomInt = $randomInt ?: static fn (int $min, int $max): int => random_int($min, $max);
        $columns = [];
        foreach ($reelWeights as $weights) {
            $normalised = self::normaliseWeights($weights);
            $total = array_sum($normalised);
            $column = [];
            for ($row = 0; $row < $rows; $row++) {
                $pick = $randomInt(1, $total);
                $cursor = 0;
                foreach ($normalised as $symbol => $weight) {
                    $cursor += $weight;
                    if ($pick <= $cursor) {
                        $column[] = (string) $symbol;
                        break;
                    }
                }
            }
            $columns[] = $column;
        }

        $grid = [];
        for ($row = 0; $row < $rows; $row++) {
            foreach ($columns as $column) {
                $grid[] = $column[$row];
            }
        }
        return $grid;
    }

    public static function evaluateLines(array $grid, int $rows, array $paylines, array $paytable, int $wild, float $betPerLine): array
    {
        $reels = count($paylines[0] ?? []);
        if ($rows < 1 || $reels < 1 || count($grid) !== $rows * $reels) {
            throw new InvalidArgumentException('Grid dimensions do not match the configured paylines.');
        }
        $rowGrid = array_chunk($grid, $reels);
        $total = 0.0;
        $wins = [];
        foreach ($paylines as $lineIndex => $payline) {
            if (count($payline) !== $reels) {
                throw new InvalidArgumentException('Every payline must address each reel exactly once.');
            }
            $symbols = [];
            foreach ($payline as $reel => $row) {
                if (!isset($rowGrid[(int) $row][$reel])) {
                    throw new InvalidArgumentException('Payline points outside the slot grid.');
                }
                $symbols[] = (int) $rowGrid[(int) $row][$reel];
            }
            $winningSymbol = $symbols[0];
            $count = 1;
            foreach (array_slice($symbols, 1) as $symbol) {
                if ($winningSymbol === $wild && $symbol !== $wild) {
                    $winningSymbol = $symbol;
                }
                if ($symbol !== $wild && $winningSymbol !== $wild && $symbol !== $winningSymbol) {
                    break;
                }
                $count++;
            }
            $pays = $paytable[$winningSymbol] ?? [];
            $payIndex = count($pays) - $count;
            $multiplier = $payIndex >= 0 ? (float) ($pays[$payIndex] ?? 0) : 0.0;
            $lineWin = round($multiplier * $betPerLine, 2);
            if ($lineWin <= 0) {
                continue;
            }
            $positions = [];
            for ($reel = 0; $reel < $count; $reel++) {
                $positions[] = ((int) $payline[$reel] * $reels) + $reel;
            }
            $wins[] = ['WinSymbol' => $winningSymbol, 'CountSymbols' => $count, 'Pay' => number_format($lineWin, 2, '.', ''), 'Positions' => $positions, 'l' => $lineIndex];
            $total += $lineWin;
        }
        return ['TotalWin' => number_format($total, 2, '.', ''), 'WinLines' => $wins];
    }

    private static function normaliseWeights(array $weights): array
    {
        $normalised = [];
        foreach ($weights as $symbol => $weight) {
            if ((int) $weight > 0) {
                $normalised[(string) $symbol] = (int) $weight;
            }
        }
        if (!$normalised) {
            throw new InvalidArgumentException('A reel must contain at least one positive symbol weight.');
        }
        return $normalised;
    }
}
