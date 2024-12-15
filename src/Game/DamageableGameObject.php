<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffectHelper;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Game\Asteroid\HugeAsteroid;

abstract class DamageableGameObject extends GameObject
{
    private int $health;

    private float $lastInvulnerabilityPeriodStartTime = 0;

    private ?float $destructionTime = null;

    private float $destructionPhaseDuration = 0.0;

    abstract public static function getMaxHealth(): int;

    public function __construct(Sprite $sprite, array $movers = [])
    {
        $sprite->addEffect(
            new SpriteEffect(
                function (SpriteRenderingParameters $renderingParameters, float $age) {
                    $damageRatio = 1 - $this->health / static::getMaxHealth();
                    $renderingParameters->setGlobalBlendingColor(
                        ColorUtils::createColor(
                            '#ff0000',
                            (int)(
                                255 * ((0.5 * $damageRatio) + (0.5 * $damageRatio * abs(sin($age * 10))))
                            )
                        )
                    );

                    $height = $this->getSprite()->getHeight();
                    $renderingParameters->setHorizontalDistortionOffsets(
                        SpriteEffectHelper::generateHorizontalDistortionOffsets(
                            height: $height,
                            maxAmplitude: $height * $damageRatio * 0.13,
                            timeFactor: 5,
                            shearFactor: 2 * ($height / HugeAsteroid::getSize())
                        )
                    );
                },
            )
        );

        $sprite->addEffect(
            new SpriteEffect(
                function (SpriteRenderingParameters $renderingParameters, float $age) {
                    $healthRatio = $this->health / static::getMaxHealth();
                    $renderingParameters->setGlobalBlendingColor(
                        ColorUtils::createColor(
                            '#00ff00',
                            (int)(
                                255 * $healthRatio * abs(sin($age * 30))
                            )
                        )
                    );
                },
                autoStart: false,
                key: 'repair',
                duration: 1,
            )
        );

        parent::__construct($sprite, movers: $movers);
    }

    protected function setHealth(int $health): void
    {
        $this->health = Math::bound($health, 0, static::getMaxHealth());
    }

    /**
     * @return int
     */
    public function getHealth(): int
    {
        return $this->health;
    }

    public function getDestructionTime(): ?float
    {
        return $this->destructionTime;
    }

    public function getDestructionPhaseDuration(): float
    {
        return $this->destructionPhaseDuration;
    }

    public function getDestructionPhaseCompletionRatio(): float
    {
        if ($this->destructionTime === null) {
            return 0;
        }

        if ($this->destructionPhaseDuration === 0.0) {
            return 0;
        }

        return Math::bound((Timer::getCurrentGameTime() - $this->destructionTime) / $this->destructionPhaseDuration);
    }

    protected function damageableObjectInit(float $destructionPhaseDuration = 0.0): void
    {
        $this->health = static::getMaxHealth();
        $this->lastInvulnerabilityPeriodStartTime = Timer::getCurrentGameTime();
        $this->destructionTime = null;
        $this->destructionPhaseDuration = $destructionPhaseDuration;

        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        if (
            $this->destructionTime !== null
                && Timer::getCurrentGameTime() - $this->destructionTime > $this->destructionPhaseDuration
        ) {
            $this->onDestructionPhaseEnd();
            $this->setTerminated();

            return;
        }

        foreach ($this->getGame()->getGameObjects() as $otherGameObject) {
            if ($this === $otherGameObject) {
                continue;
            }

            if (
                ! $otherGameObject instanceof DamageableGameObject
                // in order to not process twice this pair
                || $this->getId() < $otherGameObject->getId()
            ) {
                continue;
            }

            if (
                ! $this->canCollide($otherGameObject)
                    || ! $otherGameObject->canCollide($this)) {
                continue;
            }

            if (
                ! $otherGameObject->collidesWith($this)
            ) {
                continue;
            }

            $currentHealth = $this->getHealth();
            $this->onCollision($otherGameObject, $otherGameObject->getHealth());
            $otherGameObject->onCollision($this, $currentHealth);

            if ($this->isTerminated()) {
                break;
            }
        }
    }

    public function hit(int $damage): void
    {
        $this->setHealth($this->health - $damage);

        if ($this->destructionTime === null && $this->health === 0) {
            $this->destructionTime = Timer::getCurrentGameTime();

            $this->onDestructionPhaseStart();

            if ($this->destructionPhaseDuration === 0.0) {
                $this->onDestructionPhaseEnd();
                $this->setTerminated();
            }
        }
    }

    public function repair(int $health): void
    {
        $this->health = Math::bound($this->health + $health, 0, static::getMaxHealth());

        $this->getSprite()->startEffect('repair');
        $this->onRepair();
    }

    protected function canCollide(DamageableGameObject $otherGameObject): bool
    {
        if ($this->destructionTime !== null) {
            return false;
        }

        if (Timer::getCurrentGameTime() - $this->lastInvulnerabilityPeriodStartTime < 0.05) {
            return false;
        }

        return true;
    }

    protected function onCollision(DamageableGameObject $other, int $otherHealthBeforeCollision): void
    {
        $this->lastInvulnerabilityPeriodStartTime = Timer::getCurrentGameTime();
    }

    protected function onRepair(): void
    {
    }

    protected function onDestructionPhaseStart(): void
    {
    }

    abstract protected function onDestructionPhaseEnd(): void;
}
