<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class RandomUtils
{
    public static function generateIntegerSeedFromData(mixed $data): int
    {
        if (is_int($data)) {
            return $data;
        }

        /*
            8 // sizeof natural int (64 bits)
                * 2 // octet char-length in hex
                - 2 // to avoid having hexdec generating a float due to integer overflow
        */
        return \hexdec(\substr(\md5(\serialize($data)), 0, 8 * 2 - 2));
    }

    public static function getRandomBool(float $trueProbability = 0.5): bool
    {
        return self::getRandomFloat() < $trueProbability;
    }

    public static function getRandomInt(
        int $min = 0,
        ?int $max = null
    ): int {
        if ($max === null) {
            $max = \mt_getrandmax();
        }

        \assert($min <= $max);

        return \mt_rand($min, $max);
    }

    public static function getRandomFloat(
        float $min = 0,
        float $max = 1,
    ): float {
        \assert($min < $max);

        $n = \mt_rand() / \mt_getrandmax();

        return $min + ($max - $min) * $n;
    }
}
