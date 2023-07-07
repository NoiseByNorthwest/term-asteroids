<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class SpriteRenderingParameters
{
    private int $globalAlpha = 255;

    private float $brightness = 1;

    private ?int $blendingColor = null;

    private bool $persisted = false;

    private ?int $persistedColor = null;

    private array $horizontalBackgroundDistortionOffsets = [];

    public function reset(): void
    {
        $this->globalAlpha = 255;
        $this->brightness = 1;
        $this->blendingColor = null;
        $this->persisted = false;
        $this->persistedColor = null;
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
    public function getBlendingColor(): ?int
    {
        return $this->blendingColor;
    }

    /**
     * @param int|null $blendingColor
     */
    public function setBlendingColor(?int $blendingColor): void
    {
        $this->blendingColor = $blendingColor;
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

    public function getHorizontalBackgroundDistortionOffsets(): array
    {
        return $this->horizontalBackgroundDistortionOffsets;
    }

    public function setHorizontalBackgroundDistortionOffsets(array $horizontalBackgroundDistortionOffsets): void
    {
        $this->horizontalBackgroundDistortionOffsets = $horizontalBackgroundDistortionOffsets;
    }
}