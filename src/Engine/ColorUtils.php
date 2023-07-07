<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

abstract class ColorUtils
{
    public static function createColor($v, ?int $alpha = null): int
    {
        if (is_string($v)) {
            return self::htmlHexStringToColor($v, $alpha ?? 255);
        }

        if (is_int($v)) {
            if ($v < 0) {
                return 0;
            }

            if ($alpha === null) {
                return $v;
            }

            $v = self::colorToRgba($v);
        }

        if (is_array($v)) {
            if (
                array_keys($v) !== [0, 1, 2]
                && array_keys($v) !== [0, 1, 2, 3]
            ) {
                return self::generateRandomColorWithinGradient(
                    array_map(fn ($e) => self::createColor($e), $v)
                );
            }

            if ($alpha !== null) {
                $v[3] = $alpha;
            }

            return self::rgbaToColor(...$v);
        }

        if ($v instanceof Vec3) {
            return self::vec3ToColor($v, $alpha ?? 255);
        }

        throw new \RuntimeException('Unsupported color type: ' . gettype($v));
    }

    public static function htmlHexStringToColor(string $s, int $alpha = 255): int
    {
        assert(str_starts_with($s, '#'));
        assert(strlen($s) === 7);

        $components = array_map(
            fn ($e) => (int) hexdec($e),
            str_split(substr($s, 1), 2)
        );

        $components[] = $alpha;

        return self::rgbaToColor(...$components);
    }

    public static function rgbaToColor(int $r, int $g, int $b, int $alpha = 255): int
    {
        return
            (($alpha & 0xff) << 24) |
            (($r & 0xff) << 16) |
            (($g & 0xff) << 8) |
            ($b & 0xff)
        ;
    }

    public static function vec3ToColor(Vec3 $v, int $alpha = 255): int
    {
        return self::rgbaToColor($v->getR(), $v->getG(), $v->getB(), $alpha);
    }

    public static function colorToVec3(int $color): Vec3
    {
        return new Vec3(...self::colorToRgba($color));
    }

    public static function colorToRgba(int $color): array
    {
        return [
            ($color >> 16) & 0xff,
            ($color >> 8) & 0xff,
            $color & 0xff,
            ($color >> 24) & 0xff,
        ];
    }

    public static function applyEffects(
        int   $color,
        int   $backgroundColor = 0,
        int   $globalAlpha = 255,
        float $brightness = 1,
        ?int  $blendingColor = null
    ): int {
        $alpha = ($color >> 24) & 0xff;
        $color = [
            ($color >> 16) & 0xff,
            ($color >> 8) & 0xff,
            $color & 0xff,
        ];

        $backgroundColor = [
            ($backgroundColor >> 16) & 0xff,
            ($backgroundColor >> 8) & 0xff,
            $backgroundColor & 0xff,
        ];

        if ($blendingColor !== null) {
            $blendingColor = [
                ($blendingColor >> 16) & 0xff,
                ($blendingColor >> 8) & 0xff,
                $blendingColor & 0xff,
                ($blendingColor >> 24) & 0xff,
            ];
        }

        $combinedAlpha = 255;
        if ($globalAlpha < 255 || $alpha < 255) {
            $combinedAlpha = (int) (255 * ($globalAlpha / 255) * ($alpha / 255));
        }

        for ($i = 0; $i < 3; $i++) {
            if ($blendingColor !== null && $blendingColor[3] > 0) {
                $color[$i] = (int) (
                    $blendingColor[$i] * ($blendingColor[3] / 255.0)
                    + $color[$i] * (1 - ($blendingColor[3] / 255.0))
                );
            }

            if ($combinedAlpha < 255) {
                $color[$i] = (int) (
                    $color[$i] * ($combinedAlpha / 255.0)
                    + $backgroundColor[$i] * (1 - ($combinedAlpha / 255.0))
                );
            }

            $color[$i] = (int) ($color[$i] * $brightness);
        }

        return
            (255 << 24) |
            (($color[0] & 0xff) << 16) |
            (($color[1] & 0xff) << 8) |
            ($color[2] & 0xff)
        ;
    }

    public static function generateRandomColorWithinGradient(array $gradient): int
    {
        return self::createColorWithinGradient($gradient, RandomUtils::getRandomFloat(0, 1));
    }

    public static function createColorWithinGradient(array $gradient, float $pos): int
    {
        assert(0 <= $pos && $pos <= 1);

        assert((float) array_key_first($gradient) === 0.0);
        assert((float) array_key_last($gradient) === 1.0);

        $gradient = array_map(
            fn ($e) => is_int($e) ? $e : self::createColor($e),
            $gradient
        );

        $keys = array_keys($gradient);
        foreach ($keys as $i => $k) {
            $k = (float) $k;
            assert($pos >= $k);

            $next = $keys[$i + 1] ?? null;
            assert($next !== null);

            $next = (float) $next;

            if ($pos >= $next) {
                if ($pos === $next) {
                   return $gradient[$keys[$i + 1]];
                }

                continue;
            }

            return self::createColorWithinRange(
                $gradient[$keys[$i]],
                $gradient[$keys[$i + 1]],
                ($pos - $k) / ($next - $k)
            );
        }

        assert($pos === 1.0);

        return $gradient[array_key_last($gradient)];
    }

    public static function createColorWithinRange(int $a, int $b, float $dist): int
    {
        $a = self::colorToRgba($a);
        $b = self::colorToRgba($b);

        foreach ($a as $k => $v) {
            $a[$k] = (int) Math::lerp($v, $b[$k], $dist);
        }

        return self::rgbaToColor(...$a);
    }
}
