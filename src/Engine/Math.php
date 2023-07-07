<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

abstract class Math
{
    public static function bound(int|float $value, int|float $min = 0, int|float $max = 1): int|float
    {
        return max($min, min($value, $max));
    }

    public static function lerpRoundTrip(float $a, float $b, float $dist)
    {
        $dist = $dist - (int) $dist;
        assert($dist >= 0);
        assert($dist <= 1);

        if ($dist > 0.5) {
            $dist = 1 - $dist;
        }

        assert($dist <= 0.5);

        return self::lerp($a, $b, $dist / 0.5);
    }

    public static function lerpPath(array $pathComponents, float $dist): float
    {
        $previous = null;
        $prevKey = null;
        foreach ($pathComponents as $k => $current) {
            $k = (float) $k;
            if ($previous !== null && $dist <= $k) {
                assert($dist >= $prevKey);

                return self::lerp($previous, $current, ($dist - $prevKey) / ($k - $prevKey));
            }

            $previous = $current;
            $prevKey = $k;
        }

        throw new \RuntimeException('unreachable');
    }

    public static function lerp(float $a, float $b, float $dist): float
    {
        assert(0 <= $dist && $dist <= 1);

        return $a * (1 - $dist) + $b * $dist;
    }

    public static function dist(float $a, float $b): float
    {
        return $a < $b ? $b - $a : $a - $b;
    }

    public static function roundToInt(float $v): int
    {
        return (int) round($v);
    }
}