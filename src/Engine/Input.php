<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Input
{
    private static $stdin = null;

    public static function init()
    {
        self::$stdin = fopen('php://stdin', 'r');
        assert(self::$stdin !== false);

        stream_set_blocking(self::$stdin, 0);
        system('stty cbreak -echo');
    }

    public static function getPressedKey(): ?string
    {
        assert(self::$stdin !== null);

        $pressedKey = fgets(self::$stdin);
        if ($pressedKey === false) {
            return null;
        }

        return $pressedKey;
    }
}
