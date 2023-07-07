<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Game\Smoke\HugeSmoke;

class HugeFlame extends Flame
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 8;
    }

    public static function getSize(): int
    {
        return 120;
    }

    public static function getSmokeClassName(): string
    {
        return HugeSmoke::class;
    }
}