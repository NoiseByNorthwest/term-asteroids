<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Bitmap
{
    private int $width;

    private int $height;

    /**
     * @var int[]
     */
    private array $pixels;

    public static function generateVerticalGradient(int $width, int $height, array $colorGradient, bool $loopBack = false): self
    {
        $pixels = array_fill(0, $width * $height, 0);
        for ($i = 0; $i < $height; $i++) {
            $coefficient = $i / ($height - 1);
            if ($loopBack) {
                if ($coefficient > 0.5) {
                    $coefficient = 1 - $coefficient;
                }

                $coefficient *= 2;
            }

            $color = ColorUtils::createColorWithinGradient($colorGradient, $coefficient);
            for ($j = 0; $j < $width; $j++) {
                $pixels[$i * $width + $j] = $color;
            }
        }

        return new self(
            $width,
            $height,
            $pixels
        );
    }

    /**
     * @param Bitmap $bitmap
     * @param int $frameCount
     * @return array<self>
     */
    public static function generateCenteredRotationAnimation(self $bitmap, int $frameCount): array
    {
        assert($frameCount > 0);

        $bitmaps[] = $bitmap;

        $angleStep = (2 * M_PI) / $frameCount;
        $i = 1;
        $angle = 0;
        while ($i < $frameCount) {
            $i++;
            $angle += $angleStep;

            $bitmaps[] = $bitmap->withCenteredRotation($angle);
        }

        return $bitmaps;
    }

    /**
     * @param int $width
     * @param int $height
     * @param int[] $pixels
     */
    public function __construct(int $width, int $height, array $pixels)
    {
        assert(count($pixels) > 0);
        assert(count($pixels) === $width * $height);

        $this->width = $width;
        $this->height = $height;
        $this->pixels = $pixels;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return array
     */
    public function getPixels(): array
    {
        return $this->pixels;
    }

    public function withCenteredRotation(float $angle): self
    {
        $newPixels = array_fill(0, $this->width * $this->height, 0);

        $projectionMatrix = Mat3::identity()
            ->translate($this->width / 2, $this->height / 2)
            ->rotate($angle)
            ->translate(-($this->width / 2), -($this->height / 2))
        ;

        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $point = new Vec2($x, $y);
                $projectedPoint = $projectionMatrix->transform($point);

                $pX = Math::roundToInt($projectedPoint->getX());

                if ($pX < 0 || $this->width <= $pX) {
                    continue;
                }

                $pY = Math::roundToInt($projectedPoint->getY());

                if ($pY < 0 || $this->height <= $pY) {
                    continue;
                }

                $newPixels[
                    $x + $y * $this->width
                ] = $this->pixels[
                    $pX + $pY * $this->width
                ];
            }
        }

        return new self(
            $this->width,
            $this->height,
            $newPixels,
        );
    }
}
