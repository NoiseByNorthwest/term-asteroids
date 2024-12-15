<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Game\Flame\HugeFlame;

class HugeSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 8;
    }

    public static function getSize(): int
    {
        return Math::roundToInt(HugeFlame::getSize() * static::getFlameSizeRatio());
    }

    public static function getFlameSizeRatio(): float
    {
        return 1.4;
    }
}