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

    private int $drawnBitmapPixelCount = 0;

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

        $this->drawnBitmapPixelCount = 0;
    }

    public function drawBitmap(
        Bitmap $bitmap,
        int    $x,
        int    $y,
        int    $globalAlpha = 255,
        float  $brightness = 1,
        ?int   $globalBlendingColor = null,
        array  $verticalBlendingColors = [],
        bool   $persisted = false,
        ?int   $globalPersistedColor = null,
        array  $horizontalDistortionOffsets = [],
        array  $horizontalBackgroundDistortionOffsets = [],
        float  $ditheringAlphaRatioThreshold = 0,
    ): void {
        if ($globalAlpha === 0) {
            return;
        }

        $width = $this->width;
        $height = $this->height;
        $bitmapWidth = $bitmap->getWidth();
        $bitmapHeight = $bitmap->getHeight();
        $bitmapPixels = $bitmap->getPixels();

        $fullBrightnessReciprocal = 1 / 255.0;

        for ($i = 0; $i < $bitmapHeight; $i++) {
            $pxPosY = $y + $i;

            if (
                $pxPosY < 0 || $pxPosY >= $height
            ) {
                continue;
            }

            $horizontalDistortionOffset = $horizontalDistortionOffsets[$i] ?? 0;
            $horizontalBackgroundDistortionOffset = $horizontalBackgroundDistortionOffsets[$i] ?? 0;

            for ($j = 0; $j < $bitmapWidth; $j++) {
                $pxPosX = $x + $j + $horizontalDistortionOffset;

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

                $blendingColor = $globalBlendingColor;
                $verticalBlendingColor = $verticalBlendingColors[$j] ?? null;
                if ($blendingColor === null) {
                    $blendingColor = $verticalBlendingColor;
                } elseif ($verticalBlendingColor !== null) {
                    $verticalBlendingColorA = ($verticalBlendingColor >> 24) & 0xff;
                    if ($verticalBlendingColorA !== 0) {
                        $blendingColorA = ($blendingColor >> 24) & 0xff;
                        if ($blendingColorA === 0) {
                            $blendingColor = $verticalBlendingColor;
                        } else {
                            $verticalBlendingColorR = ($verticalBlendingColor >> 16) & 0xff;
                            $verticalBlendingColorG = ($verticalBlendingColor >> 8) & 0xff;
                            $verticalBlendingColorB = ($verticalBlendingColor >> 0) & 0xff;
                            $blendingColorR = ($blendingColor >> 16) & 0xff;
                            $blendingColorG = ($blendingColor >> 8) & 0xff;
                            $blendingColorB = ($blendingColor >> 0) & 0xff;

                            $blendingColorAlphaRatio = $blendingColorA / ($blendingColorA + $verticalBlendingColorA);

                            $blendingColorR = (int) (
                                $blendingColorR * $blendingColorAlphaRatio
                                    + $verticalBlendingColorR * (1 - $blendingColorAlphaRatio)
                            );

                            $blendingColorG = (int) (
                                $blendingColorG * $blendingColorAlphaRatio
                                    + $verticalBlendingColorG * (1 - $blendingColorAlphaRatio)
                            );

                            $blendingColorB = (int) (
                                $blendingColorB * $blendingColorAlphaRatio
                                    + $verticalBlendingColorB * (1 - $blendingColorAlphaRatio)
                            );

                            $blendingColorA = (int) (
                                $blendingColorA * $blendingColorAlphaRatio
                                    + $verticalBlendingColorA * (1 - $blendingColorAlphaRatio)
                            );

                            $blendingColor =
                                $blendingColorA << 24
                                | $blendingColorR << 16
                                | $blendingColorG << 8
                                | $blendingColorB
                            ;
                        }
                    }
                }

                $persistedColor = $globalPersistedColor ?? $color;

                $pxIndex = $pxPosY * $width + $pxPosX;

                if (
                    $alpha < 255
                    || $globalAlpha < 255
                    || $brightness !== 1.0
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

                        $blendingColorAlphaRatio = $blendingColorA * $fullBrightnessReciprocal;

                        $colorR = (int) (
                            $blendingColorR * $blendingColorAlphaRatio
                            + $colorR * (1 - $blendingColorAlphaRatio)
                        );

                        $colorG = (int) (
                            $blendingColorG * $blendingColorAlphaRatio
                            + $colorG * (1 - $blendingColorAlphaRatio)
                        );

                        $colorB = (int) (
                            $blendingColorB * $blendingColorAlphaRatio
                            + $colorB * (1 - $blendingColorAlphaRatio)
                        );
                    }

                    $colorR = (int) ($colorR * $brightness);
                    $colorG = (int) ($colorG * $brightness);
                    $colorB = (int) ($colorB * $brightness);

                    $combinedAlpha = 255;
                    if ($globalAlpha < 255 || $alpha < 255) {
                        $combinedAlpha = (int) (255 * ($globalAlpha * $fullBrightnessReciprocal) * ($alpha * $fullBrightnessReciprocal));
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

                        if ($brightness !== 1.0) {
                            $persistedColor =
                                ((($persistedColor >> 24) & 0xff) << 24) |
                                ((((int) ((($persistedColor >> 16) & 0xff) * $brightness) & 0xff)) << 16) |
                                ((((int) ((($persistedColor >> 8) & 0xff) * $brightness) & 0xff)) << 8) |
                                ((((int) ((($persistedColor >> 0) & 0xff) * $brightness) & 0xff)) << 0)
                            ;
                        }
                    }

                    if ($combinedAlpha < 255) {
                        $combinedAlphaRatio = $combinedAlpha * $fullBrightnessReciprocal;

                        if ($ditheringAlphaRatioThreshold !== 0.0 && $combinedAlphaRatio <= $ditheringAlphaRatioThreshold) {
                            $rn = (214013 * $pxIndex + 2531011) & 0xffff;
                            $combinedAlphaRatio = ($combinedAlphaRatio * 0xffff) < $rn ? 0.0 : 1.0;

                            if ($combinedAlphaRatio === 0.0 && $horizontalBackgroundDistortionOffset === 0) {
                                continue;
                            }
                        }

                        if ($combinedAlphaRatio !== 1.0) {
                            $backgroundColor = $this->currentFrameBuffer[
                                $pxIndex + $horizontalBackgroundDistortionOffset
                            ] ?? 0;

                            $backgroundColorR = ($backgroundColor >> 16) & 0xff;
                            $backgroundColorG = ($backgroundColor >> 8) & 0xff;
                            $backgroundColorB = $backgroundColor & 0xff;

                            if ($combinedAlphaRatio === 0.0) {
                                $colorR = $backgroundColorR;
                                $colorG = $backgroundColorG;
                                $colorB = $backgroundColorB;
                            } else {
                                $colorR = (int)(
                                    $colorR * $combinedAlphaRatio
                                    + $backgroundColorR * (1 - $combinedAlphaRatio)
                                );

                                $colorG = (int)(
                                    $colorG * $combinedAlphaRatio
                                    + $backgroundColorG * (1 - $combinedAlphaRatio)
                                );

                                $colorB = (int)(
                                    $colorB * $combinedAlphaRatio
                                    + $backgroundColorB * (1 - $combinedAlphaRatio)
                                );
                            }
                        }
                    }

                    $color =
                        (255 << 24) |
                        (($colorR & 0xff) << 16) |
                        (($colorG & 0xff) << 8) |
                        ($colorB & 0xff)
                    ;
                }

                $this->currentFrameBuffer[$pxIndex] = $color;
                $this->drawnBitmapPixelCount++;
                if ($persisted && $alpha > 1) {
                    $currentPersistedColor = $this->persistenceBuffer[$pxIndex];
                    $currentPersistedColorA = ($currentPersistedColor >> 24) & 0xff;
                    if ($currentPersistedColorA <= 2) {
                        $this->persistenceBuffer[$pxIndex] = $persistedColor;
                    } else {
                        $persistedColorA = ($persistedColor >> 24) & 0xff;
                        $blendingRatio = $persistedColorA / ($persistedColorA + $currentPersistedColorA);

                        if ($ditheringAlphaRatioThreshold !== 0.0 && $blendingRatio <= $ditheringAlphaRatioThreshold) {
                            $rn = (214013 * $pxIndex + 2531011) & 0xffff;
                            $blendingRatio = ($blendingRatio * 0xffff) < $rn ? 0.0 : 1.0;
                        }

                        if ($blendingRatio === 1.0) {
                            $this->persistenceBuffer[$pxIndex] = $persistedColor;
                        } else if ($blendingRatio !== 0.0) {
                            $currentPersistedColorR = ($currentPersistedColor >> 16) & 0xff;
                            $currentPersistedColorG = ($currentPersistedColor >> 8) & 0xff;
                            $currentPersistedColorB = $currentPersistedColor & 0xff;
                            $persistedColorR = ($persistedColor >> 16) & 0xff;
                            $persistedColorG = ($persistedColor >> 8) & 0xff;
                            $persistedColorB = $persistedColor & 0xff;
                            $persistedColorA = $persistedColorA > $currentPersistedColorA ? $persistedColorA : $currentPersistedColorA;

                            $persistedColorR = (int)(
                                $persistedColorR * $blendingRatio
                                + $currentPersistedColorR * (1 - $blendingRatio)
                            );

                            $persistedColorG = (int)(
                                $persistedColorG * $blendingRatio
                                + $currentPersistedColorG * (1 - $blendingRatio)
                            );

                            $persistedColorB = (int)(
                                $persistedColorB * $blendingRatio
                                + $currentPersistedColorB * (1 - $blendingRatio)
                            );

                            $this->persistenceBuffer[$pxIndex] =
                                (($persistedColorA & 0xff) << 24) |
                                (($persistedColorR & 0xff) << 16) |
                                (($persistedColorG & 0xff) << 8) |
                                ($persistedColorB & 0xff);
                        }
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
                $this->drawnBitmapPixelCount++;
            }
        }
    }

    public function getDrawnBitmapPixelCount(): int
    {
        return $this->drawnBitmapPixelCount;
    }

    function update(
        bool $trueColorModeEnabled,
        bool $persistenceEffectsEnabled,
        int $persistenceAlphaDecrease,
        int $removedColorDepthBits,
        int $lowResolutionMode,
    ): int {
        $updatedCharacterCount = 0;

        $fullBrightnessReciprocal = 1 / 255.0;

        $colorReductionCorrectionMask = $removedColorDepthBits !== 0 ? 1 << ($removedColorDepthBits - 1) : 0;

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

                $upperPxIndex = 0;
                $lowerPxIndex = 0;
                $upperColor = 0;
                $lowerColor = 0;

                for ($k = 0; $k < 2; $k++) {
                    $pxIndex = ($i + $k) * $width + $j;

                    $color = $this->currentFrameBuffer[$pxIndex];
                    $persistedColor = $this->persistenceBuffer[$pxIndex];

                    $persistedColorA = ($persistedColor >> 24) & 0xff;

                    if (!$persistenceEffectsEnabled && $persistedColorA > 0) {
                        $this->persistenceBuffer[$pxIndex] = 0;
                    }

                    if ($persistenceEffectsEnabled && $persistedColorA > 0) {
                        $persistedColorR = ($persistedColor >> 16) & 0xff;
                        $persistedColorG = ($persistedColor >> 8) & 0xff;
                        $persistedColorB = $persistedColor & 0xff;

                        $colorR = ($color >> 16) & 0xff;
                        $colorG = ($color >> 8) & 0xff;
                        $colorB = $color & 0xff;

                        $persistedColorAlphaRatio = $persistedColorA * $fullBrightnessReciprocal;

                        $colorR = (int) (
                            $colorR * (1 - $persistedColorAlphaRatio)
                            + $persistedColorR * $persistedColorAlphaRatio
                        );

                        $colorG = (int) (
                            $colorG * (1 - $persistedColorAlphaRatio)
                            + $persistedColorG * $persistedColorAlphaRatio
                        );

                        $colorB = (int) (
                            $colorB * (1 - $persistedColorAlphaRatio)
                            + $persistedColorB * $persistedColorAlphaRatio
                        );

                        $color =
                            (255 << 24) |
                            (($colorR & 0xff) << 16) |
                            (($colorG & 0xff) << 8) |
                            ($colorB & 0xff)
                        ;

                        $persistedColorA -= $persistenceAlphaDecrease;
                        if ($persistedColorA < 0) {
                            $persistedColorA = 0;
                        }

                        $this->persistenceBuffer[$pxIndex] =
                            (($persistedColorA & 0xff) << 24) |
                            (($persistedColorR & 0xff) << 16) |
                            (($persistedColorG & 0xff) << 8) |
                            ($persistedColorB & 0xff)
                        ;

                        $this->currentFrameBuffer[$pxIndex] = $color;
                    }

                    if ($removedColorDepthBits !== 0 && ($color & 0xffffff) !== 0) {
                        $colorR = ($color >> 16) & 0xff;
                        $colorG = ($color >> 8) & 0xff;
                        $colorB = $color & 0xff;

                        $color =
                            (255 << 24) |
                            (((($colorR >> $removedColorDepthBits) << $removedColorDepthBits) | $colorReductionCorrectionMask) << 16) |
                            (((($colorG >> $removedColorDepthBits) << $removedColorDepthBits) | $colorReductionCorrectionMask) << 8) |
                            ((($colorB >> $removedColorDepthBits) << $removedColorDepthBits) | $colorReductionCorrectionMask)
                        ;

                        $this->currentFrameBuffer[$pxIndex] = $color;
                    }

                    if ($k === 0) {
                        $upperPxIndex = $pxIndex;
                        $upperColor = $color;
                    } else {
                        $lowerPxIndex = $pxIndex;
                        $lowerColor = $color;
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
                    if ($trueColorModeEnabled) {
                        echo
                            "\033",
                            '[38;2;',
                            (($upperColor >> 16) & 0xff), ';', (($upperColor >> 8) & 0xff), ';', ($upperColor & 0xff),
                            ';48;2;',
                            (($lowerColor >> 16) & 0xff), ';', (($lowerColor >> 8) & 0xff), ';', ($lowerColor & 0xff),
                            'm'
                        ;

                        $lastUpperColor = $upperColor;
                        $lastLowerColor = $lowerColor;
                    } else {
                        $brightnessBoost = 0.3;

                        $upperColorTableIdx = 16 +
                            + 36 * (int) round($brightnessBoost + 5 * (($upperColor >> 16) & 0xff) * $fullBrightnessReciprocal)
                            + 6 * (int) round($brightnessBoost + 5 * (($upperColor >> 8) & 0xff) * $fullBrightnessReciprocal)
                            + (int) round($brightnessBoost + 5 * (($upperColor >> 0) & 0xff) * $fullBrightnessReciprocal)
                        ;

                        if ($upperColorTableIdx > 231) {
                            $upperColorTableIdx = 231;
                        }

                        $lowerColorTableIdx = 16
                            + 36 * (int) round($brightnessBoost + 5 * (($lowerColor >> 16) & 0xff) * $fullBrightnessReciprocal)
                            + 6 * (int) round($brightnessBoost + 5 * (($lowerColor >> 8) & 0xff) * $fullBrightnessReciprocal)
                            + (int) round($brightnessBoost + 5 * (($lowerColor >> 0) & 0xff) * $fullBrightnessReciprocal)
                        ;

                        if ($lowerColorTableIdx > 231) {
                            $lowerColorTableIdx = 231;
                        }

                        echo "\033", '[38;5;', $upperColorTableIdx, ';48;5;', $lowerColorTableIdx, 'm';

                        $lastUpperColor = $upperColorTableIdx;
                        $lastLowerColor = $lowerColorTableIdx;
                    }
                }

                echo '▀';

                $lastPxCol = $j;
                if ($lowResolutionMode === 2) {
                    $updatedCharacterCount++;

                    echo '▀';

                    $lastPxCol = $j + 1;
                }

                if ($updatedCharacterCount % 300 === 0) {
                    ob_flush();
                }
            }
        }

        ob_flush();

        $this->previousFrameBuffer = $this->currentFrameBuffer;

        return $updatedCharacterCount;
    }
}
