<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Mover
{
    private Vec2 $dir;

    private Accelerator $accelerator;

    public function __construct(Vec2 $dir, Accelerator $accelerator)
    {
        $this->dir = $dir->copy();
        $this->dir->normalize();
        $this->accelerator = $accelerator;
    }

    public function reset(?float $maxVelocity = null): void
    {
        $this->accelerator->reset(maxVelocity: $maxVelocity);
    }

    public function getDir(): Vec2
    {
        return $this->dir;
    }

    public function getAccelerator(): Accelerator
    {
        return $this->accelerator;
    }

    public function getMoveVectorSinceLastStep(): Vec2
    {
        return $this->dir->copy()->mul(
            $this->accelerator->getDistanceSinceLastStep()
        );
    }

    public function isStopped(): bool
    {
        return $this->accelerator->isStopped();
    }
}
