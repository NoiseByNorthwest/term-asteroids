<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Asteroid;

use NoiseByNorthwest\TermAsteroids\Game\Flame\VerySmallFlame;

class VerySmallAsteroid extends Asteroid
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 30;
    }

    public static function getSize(): int
    {
        return 10;
    }

    public static function getMaxVariantCount(): int
    {
        return 1;
    }

    public static function getFlameClassName(): string
    {
        return VerySmallFlame::class;
    }
}