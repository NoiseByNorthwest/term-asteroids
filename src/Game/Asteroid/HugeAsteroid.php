<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Asteroid;

use NoiseByNorthwest\TermAsteroids\Game\Flame\HugeFlame;

class HugeAsteroid extends Asteroid
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 10;
    }

    public static function getSize(): int
    {
        return 80;
    }

    public static function getMaxVariantCount(): int
    {
        return 1;
    }

    public static function getFlameClassName(): string
    {
        return HugeFlame::class;
    }
}