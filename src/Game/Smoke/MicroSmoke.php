<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Game\Flame\MicroFlame;

class MicroSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 50;
    }

    public static function getSize(): int
    {
        return Math::roundToInt(MicroFlame::getSize() * static::getFlameSizeRatio());
    }
}