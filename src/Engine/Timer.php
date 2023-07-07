<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Timer
{
    private static float $startTime = 0;
    private static float $previousFrameStartTime = 0;
    private static float $currentFrameStartTime = 0;


    public static function getAbsoluteCurrentTime(): float
    {
        return microtime(true);
    }

    public static function getCurrentTime(): float
    {
        return self::getAbsoluteCurrentTime() - self::$startTime;
    }

    public static function init(): void
    {
        self::reset();
    }

    public static function reset(): void
    {
        self::$startTime = self::getAbsoluteCurrentTime();
        self::$previousFrameStartTime = 0;
        self::$currentFrameStartTime = 0;
    }


    public static function startFrame(): void
    {
        self::$previousFrameStartTime = self::$currentFrameStartTime;
        self::$currentFrameStartTime = self::getCurrentTime();
    }

    /**
     * @return float
     */
    public static function getStartTime(): float
    {
        return self::$startTime;
    }

    /**
     * @return float
     */
    public static function getPreviousFrameStartTime(): float
    {
        return self::$previousFrameStartTime;
    }

    /**
     * @return float
     */
    public static function getCurrentFrameStartTime(): float
    {
        return self::$currentFrameStartTime;
    }

    public static function getPreviousFrameTime(): float
    {
        return self::$currentFrameStartTime - self::$previousFrameStartTime;
    }

    public static function getExecutionTime(callable $func): float
    {
        $startTime = self::getAbsoluteCurrentTime();
        $func();
        return self::getAbsoluteCurrentTime() - $startTime;
    }
}
