<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Game\Smoke\MediumSmoke;

class MediumFlame extends Flame
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 20;
    }

    public static function getSize(): int
    {
        return 40;
    }

    public static function getSmokeClassName(): string
    {
        return MediumSmoke::class;
    }
}