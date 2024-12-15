<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class AABox
{
    private Vec2 $min;

    private Vec2 $max;

    private Vec2 $size;

    public function __construct(Vec2 $pos, Vec2 $size, ?Vec2 $max = null)
    {
        $this->min = $pos;
        $this->max = $max ?? $this->min->copy()->addVec($size);
        $this->size = $size;
    }

    public function copy(): self
    {
        return new self(
            $this->min->copy(),
            $this->size->copy(),
            $this->max->copy()
        );
    }

    /**
     * @return Vec2
     */
    public function getPos(): Vec2
    {
        return $this->min;
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
        return $this->min->getX();
    }

    public function getRight(): float
    {
        return $this->max->getX();
    }

    public function getTop(): float
    {
        return $this->min->getY();
    }

    public function getBottom(): float
    {
        return $this->max->getY();
    }

    public function getCenter(): Vec2
    {
        return $this->min->copy()->add(
            $this->size->getWidth() / 2,
            $this->size->getHeight() / 2
        );
    }

    public function containsPos(Vec2 $pos): bool
    {
        return
            $this->min->getX() <= $pos->getX() && $pos->getX() <= $this->max->getX() &&
            $this->min->getY() <= $pos->getY() && $pos->getY() <= $this->max->getY()
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
            $this->min->getX() > $other->max->getX()
            || $this->max->getX() < $other->min->getX()
            || $this->min->getY() > $other->max->getY()
            || $this->max->getY() < $other->min->getY()
        );
    }

    public function grow(?float $factor = null, ?float $widthFactor = null, ?float $heightFactor = null): self
    {
        $widthFactor = ($widthFactor ?? $factor) ?? 1;
        $heightFactor = ($heightFactor ?? $factor) ?? 1;

        $widthDiff = $widthFactor * $this->size->getWidth() - $this->size->getWidth();
        $heightDiff = $heightFactor * $this->size->getHeight() - $this->size->getHeight();

        $this->min->add(-$widthDiff * 0.5, -$heightDiff * 0.5);
        $this->max->add($widthDiff * 0.5, $heightDiff * 0.5);
        $this->size->add($widthDiff, $heightDiff);

        return $this;
    }
}