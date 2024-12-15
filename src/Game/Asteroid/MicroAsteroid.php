<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Asteroid;

use NoiseByNorthwest\TermAsteroids\Game\Flame\MicroFlame;

class MicroAsteroid extends Asteroid
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 20;
    }

    public static function getSize(): int
    {
        return 5;
    }

    public static function getMaxVariantCount(): int
    {
        return 1;
    }

    public static function getFlameClassName(): string
    {
        return MicroFlame::class;
    }
}