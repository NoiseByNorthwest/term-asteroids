<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

abstract class GameObject
{
    private static int $idSeq = 0;

    private GameObjectPool $pool;

    private int $id;

    private Sprite $sprite;

    private array $hitBoxes = [];

    /**
     * @var array<Mover>
     */
    private array $movers;

    private Vec2 $currentDisplacementDir;

    private float $currentDisplacementVelocity;

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

    public static function shouldBeExcluded(Vec2 $pos, float $allowedResourceConsumptionRatio): bool
    {
        return false;
    }

    public function __construct(
        Sprite $sprite,
        array $movers = [],
        int $zIndex = 0
    ) {
        $this->sprite = $sprite;
        $this->movers = $movers;
        $this->currentDisplacementDir = new Vec2();
        $this->currentDisplacementVelocity = 0;
        $this->zIndex = $zIndex;
    }

    public function reset(Vec2 $pos, ?callable $initializer = null): void
    {
        $this->id = ++self::$idSeq;
        $this->sprite->reset();

        foreach ($this->movers as $mover) {
            $mover->reset();
        }

        $this->currentDisplacementDir->set(0, 0);
        $this->currentDisplacementVelocity = 0;

        $this->creationTime = Timer::getCurrentGameTime();
        $this->initialized = false;
        $this->active = true;
        $this->terminated = false;
        $this->terminationTime = null;

        $this->getPos()->setVec($pos);
        $this->sprite->updateBoundingBox();
        $this->updateHitBoxes();

        if ($initializer !== null) {
            $initializer($this);
        }
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
     * @return AABox[]
     */
    protected function resolveHitBoxes(): array
    {
        return [
            $this->getBoundingBox(),
        ];
    }

    protected function updateHitBoxes(): void
    {
        $this->hitBoxes = $this->resolveHitBoxes();
    }

    /**
     * @deprecated
     * @param AABox[] $hitBoxes
     * @return void
     */
    protected function setHitBoxes(array $hitBoxes): void
    {
        $this->hitBoxes = $hitBoxes;
    }

    /**
     * @return AABox[]
     */
    public function getHitBoxes(): array
    {
        return $this->hitBoxes;
    }

    public function collidesWith(GameObject $other): bool
    {
        return $this->resolveFirstCollidingHitBox($other) !== null;
    }

    public function resolveFirstCollidingHitBox(GameObject $other): ?AABox
    {
        if (! $this->sprite->getBoundingBox()->intersectWith($other->sprite->getBoundingBox())) {
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

    public function getMovers(): array
    {
        return $this->movers;
    }

    public function getSingleMover(): Mover
    {
        assert(count($this->movers) === 1);

        return $this->movers[0];
    }

    /**
     * @param array<Mover> $movers
     * @return void
     */
    public function setMovers(array $movers): void
    {
        $this->movers = $movers;
    }

    public function getCurrentDisplacementDir(): Vec2
    {
        return $this->currentDisplacementDir;
    }

    public function getCurrentDisplacementVelocity(): float
    {
        return $this->currentDisplacementVelocity;
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
                    && (Timer::getCurrentGameTime() -  $this->creationTime) > 5
            )
        ) {
            $this->setTerminated();

            return;
        }

        $this->getSprite()->update();

        $displacementVector = new Vec2();
        foreach ($this->movers as $mover) {
            $displacementVector->addVec($mover->getMoveVectorSinceLastStep());
        }

        $this->currentDisplacementDir = $displacementVector->copy()->normalize();
        $this->currentDisplacementVelocity = $displacementVector->computeLength() / max(0.00001, Timer::getPreviousFrameTime());

        $this->sprite->getPos()->addVec($displacementVector);

        $this->afterPosUpdate();

        $this->sprite->updateBoundingBox();
        $this->updateHitBoxes();

        if (
            ! $this->enteredScreen &&
            ! $this->isOffScreen()
        ) {
            $this->enteredScreen = true;
        }

        $this->doUpdate();
    }

    protected function afterPosUpdate(): void
    {
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

        if ($screen->isDebugRectDisplayEnabled()) {
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
        $this->terminationTime = Timer::getCurrentGameTime();
        $this->pool->release($this);
        $this->getGame()->removeGameObject($this);
    }
}
