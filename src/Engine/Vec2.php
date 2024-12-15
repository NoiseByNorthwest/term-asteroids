<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Vec2
{
    private float $x;

    private float $y;

    public function __construct(float $x = 0, float $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function __toString(): string
    {
        return sprintf('(%f, %f)', $this->getX(), $this->getY());
    }

    public function copy(): self
    {
        return new self($this->x, $this->y);
    }

    /**
     * @return float
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * @return float
     */
    public function getY(): float
    {
        return $this->y;
    }

    public function getWidth(): float
    {
        return $this->x;
    }

    public function getHeight(): float
    {
        return $this->y;
    }

    public function computeLength(): float
    {
        return sqrt(
            ($this->x ** 2) +
            ($this->y ** 2)
        );
    }

    public function setLength(float $newLength): self
    {
        $length = $this->computeLength();
        if ($length > 0) {
            $this->mul($newLength / $length);
        }

        return $this;
    }

    public function normalize(): self
    {
        return $this->setLength(1);
    }

    public function setVec(self $other): self
    {
        return $this->set($other->getX(), $other->getY());
    }

    public function set(float $x, float $y): self
    {
        $this->x = $x;
        $this->y = $y;

        return $this;
    }

    public function add(float $x, float $y): self
    {
        $this->x += $x;
        $this->y += $y;

        return $this;
    }

    public function addVec(self $other): self
    {
        $this->x += $other->x;
        $this->y += $other->y;

        return $this;
    }

    public function subVec(self $other): self
    {
        $this->x -= $other->x;
        $this->y -= $other->y;

        return $this;
    }

    public function minVec(self $other): self
    {
        $this->x -= $other->x;
        $this->y -= $other->y;

        return $this;
    }

    public function mul(float $v): self
    {
        $this->x *= $v;
        $this->y *= $v;

        return $this;
    }

    public function boundToRect(AABox $boundingRect): void
    {
        $x = $this->getX();
        $y = $this->getY();

        $dx = 0;
        $dy = 0;

        if ($x < $boundingRect->getLeft()) {
            $dx = $boundingRect->getLeft() - $x;
        } elseif ($x > $boundingRect->getRight()) {
            $dx = $boundingRect->getRight() - $x;
        }

        if ($y < $boundingRect->getTop()) {
            $dy = $boundingRect->getTop() - $y;
        } elseif ($y > $boundingRect->getBottom()) {
            $dy = $boundingRect->getBottom() - $y;
        }

        $this->add($dx, $dy);
    }
}
