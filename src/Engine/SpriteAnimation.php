<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class SpriteAnimation
{
    private Sprite $sprite;

    private string $name;
    private bool $repeated;

    /**
     * @var SpriteFrame[]
     */
    private array $frames;

    private array $loopIndexes;

    private int $currentFrameRawIdx;

    private float $currentFrameStartTime;

    public function __construct(
        Sprite $sprite,
        string $name,
        bool   $repeated,
        bool   $loopBack,
        array  $framesData
    ) {
        $this->sprite = $sprite;
        $this->name = $name;
        $this->repeated = $repeated;

        $this->frames = [];
        foreach ($framesData as $frameData) {
            $this->frames[] = new SpriteFrame($this, $frameData['duration'] ?? 1, $frameData['bitmap']);
        }

        $this->loopIndexes = [];
        for ($i = 0; $i < count($this->frames); $i++) {
            $this->loopIndexes[] = $i;
        }

        if ($loopBack && count($this->frames) > 1) {
            for ($i = count($this->frames) - 2; $i >= 1; $i--) {
                $this->loopIndexes[] = $i;
            }
        }

        $this->reset();
    }

    public function reset(): void
    {
        $this->currentFrameRawIdx = 0;
        $this->currentFrameStartTime = Timer::getCurrentFrameStartTime();
    }

    /**
     * @return Sprite
     */
    public function getSprite(): Sprite
    {
        return $this->sprite;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isRepeated(): bool
    {
        return $this->repeated;
    }

    /**
     * @param bool $repeated
     */
    public function setRepeated(bool $repeated): void
    {
        $this->repeated = $repeated;
    }

    /**
     * @return array
     */
    public function getFrames(): array
    {
        return $this->frames;
    }

    public function getCurrentFrame(): SpriteFrame
    {
        return $this->frames[$this->loopIndexes[$this->getCurrentFrameIdx()]];
    }

    /**
     * @return int
     */
    public function getCurrentFrameIdx(): int
    {
        return $this->currentFrameRawIdx % count($this->loopIndexes);
    }

    public function isFinished(): bool
    {
        return ! $this->repeated && $this->currentFrameRawIdx >= count($this->loopIndexes) - 1;
    }

    public function update(): void
    {
        if ($this->isFinished()) {
            return;
        }

        $currentTime = Timer::getCurrentFrameStartTime();
        while ($this->getCurrentFrame()->getDuration() < $currentTime - $this->currentFrameStartTime) {
            $this->currentFrameStartTime += $this->getCurrentFrame()->getDuration();
            $this->currentFrameRawIdx++;
        }
    }

    public function selectRandomFrame(): void
    {
        $this->currentFrameRawIdx = RandomUtils::getRandomInt(0, count($this->frames) - 1);
        $this->currentFrameStartTime = Timer::getCurrentFrameStartTime();
    }
}