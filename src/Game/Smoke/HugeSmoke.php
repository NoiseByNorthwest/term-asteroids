<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

class HugeSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 8;
    }

    public static function getSize(): int
    {
        return 140;
    }
}