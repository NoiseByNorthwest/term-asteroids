<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class BitmapNoiseGenerator
{
    public static function generate(
        int $width,
        int $height,
        array $colorGradient,
        mixed $seed = null,
        float $shift = 0,
        float $radius = 1,
        float $zFactor = 1,
        ?array $scales = null,
        int $maxScaleCount = 6
    ): Bitmap {
        $seed = RandomUtils::generateIntegerSeedFromData($seed ?? RandomUtils::getRandomInt()) % 21;

        return CacheUtils::memoize(
            [
                __METHOD__,
                $width,
                $height,
                $colorGradient,
                $seed,
                round($shift, 2),
                round($radius, 2),
                round($zFactor, 2),
                $scales,
                $maxScaleCount
            ],
            function () use(
                $width,
                $height,
                $colorGradient,
                $seed,
                $shift,
                $radius,
                $zFactor,
                $scales,
                $maxScaleCount
            ) {
                $colorGradient = array_map(fn ($e) => ColorUtils::createColor($e), $colorGradient);

                if ($scales === null) {
                    $currentScale = $width * 0.6;
                    $scales = [];
                    while ($currentScale > 1) {
                        $scales[] = $currentScale;
                        $currentScale *= 0.5;
                    }
                }

                sort($scales);
                $scales = array_slice($scales, -$maxScaleCount);

                $noiseMap = self::generateNoiseMap(
                    $width,
                    $height,
                    $seed,
                    $shift,
                    $radius,
                    $scales
                );

                $maxZ = max(...$noiseMap);
                $pixels = [];
                for ($y = 0; $y < $height; $y++) {
                    for ($x = 0; $x < $width; $x++) {
                        $z = $noiseMap[$y * $width + $x];

                        if ($z <= 0) {
                            $pixels[$y * $width + $x] = $colorGradient[array_key_first($colorGradient)];

                            continue;
                        }

                        $z /= $maxZ;

                        assert($z <= 1);
                        assert($z >= 0);

                        $pixels[$y * $width + $x] = ColorUtils::createColorWithinGradient(
                            $colorGradient,
                            Math::bound($z * $zFactor)
                        );
                    }
                }

                return new Bitmap($width, $height, $pixels);
            }
        );
    }

    private static function generateNoiseMap(
        int $width,
        int $height,
        int $seed,
        float $shift,
        float $radius,
        array $scales
    ): array {
        static $cache = [];

        $cacheKey = igbinary_serialize([
            $width,
            $height,
            $seed,
            round($shift, 2),
            round($radius, 2),
            $scales
        ]);

        if (!isset($cache[$cacheKey])) {
            $maxScale = $scales[array_key_last($scales)];

            $pixels = array_fill(0, $width * $height, 0);

            for ($y = 0; $y < $height; $y++) {
                $distY = Math::dist($y, $height / 2) / ($height / 2);

                for ($x = 0; $x < $width; $x++) {
                    $distX = Math::dist($x, $width / 2) / ($width / 2);

                    if ((new Vec2($distX, $distY))->computeLength() > $radius) {
                        $z = 0;
                    } else {
                        $z = 1;
                        foreach ($scales as $k => $r) {
                            $pX = $x / $r + $shift;
                            $pY = $y / $r + $shift;

                            $z *= Math::lerp(
                                Math::lerp(0.8, 0, $r / $maxScale),
                                1,
                                self::perlin(
                                    $pX,
                                    $pY,
                                    count($scales) > 1 && $k !== count($scales) - 1 ? 1 : $seed
                                ) * 0.5 + 0.5
                            );
                        }
                    }

                    assert($z >= 0);

                    if ($z > 0) {
                        $z *= cos((1.1 / $radius) * M_PI_2 * $distY);
                        $z *= cos((1.1 / $radius) * M_PI_2 * $distX);
                    }

                    $pixels[$y * $width + $x] = $z;
                }
            }

            $cache[$cacheKey] = $pixels;
        }

        return $cache[$cacheKey];
    }

    /**
     * @see https://en.wikipedia.org/wiki/Perlin_noise
     * @param float $x
     * @param float $y
     * @param int   $seed
     * @return float
     */
    private static function perlin(float $x, float $y, int $seed): float
    {
        static $cache = [];

        $cacheKey = round($x, 2) . '/' . round($y, 2) . '/' . $seed;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $x0 = (int) floor($x);
        $x1 = $x0 + 1;
        $y0 = (int) floor($y);
        $y1 = $y0 + 1;

        $sx = $x - $x0;
        $sy = $y - $y0;

        $n0 = self::dotGridGradient($x0, $y0, $x, $y, $seed);
        $n1 = self::dotGridGradient($x1, $y0, $x, $y, $seed);
        $ix0 = Math::lerp($n0, $n1, $sx);

        $n0 = self::dotGridGradient($x0, $y1, $x, $y, $seed);
        $n1 = self::dotGridGradient($x1, $y1, $x, $y, $seed);
        $ix1 = Math::lerp($n0, $n1, $sx);

        $value = Math::lerp($ix0, $ix1, $sy);

        $cache[$cacheKey] = $value;

        return $value;
    }

    private static function dotGridGradient(int $ix, int $iy, float $x, float $y, int $seed): float
    {
        static $gradientCache = [];

        $gradientCacheKey = $ix . ' ' . $iy . ' ' . $seed;
        if (isset($gradientCache[$gradientCacheKey])) {
            $gradient = $gradientCache[$gradientCacheKey];
        } else {
            $gradient = self::randomGradient($ix, $iy, $seed);
            $gradientCache[$gradientCacheKey] = $gradient;
        }

        $dx = $x - $ix;
        $dy = $y - $iy;

        return $dx * $gradient[0] + $dy * $gradient[1];
    }

    private static function randomGradient(int $ix, int $iy, int $seed): array
    {
        $random = 2 * M_PI * (
            (RandomUtils::generateIntegerSeedFromData([$ix, $iy, $seed]) & 0xffffffff)
                / 0xffffffff
        );

        return [
            cos($random), // x
            sin($random), // y
        ];
    }
}
