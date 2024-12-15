<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class CacheUtils
{
    private static array $cache = [];

    public static function memoize(mixed $key, callable $func)
    {
        $key = is_string($key) ? $key : igbinary_serialize($key);
        assert(strlen($key) < 1024);

        if (! array_key_exists($key, self::$cache)) {
            self::$cache[$key] = $func();
        }

        return self::$cache[$key];
    }

    public static function saveToFile(string $fileName): void
    {
        file_put_contents($fileName, igbinary_serialize(self::$cache));
    }

    public static function loadFromFile(string $fileName): bool
    {
        $data = igbinary_unserialize(file_get_contents($fileName));
        if (!is_array($data)) {
            return false;
        }

        self::$cache = $data;

        return true;
    }
}
