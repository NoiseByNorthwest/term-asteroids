<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class SpriteEffectHelper
{
    public static function generateHorizontalDistortionOffsets(int $height, ?float $maxAmplitude = null, ?float $amplitude = null, float $timeFactor = 1, float $shearFactor = 1): array
    {
        if ($amplitude === null) {
            assert($maxAmplitude !== null);
            $amplitude = $maxAmplitude * (0.5 + 0.5 * sin($timeFactor * Timer::getCurrentGameTime()));
        } else {
            assert($maxAmplitude === null);
            assert($timeFactor === 1.0);
        }

        $half = ceil($height / 2);

        $horizontalDistortionOffsets = [];

        for ($i = 0; $i < $half; $i++) {
            $offset = Math::roundToInt(
                $amplitude * (0.5 + 0.5 * sin(1.5 * M_PI + $shearFactor * M_PI * $i / ($half - 1)))
            );

            assert($offset >= 0);
            $horizontalDistortionOffsets[] = $offset;
        }

        for ($i = $half; $i < $height; $i++) {
            $horizontalDistortionOffsets[] = $horizontalDistortionOffsets[$half - 1 - ($height % 2) - ($i - $half)];
        }

        return $horizontalDistortionOffsets;
    }
}