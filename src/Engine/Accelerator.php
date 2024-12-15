<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Accelerator
{
    private float $maxVelocity;
    private float $timeFromStopToMaxVelocity;
    private float $timeFromMaxVelocityToStop;
    private float $stopDelay;
    private bool $autoStart;
    private float $startTime;
    private float $stopTime;
    private float $lastStepTime;

    public function __construct(
        float $maxVelocity,
        float $timeFromStopToMaxVelocity,
        float $timeFromMaxVelocityToStop,
        float $stopDelay = INF,
        bool $autoStart = true
    ) {
        $this->maxVelocity = $maxVelocity;
        $this->timeFromStopToMaxVelocity = $timeFromStopToMaxVelocity;
        $this->timeFromMaxVelocityToStop = $timeFromMaxVelocityToStop;
        $this->stopDelay = $stopDelay;
        $this->autoStart = $autoStart;

        $this->reset();
    }

    public function reset(?float $maxVelocity = null): void
    {
        $this->maxVelocity = $maxVelocity ?? $this->maxVelocity;
        $this->startTime = INF;
        $this->stopTime = INF;
        $this->lastStepTime = INF;

        if ($this->autoStart) {
            $this->restart(null, $this->stopDelay);
        }
    }

    /**
     * @return float
     */
    public function getMaxVelocity(): float
    {
        return $this->maxVelocity;
    }

    /**
     * @return float
     */
    public function getTimeFromStopToMaxVelocity(): float
    {
        return $this->timeFromStopToMaxVelocity;
    }

    public function getAcceleration(): float
    {
        return $this->maxVelocity / $this->getTimeFromStopToMaxVelocity();
    }

    /**
     * @return float
     */
    public function getTimeFromMaxVelocityToStop(): float
    {
        return $this->timeFromMaxVelocityToStop;
    }

    public function getDeceleration(): float
    {
        $timeFromMaxVelocityToStop = $this->getTimeFromMaxVelocityToStop();
        if ($timeFromMaxVelocityToStop === 0.0) {
            return PHP_FLOAT_MAX;
        }

        return $this->maxVelocity / $timeFromMaxVelocityToStop;
    }

    public function restart(?float $time = null, $stopDelay = INF): void
    {
        $this->startTime = $time ?? Timer::getCurrentGameTime();
        $this->stopTime = $this->startTime + $stopDelay;
        $this->lastStepTime = $this->startTime;
    }

    public function stop(?float $time = null): void
    {
        $this->stopTime = $time ?? Timer::getCurrentGameTime();
        assert($this->startTime <= $this->stopTime);
    }

    public function getVelocity(?float $time = null): float
    {
        if ($time === null) {
            $time = Timer::getCurrentGameTime();
        }

        if ($time <= $this->startTime) {
            return 0;
        }

        if ($time >= $this->stopTime + $this->timeFromMaxVelocityToStop) {
            return 0;
        }

        if ($time > $this->stopTime) {
            return $this->getVelocity($this->stopTime) - $this->getDeceleration() * ($time - $this->stopTime);
        }

        if ($time < $this->startTime + $this->timeFromStopToMaxVelocity) {
            return $this->getAcceleration() * ($time - $this->startTime);
        }

        return $this->maxVelocity;
    }

    public function isStopped(): bool
    {
        return Timer::getCurrentGameTime() > $this->startTime && $this->getVelocity() === 0.0;
    }

    public function getTimeToStop(?float $time = null): float
    {
        return $this->getVelocity($time) / $this->getDeceleration();
    }

    public function getDistance(?float $time = null): float
    {
        if ($time === null) {
            $time = Timer::getCurrentGameTime();
        }

        if ($time <= $this->startTime) {
            return 0;
        }

        $distance = 0;

        if ($time > $this->stopTime) {
            $timePeriod = min(
                $time,
                $this->stopTime + $this->getTimeToStop($this->stopTime)
            ) - $this->stopTime;

            $distance += $timePeriod * $this->getVelocity($this->stopTime) - 0.5 * $this->getDeceleration() * ($timePeriod ** 2);
        }

        if (
            $time > $this->startTime + $this->timeFromStopToMaxVelocity &&
            $this->stopTime > $this->startTime + $this->timeFromStopToMaxVelocity
        ) {
            $timePeriod = min($time, $this->stopTime) - ($this->startTime + $this->timeFromStopToMaxVelocity);
            $distance += $timePeriod * $this->maxVelocity;
        }

        if ($time > $this->startTime) {
            $timePeriod = min($time, $this->startTime + $this->timeFromStopToMaxVelocity, $this->stopTime) - $this->startTime;
            $distance += 0.5 * $this->getAcceleration() * ($timePeriod ** 2);
        }

        return $distance;
    }

    public function getDistanceSinceLastStep(): float
    {
        $newStepTime = Timer::getCurrentGameTime();
        $distance = $this->getDistance($newStepTime) - $this->getDistance($this->lastStepTime);
        $this->lastStepTime = $newStepTime;

        return $distance;
    }
}