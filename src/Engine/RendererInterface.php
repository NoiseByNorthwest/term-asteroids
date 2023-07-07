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
        ?int   $blendingColor = null,
        bool   $persisted = false,
        ?int   $globalPersistedColor = null,
        array  $horizontalBackgroundDistortionOffsets = [],
    ): void;

    public function drawRect(AABox $rect, int $color): void;

    function update(
        bool $persistenceEffectsEnabled,
        int $persistenceAlphaDecrease,
        int $colorReductionFactor,
        int $lowResolutionMode,
    ): int;
}