<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Game\Flame\SmallFlame;

class SmallSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 80;
    }

    public static function getSize(): int
    {
        return Math::roundToInt(SmallFlame::getSize() * static::getFlameSizeRatio());
    }
}