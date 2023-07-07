<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

class MediumSmoke extends Smoke
{
    public static function getMaxAcquiredCount(): ?int
    {
        return 20;
    }

    public static function getSize(): int
    {
        return 45;
    }
}