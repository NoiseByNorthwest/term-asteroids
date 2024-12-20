<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Game\Flame\VerySmallFlame;

class VerySmallSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 140;
    }

    public static function getSize(): int
    {
        return Math::roundToInt(VerySmallFlame::getSize() * static::getFlameSizeRatio());
    }
}