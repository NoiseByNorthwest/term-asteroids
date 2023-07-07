<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Game\Smoke\SmallSmoke;

class SmallFlame extends Flame
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 25;
    }

    public static function getSize(): int
    {
        return 25;
    }

    public static function getSmokeClassName(): string
    {
        return SmallSmoke::class;
    }
}