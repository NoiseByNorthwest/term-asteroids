<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Game\Flame\LargeFlame;

class LargeSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 15;
    }

    public static function getSize(): int
    {
        return Math::roundToInt(LargeFlame::getSize() * static::getFlameSizeRatio());
    }
}