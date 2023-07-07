<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;

abstract class DamageableGameObject extends GameObject
{
    private int $health;

    abstract public static function getMaxHealth(): int;

    public function __construct(Sprite $sprite, callable $acceleratorFactory, ?callable $nextMoveDirResolver = null)
    {
        foreach (
            [
                [
                    'key' => 'hit',
                    'color' => '#ff0000',
                    'alphaRatio' => fn () => (1 - $this->health / static::getMaxHealth()),
                ],
                [
                    'key' => 'repair',
                    'color' => '#00ff00',
                    'alphaRatio' => fn () => $this->health / static::getMaxHealth(),
                ],
            ] as $effectData
        ) {
            $sprite->addEffect(
                new SpriteEffect(
                    function (SpriteRenderingParameters $renderingParameters, float $age) use($effectData) {
                        $renderingParameters->setBlendingColor(
                            ColorUtils::createColor(
                                $effectData['color'],
                                (int)(
                                    255 * $effectData['alphaRatio']() * abs(sin($age * 30))
                                )
                            )
                        );
                    },
                    autoStart: false,
                    key: $effectData['key'],
                    duration: 0.5,
                )
            );
        }

        parent::__construct($sprite, $acceleratorFactory, $nextMoveDirResolver);
    }

    /**
     * @return int
     */
    public function getHealth(): int
    {
        return $this->health;
    }

    protected function setInitialized(): void
    {
        $this->health = static::getMaxHealth();

        parent::setInitialized();
    }

    protected function doUpdate(): void
    {
        foreach ($this->getOtherGameObjects() as $otherGameObject) {
            if (
                ! $otherGameObject instanceof DamageableGameObject
                // in order to not process twice this pair
                || $this->getId() < $otherGameObject->getId()
            ) {
                continue;
            }

            if (!$this->canDamage($otherGameObject)) {
                continue;
            }

            if (
                ! $otherGameObject->collidesWith($this)
            ) {
                continue;
            }

            $thisEnergy = $this->getHealth();
            $otherEnergy = $otherGameObject->getHealth();

            $this->hit($otherEnergy);
            $otherGameObject->hit($thisEnergy);

            if ($this->isTerminated()) {
                break;
            }
        }
    }

    public function hit(int $damage): void
    {
        $this->health = Math::bound($this->health - $damage, 0, static::getMaxHealth());

        $this->getSprite()->startEffect('hit');
        $this->onHit();

        if ($this->health === 0) {
            $this->setTerminated();

            $this->onDestruction();
        }
    }

    public function repair(int $health): void
    {
        $this->health = Math::bound($this->health + $health, 0, static::getMaxHealth());

        $this->getSprite()->startEffect('repair');
        $this->onRepair();
    }

    protected function canDamage(DamageableGameObject $otherGameObject): bool
    {
        return true;
    }

    abstract protected function onDestruction(): void;

    protected function onHit(): void
    {
    }

    protected function onRepair(): void
    {
    }
}
