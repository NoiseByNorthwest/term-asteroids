<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Asteroid;

use NoiseByNorthwest\TermAsteroids\Game\Flame\SmallFlame;

class SmallAsteroid extends Asteroid
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 30;
    }

    public static function getSize(): int
    {
        return 20;
    }

    public static function getMaxVariantCount(): int
    {
        return 6;
    }

    public static function getFlameClassName(): string
    {
        return SmallFlame::class;
    }
}