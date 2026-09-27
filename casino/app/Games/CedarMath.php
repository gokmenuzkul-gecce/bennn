<?php

namespace VanguardLTE\Games;

final class CedarMath
{
    public const STEPS = [1.20, 1.50, 2.00, 2.80, 4.00, 6.00, 10.00, 18.00, 35.00, 100.00];

    public static function pathLadder(int $choices, int $levels, float $edge = 5.0): array
    {
        $survival = ($choices - 1) / $choices;
        $ladder = [];
        for ($level = 1; $level <= $levels; $level++) {
            $value = (1 - $edge / 100) / ($survival ** $level);
            $ladder[] = floor(($value + 1e-10) * 10000) / 10000;
        }
        return $ladder;
    }

    public static function kenoTable(float $edge = 5.0): array
    {
        $raw = [0, 0, 0.4, 1.5, 8, 80];
        $expectation = 0.0;
        for ($hits = 0; $hits <= 5; $hits++) {
            $expectation += self::combination(5, $hits) * self::combination(35, 10 - $hits) /
                self::combination(40, 10) * $raw[$hits];
        }
        $scale = (1 - $edge / 100) / $expectation;
        return array_map(fn ($value) => floor(($value * $scale + 1e-10) * 10000) / 10000, $raw);
    }

    private static function combination(int $n, int $k): float
    {
        if ($k < 0 || $k > $n) return 0.0;
        $k = min($k, $n - $k); $value = 1.0;
        for ($i = 1; $i <= $k; $i++) $value *= ($n - $k + $i) / $i;
        return $value;
    }

    // One hidden draw gives each stopping point at most (100-edge)% return.
    public static function stepsTrap(string $server, string $client, int $nonce, float $edge = 5.0): int
    {
        $draw = self::integer($server, $client, $nonce, 10000);
        foreach (self::STEPS as $i => $mult) {
            if ($draw >= floor((100 - $edge) * 100 / $mult)) return $i + 1;
        }
        return 11;
    }

    public const VERSION = 'cedar-v2';

    // Rejection sampling avoids modulo bias. The counter extends the HMAC stream.
    public static function integer(string $server, string $client, int $nonce, int $size, int $cursor = 0): int
    {
        if ($size < 1 || $size > 10000) {
            throw new \InvalidArgumentException('Invalid sample size.');
        }
        $limit = intdiv(4294967296, $size) * $size;
        for ($attempt = 0; ; $attempt++) {
            $hash = hash_hmac('sha256', "$client:$nonce:$cursor:$attempt", $server);
            $value = (int) hexdec(substr($hash, 0, 8));
            if ($value < $limit) return $value % $size;
        }
    }

    public static function wheel(): array
    {
        $tables = CedarTables::WHEEL;
        foreach ($tables as $count => &$risks) {
            foreach ($risks as &$values) {
                $scale = 0.95 * $count / array_sum($values);
                foreach ($values as &$value) $value = floor(($value * $scale + 1e-10) * 10000) / 10000;
                unset($value);
            }
            unset($values);
        }
        return $tables;
    }

    public static function plinkoRtp(int $rows, string $risk): float
    {
        $probability = 1 / (2 ** $rows);
        $rtp = 0;
        foreach (CedarTables::PLINKO[$rows][$risk] as $k => $value) {
            $rtp += $probability * $value;
            $probability *= ($rows - $k) / ($k + 1);
        }
        return $rtp;
    }

    public static function mines(string $server, string $client, int $nonce, int $count): array
    {
        $tiles = range(0, 24);
        for ($i = 24; $i > 0; $i--) {
            $j = self::integer($server, $client, $nonce, $i + 1, 24 - $i);
            [$tiles[$i], $tiles[$j]] = [$tiles[$j], $tiles[$i]];
        }
        $mines = array_slice($tiles, 0, $count);
        sort($mines);
        return $mines;
    }

    public static function minesMultiplier(int $mines, int $revealed, float $edge): float
    {
        if ($mines < 1 || $mines > 24 || $revealed < 0 || $revealed > 25 - $mines) {
            throw new \InvalidArgumentException('Invalid mine count.');
        }
        if (!$revealed) return 1.0;
        $multiplier = 1 - $edge / 100;
        for ($k = 0; $k < $revealed; $k++) $multiplier *= (25 - $k) / (25 - $mines - $k);
        return floor(($multiplier + 1e-10) * 10000) / 10000;
    }

    public static function crash(string $server, string $client, int $nonce, float $edge, float $cap): float
    {
        $hash = hash_hmac('sha256', "$client:$nonce", $server);
        $u = hexdec(substr($hash, 0, 13)) / 4503599627370496;
        return max(1.0, min($cap, floor(((1 - $edge / 100) / (1 - $u)) * 100) / 100));
    }

    public static function flight(float $elapsed): float
    {
        return floor((1 + pow(max(0, $elapsed) * 0.9, 1.85)) * 100) / 100;
    }

    public static function flightSeconds(float $multiplier): float
    {
        return pow(max(0, $multiplier - 1), 1 / 1.85) / 0.9;
    }
}
