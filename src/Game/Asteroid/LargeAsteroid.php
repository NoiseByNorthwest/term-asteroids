<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Asteroid;

use NoiseByNorthwest\TermAsteroids\Game\Flame\LargeFlame;

class LargeAsteroid extends Asteroid
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 20;
    }

    public static function getSize(): int
    {
        return 40;
    }

    public static function getMaxVariantCount(): int
    {
        return 4;
    }

    public static function getFlameClassName(): string
    {
        return LargeFlame::class;
    }
}