<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Game\Smoke\LargeSmoke;

class LargeFlame extends Flame
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 15;
    }

    public static function getSize(): int
    {
        return 60;
    }

    public static function getSmokeClassName(): string
    {
        return LargeSmoke::class;
    }
}