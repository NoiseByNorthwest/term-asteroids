<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class BitmapBuilder
{
    private array $textPixelLines;
    private array $palette;

    public function __construct(array $textPixelLines, array $palette)
    {
        assert(count($textPixelLines) > 0);
        foreach ($textPixelLines as $textPixelLine) {
            assert(is_string($textPixelLine));
            assert(strlen($textPixelLine) === strlen($textPixelLines[0]));
            for ($i = 0; $i < strlen($textPixelLine); $i++) {
                assert(isset($palette[$textPixelLine[$i]]));
            }
        }

        $this->textPixelLines = $textPixelLines;
        $this->palette = $palette;
    }

    public function mirrorRight(int $offset = 0): self
    {
        foreach ($this->textPixelLines as $k => $textPixelLine) {
            $this->textPixelLines[$k] .= substr(strrev($textPixelLine), $offset);
        }

        return $this;
    }

    public function repeatRight(array|string $col, int $count = 1): self
    {
        if (is_string($col)) {
            $col = array_fill(0, count($this->textPixelLines), $col);
        }

        assert(count($col) === count($this->textPixelLines));

        for ($i = 0; $i < $count; $i++) {
            foreach ($col as $k => $lineFragment) {
                assert(strlen($lineFragment) === strlen($col[0]));
                $this->textPixelLines[$k] .= $lineFragment;
            }
        }

        return $this;
    }

    public function padRight(array|string $col, int $length): self
    {
        $d = $length - strlen($this->textPixelLines[0]);
        if ($d <= 0) {
            return $this;
        }

        return $this->repeatRight($col, $d);
    }

    public function repeatLeft(array|string $col, int $count = 1): self
    {
        if (is_string($col)) {
            $col = array_fill(0, count($this->textPixelLines), $col);
        }

        assert(count($col) === count($this->textPixelLines));

        for ($i = 0; $i < $count; $i++) {
            foreach ($col as $k => $lineFragment) {
                assert(strlen($lineFragment) === strlen($col[0]));
                $this->textPixelLines[$k] = $lineFragment . $this->textPixelLines[$k];
            }
        }

        return $this;
    }

    public function padLeft(null|array|string $col, int $length): self
    {
        $d = $length - strlen($this->textPixelLines[0]);
        if ($d <= 0) {
            return $this;
        }

        if ($col === null) {
            $col = [];
            foreach ($this->textPixelLines as $textPixelLine) {
                $col[] = substr($textPixelLine, -1);
            }
        }

        return $this->repeatLeft($col, $d);
    }

    public function mirrorUp(int $offset = 0): self
    {
        $this->textPixelLines = [
            ...array_slice(array_reverse($this->textPixelLines), $offset),
            ...$this->textPixelLines,
        ];

        return $this;
    }

    public function repeatUp(string $line, int $count = 1): self
    {
        assert(strlen($line) === strlen($this->textPixelLines[0]));

        for ($i = 0; $i < $count; $i++) {
            array_unshift($this->textPixelLines, $line);
        }

        return $this;
    }

    public function padUp(null|string $line, int $length): self
    {
        $d = $length - count($this->textPixelLines);
        if ($d <= 0) {
            return $this;
        }

        if ($line === null) {
            $line = $this->textPixelLines[0];
        }

        return $this->repeatUp($line, $d);
    }

    public function mirrorDown(int $offset = 0): self
    {
        $this->textPixelLines = [
            ...$this->textPixelLines,
            ...array_slice(array_reverse($this->textPixelLines), $offset)
        ];

        return $this;
    }

    public function repeatDown(string $line, int $count = 1): self
    {
        assert(strlen($line) === strlen($this->textPixelLines[0]));

        for ($i = 0; $i < $count; $i++) {
            $this->textPixelLines[] = $line;
        }

        return $this;
    }

    public function padDown(null|string $line, int $length): self
    {
        $d = $length - count($this->textPixelLines);
        if ($d <= 0) {
            return $this;
        }

        if ($line === null) {
            $line = $this->textPixelLines[count($this->textPixelLines) -1 ];
        }

        return $this->repeatDown($line, $d);
    }

    public function build(): Bitmap
    {
        $pixels = [];

        foreach ($this->textPixelLines as $textPixelLine) {
            for ($i = 0; $i < strlen($textPixelLine); $i++) {
                $color = ColorUtils::createColor($this->palette[$textPixelLine[$i]]);

                $pixels[] = $color;
            }
        }

        return new Bitmap(
            strlen($this->textPixelLines[0]),
            count($this->textPixelLines),
            $pixels
        );
    }
}
