<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class AdaptivePerformanceManager
{
    private float $minFps;

    private bool $enabled = true;

    private float $allowedResourceConsumptionRatio = 1;

    public function __construct(float $minFps)
    {
        $this->minFps = $minFps;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function toggleEnabled(): void
    {
        $this->enabled = ! $this->enabled;
    }

    public function update(): void
    {
        if (! $this->enabled) {
            $this->allowedResourceConsumptionRatio = 1;

            return;
        }

        $maxFrameTime = 1 / $this->minFps;
        $highFrameTime = $maxFrameTime * 0.6;
        $criticalFrameTime = $maxFrameTime * 1.05;

        $this->allowedResourceConsumptionRatio = 1 - Math::bound(
            Math::relativeDist(Timer::getPreviousFrameTime(), $highFrameTime, $criticalFrameTime)
        );
    }

    public function getAllowedResourceConsumptionRatio(): float
    {
        return $this->allowedResourceConsumptionRatio;
    }
}
