<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Vec3
{
    private float $x;

    private float $y;

    private float $z;

    /**
     * @param float $x
     * @param float $y
     * @param float $z
     */
    public function __construct(float $x, float $y, float $z)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    /**
     * @return float
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * @return float
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * @return float
     */
    public function getZ(): float
    {
        return $this->z;
    }

    public function getR(): float
    {
        return $this->getX();
    }

    public function getG(): float
    {
        return $this->getY();
    }

    public function getB(): float
    {
        return $this->getZ();
    }
}
