<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class SpriteRenderingParameters
{
    private int $globalAlpha = 255;

    private float $brightness = 1;

    private ?int $globalBlendingColor = null;

    private array $verticalBlendingColors = [];

    private bool $persisted = false;

    private ?int $persistedColor = null;

    private array $horizontalDistortionOffsets = [];

    private array $horizontalBackgroundDistortionOffsets = [];

    public function reset(): void
    {
        $this->globalAlpha = 255;
        $this->brightness = 1;
        $this->globalBlendingColor = null;
        $this->verticalBlendingColors = [];
        $this->persisted = false;
        $this->persistedColor = null;
        $this->horizontalDistortionOffsets = [];
        $this->horizontalBackgroundDistortionOffsets = [];
    }

    /**
     * @return int
     */
    public function getGlobalAlpha(): int
    {
        return $this->globalAlpha;
    }

    /**
     * @param int $globalAlpha
     */
    public function setGlobalAlpha(int $globalAlpha): void
    {
        if ($globalAlpha < 0 || $globalAlpha > 255) {
            throw new \RuntimeException('Invalid alpha value: ' . $globalAlpha);
        }

        $this->globalAlpha = $globalAlpha;
    }

    /**
     * @return float
     */
    public function getBrightness(): float
    {
        return $this->brightness;
    }

    /**
     * @param float $brightness
     */
    public function setBrightness(float $brightness): void
    {
        $this->brightness = $brightness;
    }

    /**
     * @return int|null
     */
    public function getGlobalBlendingColor(): ?int
    {
        return $this->globalBlendingColor;
    }

    /**
     * @param int|null $globalBlendingColor
     */
    public function setGlobalBlendingColor(?int $globalBlendingColor): void
    {
        $this->globalBlendingColor = $globalBlendingColor;
    }

    public function getVerticalBlendingColors(): array
    {
        return $this->verticalBlendingColors;
    }

    public function setVerticalBlendingColors(array $verticalBlendingColors): void
    {
        $this->verticalBlendingColors = $verticalBlendingColors;
    }

    /**
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->persisted;
    }

    /**
     * @param bool $persisted
     */
    public function setPersisted(bool $persisted): void
    {
        $this->persisted = $persisted;
    }

    /**
     * @return int|null
     */
    public function getPersistedColor(): ?int
    {
        return $this->persistedColor;
    }

    /**
     * @param int|null $persistedColor
     */
    public function setPersistedColor(?int $persistedColor): void
    {
        $this->persistedColor = $persistedColor;
    }

    public function getHorizontalDistortionOffsets(): array
    {
        return $this->horizontalDistortionOffsets;
    }

    public function setHorizontalDistortionOffsets(array $horizontalDistortionOffsets): void
    {
        $this->horizontalDistortionOffsets = $horizontalDistortionOffsets;
    }

    public function getHorizontalBackgroundDistortionOffsets(): array
    {
        return $this->horizontalBackgroundDistortionOffsets;
    }

    public function setHorizontalBackgroundDistortionOffsets(array $horizontalBackgroundDistortionOffsets): void
    {
        $this->horizontalBackgroundDistortionOffsets = $horizontalBackgroundDistortionOffsets;
    }
}