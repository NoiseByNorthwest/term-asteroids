<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Mat3
{
    private array $rows = [];

    public static function identity(): self
    {
        return new self(
            [
                [1, 0, 0],
                [0, 1, 0],
                [0, 0, 1],
            ]
        );
    }

    /**
     * @param array $rows
     */
    public function __construct(array $rows)
    {
        assert(count($rows) === 3);
        foreach ($rows as $row) {
           assert(count($row) === 3);
        }

        $this->rows = $rows;
    }

    public function translate(float $x, float $y): self
    {
        $translationMatrix = self::identity();

        $translationMatrix->rows[0][2] = $x;
        $translationMatrix->rows[1][2] = $y;

        return $this->mult($translationMatrix);
    }

    public function rotate(float $angle): self
    {
        $rotationMatrix = self::identity();

        $cos = cos($angle);
        $sin = sin($angle);

        $rotationMatrix->rows[0][0] = $cos;
        $rotationMatrix->rows[0][1] = -$sin;
        $rotationMatrix->rows[1][0] = $sin;
        $rotationMatrix->rows[1][1] = $cos;

        return $this->mult($rotationMatrix);
    }

    public function mult(self $other): self
    {
        $resultRows = [];
        for ($i = 0; $i < 3; $i++) {
            $resultRows[] = [0, 0, 0];
            for ($j = 0; $j < 3; $j++) {
                $resultRows[$i][$j] = 0
                    + $this->rows[$i][0] * $other->rows[0][$j]
                    + $this->rows[$i][1] * $other->rows[1][$j]
                    + $this->rows[$i][2] * $other->rows[2][$j]
                ;
            }
        }

        return new self($resultRows);
    }

    public function transform(Vec2 $point): Vec2
    {
        return new Vec2(
            0
                + $this->rows[0][0] * $point->getX()
                + $this->rows[0][1] * $point->getY()
                + $this->rows[0][2]
            ,
            0
                + $this->rows[1][0] * $point->getX()
                + $this->rows[1][1] * $point->getY()
                + $this->rows[1][2]
        );
    }
}