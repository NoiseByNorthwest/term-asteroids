<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class AABox
{
    private Vec2 $pos;

    private Vec2 $size;

    public function __construct(Vec2 $pos, Vec2 $size)
    {
        $this->pos = $pos;
        $this->size = $size;
    }

    public function copy(): self
    {
        return new self($this->pos->copy(), $this->size->copy());
    }

    /**
     * @return Vec2
     */
    public function getPos(): Vec2
    {
        return $this->pos;
    }

    /**
     * @return Vec2
     */
    public function getSize(): Vec2
    {
        return $this->size;
    }

    public function getLeft(): float
    {
        return $this->pos->getX();
    }

    public function getRight(): float
    {
        return $this->pos->getX() + $this->size->getWidth();
    }

    public function getTop(): float
    {
        return $this->pos->getY();
    }

    public function getBottom(): float
    {
        return $this->pos->getY() + $this->size->getHeight();
    }

    public function getCenter(): Vec2
    {
        return $this->pos->copy()->add(
            $this->size->getWidth() / 2,
            $this->size->getHeight() / 2
        );
    }

    public function getRelativeCenter(): Vec2
    {
        return new Vec2(
            $this->size->getWidth() / 2,
            $this->size->getHeight() / 2
        );
    }

    public function containsPos(Vec2 $pos): bool
    {
        return
            $this->pos->getX() <= $pos->getX() && $pos->getX() <= $this->pos->getX() + $this->size->getWidth() &&
            $this->pos->getY() <= $pos->getY() && $pos->getY() <= $this->pos->getY() + $this->size->getHeight()
        ;
    }

    public function getRandomPos(): Vec2
    {
        return new Vec2(
            RandomUtils::getRandomFloat($this->getLeft(), $this->getRight()),
            RandomUtils::getRandomFloat($this->getTop(), $this->getBottom()),
        );
    }

    public function intersectWith(self $other): bool
    {
        return ! (
            $this->getLeft() > $other->getRight()
            || $this->getRight() < $other->getLeft()
            || $this->getTop() > $other->getBottom()
            || $this->getBottom() < $other->getTop()
        );
    }

    public function grow(float $factor = null, float $widthFactor = null, float $heightFactor = null): self
    {
        $widthFactor = ($widthFactor ?? $factor) ?? 1;
        $heightFactor = ($heightFactor ?? $factor) ?? 1;

        $widthDiff = $widthFactor * $this->size->getWidth() - $this->size->getWidth();
        $heightDiff = $heightFactor * $this->size->getHeight() - $this->size->getHeight();

        $this->pos->add(-($widthDiff / 2), -($heightDiff / 2));
        $this->size->add($widthDiff, $heightDiff);

        return $this;
    }
}