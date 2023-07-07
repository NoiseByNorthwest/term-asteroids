<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

abstract class GameObject
{
    private static int $idSeq = 0;

    private GameObjectPool $pool;

    private int $id;

    private Sprite $sprite;

    private ?array $hitBoxes = null;

    private MomentumBasedMover $mover;

    private int $zIndex;

    private float $creationTime;

    private bool $initialized = false;

    private bool $enteredScreen = false;

    private bool $active = true;

    private bool $terminated = false;

    private ?float $terminationTime = null;

    public static function getMinPoolSize(): int
    {
        return 1;
    }

    public static function getMaxAcquiredCount(): ?int
    {
        return null;
    }

    public function __construct(
        Sprite $sprite,
        callable $acceleratorFactory,
        ?callable $nextMoveDirResolver = null,
        int $zIndex = 0
    ) {
        $this->sprite = $sprite;
        $this->mover = new MomentumBasedMover(
            $this->sprite->getPos(),
            $acceleratorFactory,
            $nextMoveDirResolver
        );

        $this->zIndex = $zIndex;

        $this->reset();
    }

    public function reset(): void
    {
        $this->id = ++self::$idSeq;
        $this->sprite->reset();
        $this->mover->reset();
        $this->creationTime = Timer::getCurrentFrameStartTime();
        $this->initialized = false;
        $this->active = true;
        $this->terminated = false;
        $this->terminationTime = null;
    }

    /**
     * @return GameObjectPool
     */
    public function getPool(): GameObjectPool
    {
        return $this->pool;
    }

    /**
     * @internal
     * @param GameObjectPool $pool
     */
    public function setPool(GameObjectPool $pool): void
    {
        $this->pool = $pool;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Game
     */
    public function getGame(): Game
    {
        return $this->pool->getGame();
    }

    public function getOtherGameObjects(): array
    {
        $otherGameObjects = [];
        foreach ($this->getGame()->getGameObjects() as $gameObject) {
            if ($gameObject->id === $this->id) {
                continue;
            }

            $otherGameObjects[] = $gameObject;
        }

        return $otherGameObjects;
    }

    public function getScreen(): Screen
    {
        return $this->getGame()->getScreen();
    }

    /**
     * @return Sprite
     */
    public function getSprite(): Sprite
    {
        return $this->sprite;
    }

    public function getPos(): Vec2
    {
        return $this->sprite->getPos();
    }

    public function getBoundingBox(): AABox
    {
        return $this->sprite->getBoundingBox();
    }

    /**
     * @param AABox[] $hitBoxes
     * @return void
     */
    protected function setHitBoxes(array $hitBoxes)
    {
        $this->hitBoxes = $hitBoxes;
    }

    /**
     * @return AABox[]
     */
    public function getHitBoxes(): array
    {
        if ($this->hitBoxes === null) {
            $this->hitBoxes = [
                $this->getBoundingBox(),
            ];
        }

        return $this->hitBoxes;
    }

    public function collidesWith(GameObject $other): bool
    {
        return $this->resolveFirstCollidingHitBox($other) !== null;
    }

    public function resolveFirstCollidingHitBox(GameObject $other): ?AABox
    {
        if (! $this->getBoundingBox()->intersectWith($other->getBoundingBox())) {
            return null;
        }

        foreach ($this->getHitBoxes() as $hitBox) {
            foreach ($other->getHitBoxes() as $otherHitBox) {
                if ($hitBox->intersectWith($otherHitBox)) {
                    return $hitBox;
                }
            }
        }

        return null;
    }

    public function getMover(): MomentumBasedMover
    {
        return $this->mover;
    }

    /**
     * @return int
     */
    public function getZIndex(): int
    {
        return $this->zIndex;
    }

    public function getCreationTime(): float
    {
        return $this->creationTime;
    }

    public function isOffScreen(): bool
    {
        return ! $this->getBoundingBox()->intersectWith($this->getScreen()->getRect());
    }

    public function terminateWhenOffScreen(): bool
    {
        return true;
    }

    public final function update(): void
    {
        if (
            $this->enteredScreen &&
            $this->terminateWhenOffScreen() &&
            $this->isOffScreen()
            || (
                ! $this->enteredScreen
                    && $this->isOffScreen()
                    && (Timer::getCurrentFrameStartTime() -  $this->creationTime) > 5
            )
        ) {
            $this->setTerminated();

            return;
        }

        $this->getSprite()->update();
        $this->getMover()->updatePos();

        if (
            ! $this->enteredScreen &&
            $this->getBoundingBox()->intersectWith($this->getScreen()->getRect())
        ) {
            $this->enteredScreen = true;
        }

        $this->doUpdate();
    }


    protected function doUpdate(): void
    {
    }

    public function render(): void
    {
        if (! $this->active) {
            return;
        }

        $screen = $this->getScreen();

        $this->sprite->draw($screen);

        if ($screen->isDebug()) {
            foreach ($this->getHitBoxes() as $hitBox) {
                $screen->drawDebugRect($hitBox, ColorUtils::createColor([128, 0, 0]));
            }
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    protected function setInitialized(): void
    {
        assert($this->getPool()->isAcquired($this));
        assert(! $this->initialized);
        assert(! $this->terminated);

        $this->initialized = true;
        $this->enteredScreen = false;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isTerminated(): bool
    {
        return $this->terminated;
    }

    public function getTerminationTime(): ?float
    {
        return $this->terminationTime;
    }

    protected function setTerminated(): void
    {
        assert(
            ! $this->terminated,
            sprintf('%s::%d has been terminated twice', static::class, $this->getId())
        );

        $this->terminated = true;
        $this->terminationTime = Timer::getCurrentFrameStartTime();
        $this->pool->release($this);
        $this->getGame()->removeGameObject($this);
    }
}
