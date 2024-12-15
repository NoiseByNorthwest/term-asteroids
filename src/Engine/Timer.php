<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Timer
{
    private static float $previousFrameStartTime = 0;

    private static float $currentFrameStartTime = 0;

    private static bool $gameTimeFrozen = false;

    private static float $gameTimeSpeedFactor = 1;

    private static float $currentGameTime = 0;

    private static float $previousFrameStartGameTime = 0;

    private static float $currentFrameStartGameTime = 0;


    public static function getAbsoluteCurrentTime(): float
    {
        return microtime(true);
    }

    public static function init(): void
    {
        self::reset();
    }

    public static function toggleGameTimeFrozen(): void
    {
        self::$gameTimeFrozen = ! self::$gameTimeFrozen;
    }

    public static function isGameTimeFrozen(): bool
    {
        return self::$gameTimeFrozen;
    }

    public static function setGameTimeSpeedFactor(float $gameTimeSpeedFactor): void
    {
        self::$gameTimeSpeedFactor = $gameTimeSpeedFactor;
    }

    public static function getGameTimeSpeedFactor(): float
    {
        return self::$gameTimeSpeedFactor;
    }

    public static function reset(): void
    {
        $absoluteCurrentTime = self::getAbsoluteCurrentTime();

        self::$previousFrameStartTime = $absoluteCurrentTime;
        self::$currentFrameStartTime = $absoluteCurrentTime;
        self::$gameTimeFrozen = false;
        self::$gameTimeSpeedFactor = 1;
        self::$currentGameTime = 0;
        self::$previousFrameStartGameTime = 0;
        self::$currentFrameStartGameTime = 0;
    }

    public static function startFrame(): void
    {
        self::$previousFrameStartTime = self::$currentFrameStartTime;
        self::$currentFrameStartTime = self::getAbsoluteCurrentTime();

        self::$currentGameTime +=
            (self::$gameTimeFrozen ? 0 : 1)
                * self::$gameTimeSpeedFactor
                * (self::$currentFrameStartTime - self::$previousFrameStartTime)
        ;

        self::$previousFrameStartGameTime = self::$currentFrameStartGameTime;
        self::$currentFrameStartGameTime = self::$currentGameTime;
    }

    public static function getPreviousFrameTime(): float
    {
        return self::$currentFrameStartTime - self::$previousFrameStartTime;
    }

    public static function getCurrentGameTime(): float
    {
        return self::$currentGameTime;
    }

    public static function getElapsedGameTimeSincePreviousFrame(): float
    {
        return self::$currentFrameStartGameTime - self::$previousFrameStartGameTime;
    }

    public static function getExecutionTime(callable $func): float
    {
        $startTime = self::getAbsoluteCurrentTime();
        $func();
        return self::getAbsoluteCurrentTime() - $startTime;
    }
}
