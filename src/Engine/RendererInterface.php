<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

interface RendererInterface
{
    public function reset(): void;

    public function clear(int $color): void;

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
    ): void;

    public function drawRect(AABox $rect, int $color): void;

    public function getDrawnBitmapPixelCount(): int;

    function update(
        bool $trueColorModeEnabled,
        bool $persistenceEffectsEnabled,
        int $persistenceAlphaDecrease,
        int $removedColorDepthBits,
        int $lowResolutionMode,
    ): int;
}
