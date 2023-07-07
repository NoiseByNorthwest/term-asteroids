<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class SpriteFrame
{
    private SpriteAnimation $animation;

    private float $duration;

    private Bitmap $bitmap;

    public function __construct(SpriteAnimation $animation, float $duration, Bitmap $bitmap)
    {
        if (
            $bitmap->getWidth() !== $animation->getSprite()->getWidth() ||
            $bitmap->getHeight() !== $animation->getSprite()->getHeight()
        ) {
            throw new \RuntimeException(sprintf(
                'Cannot add a %dx%d frame to a %dx%d sprite',
                $bitmap->getWidth(),
                $bitmap->getHeight(),
                $animation->getSprite()->getWidth(),
                $animation->getSprite()->getHeight(),
            ));
        }

        $this->animation = $animation;
        $this->duration = $duration;
        $this->bitmap = $bitmap;
    }

    /**
     * @return SpriteAnimation
     */
    public function getAnimation(): SpriteAnimation
    {
        return $this->animation;
    }

    /**
     * @return float
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getBitmap(): Bitmap
    {
        return $this->bitmap;
    }
}