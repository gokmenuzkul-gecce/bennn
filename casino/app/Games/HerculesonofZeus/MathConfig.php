<?php

namespace VanguardLTE\Games\HerculesonofZeus;

/** Audited base-game weights. Feature symbols are injected by the bonus flow. */
final class MathConfig
{
    public const ROWS = 4;
    public const WILD = 2;
    public const TARGET_BASE_RTP = 0.889;

    public static function reelWeights(): array
    {
        return [
            [3 => 358, 4 => 1951, 5 => 820, 6 => 15, 7 => 16, 8 => 16, 9 => 2990, 10 => 69, 11 => 1615, 12 => 2149],
            [3 => 118, 4 => 1300, 5 => 1498, 6 => 2433, 7 => 823, 8 => 899, 9 => 567, 10 => 1563, 11 => 265, 12 => 533],
            [3 => 213, 4 => 4559, 5 => 95, 6 => 2617, 7 => 269, 8 => 693, 9 => 433, 10 => 429, 11 => 147, 12 => 544],
            [3 => 349, 4 => 2806, 5 => 335, 6 => 361, 7 => 1507, 8 => 62, 9 => 1732, 10 => 310, 11 => 714, 12 => 1825],
            [3 => 18, 4 => 3010, 5 => 29, 6 => 58, 7 => 273, 8 => 929, 9 => 2143, 10 => 38, 11 => 2686, 12 => 817],
        ];
    }
}
