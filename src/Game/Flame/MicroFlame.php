<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Game\Smoke\MicroSmoke;

class MicroFlame extends Flame
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 30;
    }

    public static function getSize(): int
    {
        return 8;
    }

    public static function getSmokeClassName(): string
    {
        return MicroSmoke::class;
    }
}