<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class PhpRenderer implements RendererInterface
{
    private int $width;

    private int $height;

    private int $pixelCount;

    /**
     * @var array<int>
     */
    private array $currentFrameBuffer;

    /**
     * @var array<int>
     */
    private array $previousFrameBuffer;

    /**
     * @var array<int>
     */
    private array $persistenceBuffer;

    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->pixelCount = $this->width * $this->height;

        $this->reset();
    }

    public function reset(): void
    {
        $this->currentFrameBuffer = array_fill(0, $this->pixelCount, -1);
        $this->previousFrameBuffer = array_fill(0, $this->pixelCount, -1);
        $this->persistenceBuffer = array_fill(0, $this->pixelCount, 0);
    }

    public function clear(int $color): void
    {
        $pixelCount = $this->pixelCount;
        for ($i = 0; $i < $pixelCount; $i++) {
            $this->currentFrameBuffer[$i] = $color;
        }
    }

    public function drawBitmap(
        Bitmap $bitmap,
        int    $x,
        int    $y,
        int    $globalAlpha = 255,
        float  $brightness = 1,
        ?int   $blendingColor = null,
        bool   $persisted = false,
        ?int   $globalPersistedColor = null,
        array  $horizontalBackgroundDistortionOffsets = [],
    ): void {
        if ($globalAlpha === 0) {
            return;
        }

        $width = $this->width;
        $height = $this->height;
        $bitmapWidth = $bitmap->getWidth();
        $bitmapHeight = $bitmap->getHeight();
        $bitmapPixels = $bitmap->getPixels();

        for ($i = 0; $i < $bitmapHeight; $i++) {
            $pxPosY = $y + $i;

            if (
                $pxPosY < 0 || $pxPosY >= $height
            ) {
                continue;
            }

            $horizontalBackgroundDistortionOffset = $horizontalBackgroundDistortionOffsets[$i] ?? 0;

            for ($j = 0; $j < $bitmapWidth; $j++) {
                $pxPosX = $x + $j;

                if (
                    $pxPosX < 0 || $pxPosX >= $width
                ) {
                    continue;
                }

                $color = $bitmapPixels[$i * $bitmapWidth + $j];

                if ($color < 0) {
                    continue;
                }

                $alpha = ($color >> 24) & 0xff;
                if ($alpha === 0) {
                    continue;
                }

                $persistedColor = $globalPersistedColor ?? $color;

                $pxIndex = $pxPosY * $width + $pxPosX;

                if (
                    $alpha < 255
                    || $globalAlpha < 255
                    || $brightness !== 1
                    || $blendingColor !== null
                ) {
                    $colorR = ($color >> 16) & 0xff;
                    $colorG = ($color >> 8) & 0xff;
                    $colorB = $color & 0xff;

                    $blendingColorA = 0;
                    if ($blendingColor !== null) {
                        $blendingColorA = ($blendingColor >> 24) & 0xff;
                    }

                    if ($blendingColor !== null && $blendingColorA > 0) {
                        $blendingColorR = ($blendingColor >> 16) & 0xff;
                        $blendingColorG = ($blendingColor >> 8) & 0xff;
                        $blendingColorB = $blendingColor & 0xff;

                        $colorR = (int) (
                            $blendingColorR * ($blendingColorA / 255.0)
                            + $colorR * (1 - ($blendingColorA / 255.0))
                        );

                        $colorG = (int) (
                            $blendingColorG * ($blendingColorA / 255.0)
                            + $colorG * (1 - ($blendingColorA / 255.0))
                        );

                        $colorB = (int) (
                            $blendingColorB * ($blendingColorA / 255.0)
                            + $colorB * (1 - ($blendingColorA / 255.0))
                        );
                    }

                    $colorR = (int) ($colorR * $brightness);
                    $colorG = (int) ($colorG * $brightness);
                    $colorB = (int) ($colorB * $brightness);

                    $combinedAlpha = 255;
                    if ($globalAlpha < 255 || $alpha < 255) {
                        $combinedAlpha = (int) (255 * ($globalAlpha / 255.0) * ($alpha / 255.0));
                    }

                    if ($persisted) {
                        if ($globalPersistedColor === null) {
                            $persistedColor =
                                ($combinedAlpha << 24) |
                                (($colorR & 0xff) << 16) |
                                (($colorG & 0xff) << 8) |
                                ($colorB & 0xff)
                            ;
                        }

                        if ($brightness !== 1) {
                            $persistedColor =
                                ((($persistedColor >> 24) & 0xff) << 24) |
                                ((((int) ((($persistedColor >> 16) & 0xff) * $brightness) & 0xff)) << 16) |
                                ((((int) ((($persistedColor >> 8) & 0xff) * $brightness) & 0xff)) << 8) |
                                ((((int) ((($persistedColor >> 0) & 0xff) * $brightness) & 0xff)) << 0)
                            ;
                        }
                    }

                    if ($combinedAlpha < 255) {
                        $backgroundColor = $this->currentFrameBuffer[
                        $pxIndex + $horizontalBackgroundDistortionOffset
                        ] ?? 0;

                        $backgroundColorR = ($backgroundColor >> 16) & 0xff;
                        $backgroundColorG = ($backgroundColor >> 8) & 0xff;
                        $backgroundColorB = $backgroundColor & 0xff;

                        $colorR = (int) (
                            $colorR * ($combinedAlpha / 255.0)
                            + $backgroundColorR * (1 - ($combinedAlpha / 255.0))
                        );

                        $colorG = (int) (
                            $colorG * ($combinedAlpha / 255.0)
                            + $backgroundColorG * (1 - ($combinedAlpha / 255.0))
                        );

                        $colorB = (int) (
                            $colorB * ($combinedAlpha / 255.0)
                            + $backgroundColorB * (1 - ($combinedAlpha / 255.0))
                        );
                    }

                    $color =
                        (255 << 24) |
                        (($colorR & 0xff) << 16) |
                        (($colorG & 0xff) << 8) |
                        ($colorB & 0xff)
                    ;
                }

                $this->currentFrameBuffer[$pxIndex] = $color;
                if ($persisted && $alpha > 1) {
                    $currentPersistedColor = $this->persistenceBuffer[$pxIndex];
                    $currentPersistedColorA = ($currentPersistedColor >> 24) & 0xff;
                    if ($currentPersistedColorA <= 2) {
                        $this->persistenceBuffer[$pxIndex] = $persistedColor;
                    } else {
                        $currentPersistedColorR = ($currentPersistedColor >> 16) & 0xff;
                        $currentPersistedColorG = ($currentPersistedColor >> 8) & 0xff;
                        $currentPersistedColorB = $currentPersistedColor & 0xff;
                        $persistedColorA = ($persistedColor >> 24) & 0xff;
                        $persistedColorR = ($persistedColor >> 16) & 0xff;
                        $persistedColorG = ($persistedColor >> 8) & 0xff;
                        $persistedColorB = $persistedColor & 0xff;

                        $blendingRatio = $persistedColorA / ($persistedColorA + $currentPersistedColorA);

                        $persistedColorA = $persistedColorA > $currentPersistedColorA ? $persistedColorA : $currentPersistedColorA;

                        $persistedColorR = (int) (
                            $persistedColorR * $blendingRatio
                                + $currentPersistedColorR * (1 - $blendingRatio)
                        );

                        $persistedColorG = (int) (
                            $persistedColorG * $blendingRatio
                            + $currentPersistedColorG * (1 - $blendingRatio)
                        );

                        $persistedColorB = (int) (
                            $persistedColorB * $blendingRatio
                            + $currentPersistedColorB * (1 - $blendingRatio)
                        );

                        $this->persistenceBuffer[$pxIndex] =
                            (($persistedColorA & 0xff) << 24) |
                            (($persistedColorR & 0xff) << 16) |
                            (($persistedColorG & 0xff) << 8) |
                            ($persistedColorB & 0xff)
                        ;
                    }
                }
            }
        }
    }

    public function drawRect(AABox $rect, int $color): void
    {
        $width = $this->width;
        $height = $this->height;

        $x = Math::roundToInt($rect->getPos()->getX());
        $y = Math::roundToInt($rect->getPos()->getY());
        $boxWidth = $rect->getSize()->getWidth();
        $boxHeight = $rect->getSize()->getHeight();

        for ($i = 0; $i < $boxHeight; $i++) {
            $pxPosY = $y + $i;

            if (
                $pxPosY < 0 || $pxPosY >= $height
            ) {
                continue;
            }

            for ($j = 0; $j < $boxWidth; $j++) {
                $pxPosX = $x + $j;

                if (
                    $pxPosX < 0 || $pxPosX >= $width
                ) {
                    continue;
                }

                if (
                    $i > 0 && $i < $boxHeight - 1 &&
                    $j > 0 && $j < $boxWidth - 1
                ) {
                    continue;
                }

                $pxIndex = $pxPosY * $width + $pxPosX;
                $this->currentFrameBuffer[$pxIndex] = $color;
            }
        }
    }

    function update(
        bool $persistenceEffectsEnabled,
        int $persistenceAlphaDecrease,
        int $colorReductionFactor,
        int $lowResolutionMode,
    ): int {
        $updatedCharacterCount = 0;

        $width = $this->width;
        $height = $this->height;

        $lastUpperColor = 0;
        $lastLowerColor = 0;
        for ($i = 0; $i < $height; $i += 2) {
            $lastPxCol = $width;
            for ($j = 0; $j < $width; $j++) {
                if ($lowResolutionMode === 2 && $j % 2 !== 0) {
                    continue;
                }

                $upperPxIndex = $i * $width + $j;
                $lowerPxIndex = ($i + 1) * $width + $j;

                $upperColor = $this->currentFrameBuffer[$upperPxIndex];
                $lowerColor = $this->currentFrameBuffer[$lowerPxIndex];

                $persistedColor = $this->persistenceBuffer[$upperPxIndex];
                $persistedColorA = ($persistedColor >> 24) & 0xff;

                if (!$persistenceEffectsEnabled && $persistedColorA > 0) {
                    $this->persistenceBuffer[$upperPxIndex] = 0;
                }

                if ($persistenceEffectsEnabled && $persistedColorA > 0) {
                    $persistedColorR = ($persistedColor >> 16) & 0xff;
                    $persistedColorG = ($persistedColor >> 8) & 0xff;
                    $persistedColorB = $persistedColor & 0xff;

                    $colorR = ($upperColor >> 16) & 0xff;
                    $colorG = ($upperColor >> 8) & 0xff;
                    $colorB = $upperColor & 0xff;

                    $colorR = (int) (
                        $colorR * (1 - ($persistedColorA / 255.0))
                        + $persistedColorR * ($persistedColorA / 255.0)
                    );

                    $colorG = (int) (
                        $colorG * (1 - ($persistedColorA / 255.0))
                        + $persistedColorG * ($persistedColorA / 255.0)
                    );

                    $colorB = (int) (
                        $colorB * (1 - ($persistedColorA / 255.0))
                        + $persistedColorB * ($persistedColorA / 255.0)
                    );

                    $upperColor =
                        (255 << 24) |
                        (($colorR & 0xff) << 16) |
                        (($colorG & 0xff) << 8) |
                        ($colorB & 0xff)
                    ;

                    $persistedColorA -= $persistenceAlphaDecrease;
                    if ($persistedColorA < 0) {
                        $persistedColorA = 0;
                    }

                    $this->persistenceBuffer[$upperPxIndex] =
                        (($persistedColorA & 0xff) << 24) |
                        (($persistedColorR & 0xff) << 16) |
                        (($persistedColorG & 0xff) << 8) |
                        ($persistedColorB & 0xff)
                    ;

                    $this->currentFrameBuffer[$upperPxIndex] = $upperColor;
                }

                $persistedColor = $this->persistenceBuffer[$lowerPxIndex];
                $persistedColorA = ($persistedColor >> 24) & 0xff;

                if (!$persistenceEffectsEnabled && $persistedColorA > 0) {
                    $this->persistenceBuffer[$lowerPxIndex] = 0;
                }

                if ($persistenceEffectsEnabled && $persistedColorA > 0) {
                    $persistedColorR = ($persistedColor >> 16) & 0xff;
                    $persistedColorG = ($persistedColor >> 8) & 0xff;
                    $persistedColorB = $persistedColor & 0xff;

                    $colorR = ($lowerColor >> 16) & 0xff;
                    $colorG = ($lowerColor >> 8) & 0xff;
                    $colorB = $lowerColor & 0xff;

                    $colorR = (int) (
                        $colorR * (1 - ($persistedColorA / 255.0))
                        + $persistedColorR * ($persistedColorA / 255.0)
                    );

                    $colorG = (int) (
                        $colorG * (1 - ($persistedColorA / 255.0))
                        + $persistedColorG * ($persistedColorA / 255.0)
                    );

                    $colorB = (int) (
                        $colorB * (1 - ($persistedColorA / 255.0))
                        + $persistedColorB * ($persistedColorA / 255.0)
                    );

                    $lowerColor =
                        (255 << 24) |
                        (($colorR & 0xff) << 16) |
                        (($colorG & 0xff) << 8) |
                        ($colorB & 0xff)
                    ;

                    $persistedColorA -= $persistenceAlphaDecrease;
                    if ($persistedColorA < 0) {
                        $persistedColorA = 0;
                    }

                    $this->persistenceBuffer[$lowerPxIndex] =
                        (($persistedColorA & 0xff) << 24) |
                        (($persistedColorR & 0xff) << 16) |
                        (($persistedColorG & 0xff) << 8) |
                        ($persistedColorB & 0xff)
                    ;

                    $this->currentFrameBuffer[$lowerPxIndex] = $lowerColor;
                }

                if ($colorReductionFactor > 1) {
                    if (($upperColor & 0xffffff) !== 0) {
                        $colorR = ($upperColor >> 16) & 0xff;
                        $colorG = ($upperColor >> 8) & 0xff;
                        $colorB = $upperColor & 0xff;

                        $brightness = ($colorR + $colorG + $colorB) / (255.0 * 3);

                        $colorR = (int) (
                            ($colorR / $colorReductionFactor + 0.95 + $brightness)
                            ) * $colorReductionFactor
                        ;
                        $colorR = $colorR > 255 ? 255 : $colorR;

                        $colorG = (int) (
                            ($colorG / $colorReductionFactor + 0.95 + $brightness)
                            ) * $colorReductionFactor
                        ;
                        $colorG = $colorG > 255 ? 255 : $colorG;

                        $colorB = (int) (
                            ($colorB / $colorReductionFactor + 0.95 + $brightness)
                            ) * $colorReductionFactor
                        ;
                        $colorB = $colorB > 255 ? 255 : $colorB;

                        $upperColor =
                            (255 << 24) |
                            (($colorR & 0xff) << 16) |
                            (($colorG & 0xff) << 8) |
                            ($colorB & 0xff)
                        ;

                        $this->currentFrameBuffer[$upperPxIndex] = $upperColor;
                    }

                    if (($lowerColor & 0xffffff) !== 0) {
                        $colorR = ($lowerColor >> 16) & 0xff;
                        $colorG = ($lowerColor >> 8) & 0xff;
                        $colorB = $lowerColor & 0xff;

                        $brightness = ($colorR + $colorG + $colorB) / (255.0 * 3);

                        $colorR = (int) (
                                ($colorR / $colorReductionFactor + 0.95 + $brightness)
                            ) * $colorReductionFactor
                        ;
                        $colorR = $colorR > 255 ? 255 : $colorR;

                        $colorG = (int) (
                            ($colorG / $colorReductionFactor + 0.95 + $brightness)
                            ) * $colorReductionFactor
                        ;
                        $colorG = $colorG > 255 ? 255 : $colorG;

                        $colorB = (int) (
                            ($colorB / $colorReductionFactor + 0.95 + $brightness)
                            ) * $colorReductionFactor
                        ;
                        $colorB = $colorB > 255 ? 255 : $colorB;

                        $lowerColor =
                            (255 << 24) |
                            (($colorR & 0xff) << 16) |
                            (($colorG & 0xff) << 8) |
                            ($colorB & 0xff)
                        ;

                        $this->currentFrameBuffer[$lowerPxIndex] = $lowerColor;
                    }
                }

                if ($lowResolutionMode) {
                    $lowerColor = $this->currentFrameBuffer[$upperPxIndex];
                    $this->currentFrameBuffer[$lowerPxIndex] = $lowerColor;
                    $this->persistenceBuffer[$lowerPxIndex] = $this->persistenceBuffer[$upperPxIndex];

                    if ($lowResolutionMode === 2) {
                        $this->currentFrameBuffer[$upperPxIndex + 1] = $upperColor;
                        $this->currentFrameBuffer[$lowerPxIndex + 1] = $lowerColor;
                        $this->persistenceBuffer[$upperPxIndex + 1] = $this->persistenceBuffer[$upperPxIndex];
                        $this->persistenceBuffer[$lowerPxIndex + 1] = $this->persistenceBuffer[$lowerPxIndex];
                    }
                }

                $prevUpperColor = $this->previousFrameBuffer[$upperPxIndex];
                $prevLowerColor = $this->previousFrameBuffer[$lowerPxIndex];

                if (
                    $upperColor === $prevUpperColor &&
                    $lowerColor === $prevLowerColor
                ) {
                    if ($lowResolutionMode !== 2) {
                        continue;
                    }

                    if (
                        $this->previousFrameBuffer[$upperPxIndex + 1] === $this->currentFrameBuffer[$upperPxIndex + 1] &&
                        $this->previousFrameBuffer[$lowerPxIndex + 1] === $this->currentFrameBuffer[$lowerPxIndex + 1]
                    ) {
                        continue;
                    }
                }

                $updatedCharacterCount++;

                if (
                    $j <= 1 || $lastPxCol !== $j - 1
                ) {
                    echo "\033", '[', $i / 2, ';', $j, 'H';
                }

                if ($lastUpperColor !== $upperColor || $lastLowerColor !== $lowerColor) {
                    $ansiFgColor = '38;2;' . (($upperColor >> 16) & 0xff) . ';' . (($upperColor >> 8) & 0xff) . ';' . ($upperColor & 0xff);
                    $ansiBgColor = '48;2;' . (($lowerColor >> 16) & 0xff) . ';' . (($lowerColor >> 8) & 0xff) . ';' . ($lowerColor & 0xff);

                    echo "\033", '[', $ansiFgColor, ';', $ansiBgColor, 'm';

                    $lastUpperColor = $upperColor;
                    $lastLowerColor = $lowerColor;
                }

                echo '▀';

                $lastPxCol = $j;
                if ($lowResolutionMode === 2) {
                    $updatedCharacterCount++;

                    echo '▀';

                    $lastPxCol = $j + 1;
                }
            }
        }

        $this->previousFrameBuffer = $this->currentFrameBuffer;

        return $updatedCharacterCount;
    }
}
