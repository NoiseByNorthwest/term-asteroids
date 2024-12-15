<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Game\Flame\MediumFlame;

class MediumSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 50;
    }

    public static function getSize(): int
    {
        return Math::roundToInt(MediumFlame::getSize() * static::getFlameSizeRatio());
    }
}