<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class ScreenAreaStats
{
    private static array $stats = [];

    public static function inc(string $name, Vec2 $pos): void
    {
        if (! isset(self::$stats[$name])) {
            self::$stats[$name] = [];
        }

        $areaKey = self::posToAreaKey($pos);

        if (! isset(self::$stats[$name][$areaKey])) {
            self::$stats[$name][$areaKey] = 1;
        } else {
            self::$stats[$name][$areaKey]++;
        }
    }

    public static function dec(string $name, Vec2 $pos): void
    {
        assert(isset(self::$stats[$name]));

        $areaKey = self::posToAreaKey($pos);

        assert(isset(self::$stats[$name][$areaKey]));
        assert(self::$stats[$name][$areaKey] > 0);

        self::$stats[$name][$areaKey]--;
    }

    public static function get(string $name, Vec2 $pos): int
    {
        return (self::$stats[$name] ?? [])[self::posToAreaKey($pos)] ?? 0;
    }

    private static function posToAreaKey(Vec2 $pos): string
    {
        return
            self::posComponentToAreaComponent($pos->getX()) . '-'
                . self::posComponentToAreaComponent($pos->getY());
    }

    private static function posComponentToAreaComponent(float $component): int
    {
        return (int) ($component / 10);
    }
}