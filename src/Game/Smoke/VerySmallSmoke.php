<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

class VerySmallSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 40;
    }

    public static function getSize(): int
    {
        return 20;
    }
}