<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class SpriteEffect
{
    private string $key;

    private $handler;

    private bool $autoStart;

    private float $duration;

    private ?float $startedAt = null;

    public function __construct(callable $handler, bool $autoStart = true, ?string $key = null, float $duration = INF)
    {
        $this->key = $key ?? (string) RandomUtils::getRandomInt();
        $this->handler = $handler;
        $this->autoStart = $autoStart;
        $this->duration = $duration;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    public function reset(): void
    {
        $this->startedAt = null;
        if ($this->autoStart) {
            $this->start();
        }
    }

    public function start(): void
    {
        $this->startedAt = Timer::getCurrentFrameStartTime();
    }

    public function updateRenderingParameters(SpriteRenderingParameters $renderingParameters): void
    {
        if ($this->startedAt === null) {
            return;
        }

        $currentTime = Timer::getCurrentFrameStartTime();

        if ($currentTime > $this->startedAt + $this->duration) {
            $this->startedAt = null;

            return;
        }

        ($this->handler)($renderingParameters, $currentTime - $this->startedAt);
    }
}