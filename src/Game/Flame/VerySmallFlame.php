<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Game\Smoke\VerySmallSmoke;

class VerySmallFlame extends Flame
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 50;
    }

    public static function getSize(): int
    {
        return 15;
    }

    public static function getSmokeClassName(): string
    {
        return VerySmallSmoke::class;
    }
}