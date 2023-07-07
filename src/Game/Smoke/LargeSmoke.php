<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

class LargeSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 15;
    }

    public static function getSize(): int
    {
        return 70;
    }
}