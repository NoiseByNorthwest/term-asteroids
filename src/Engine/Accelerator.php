<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Accelerator
{
    private float $maxVelocity;
    private float $timeFromStopToMaxVelocity;
    private float $timeFromMaxVelocityToStop;
    private float $startTime;
    private float $stopTime;
    private float $lastStepTime;

    public function __construct(
        float $maxVelocity,
        float $timeFromStopToMaxVelocity,
        float $timeFromMaxVelocityToStop,
        float $stopDelay = INF
    ) {
        $this->maxVelocity = $maxVelocity;
        $this->timeFromStopToMaxVelocity = $timeFromStopToMaxVelocity;
        $this->timeFromMaxVelocityToStop = $timeFromMaxVelocityToStop;

        $this->restart(null, $stopDelay);
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
        return $this->maxVelocity / $this->getTimeFromMaxVelocityToStop();
    }

    public function restart(?float $time = null, $stopDelay = INF): void
    {
        $this->startTime = $time ?? Timer::getCurrentTime();
        $this->stopTime = $this->startTime + $stopDelay;
        $this->lastStepTime = $this->startTime;
    }

    public function stop(?float $time = null): void
    {
        $this->stopTime = $time ?? Timer::getCurrentTime();
        assert($this->startTime < $this->stopTime);
    }

    public function getVelocity(?float $time = null): float
    {
        if ($time === null) {
            $time = Timer::getCurrentTime();
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
        return $this->getVelocity() === 0.0;
    }

    public function getTimeToStop(?float $time = null): float
    {
        return $this->getVelocity($time) / $this->getDeceleration();
    }

    public function getDistance(?float $time = null): float
    {
        if ($time === null) {
            $time = Timer::getCurrentTime();
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
        $newStepTime = Timer::getCurrentTime();
        $distance = $this->getDistance($newStepTime) - $this->getDistance($this->lastStepTime);
        $this->lastStepTime = $newStepTime;

        return $distance;
    }
}