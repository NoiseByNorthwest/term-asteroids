<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

class SmallSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 30;
    }

    public static function getSize(): int
    {
        return 30;
    }
}