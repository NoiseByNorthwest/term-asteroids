<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class GameObjectPool
{
    public Game $game;

    /**
     * @var array<string, array<GameObject>>
     */
    private array $acquiredGameObjects = [];

    /**
     * @var array<string, array<GameObject>>
     */
    private array $releasedGameObjects = [];

    /**
     * @var array<string, int>
     */
    private array $excludedGameObjectCounts = [];

    /**
     * @param Game $game
     */
    public function __construct(Game $game)
    {
        $this->game = $game;

        $this->reset();
    }

    public function reset(): void
    {
        $this->acquiredGameObjects = [];
        $this->releasedGameObjects = [];
        $this->excludedGameObjectCounts = [];
    }

    /**
     * @return Game
     */
    public function getGame(): Game
    {
        return $this->game;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param Vec2 $pos
     * @param callable $initializer
     * @param bool $withLimit
     * @param bool $withAdaptivePerformanceLimit
     * @return T|null
     */
    public function acquire(
        string $className,
        Vec2 $pos,
        callable $initializer,
        bool $withLimit = false,
        bool $withAdaptivePerformanceLimit = true
    ) {
        $this->initClassTracking($className);

        assert(is_subclass_of($className, GameObject::class));

        if (
            $withLimit &&
            (
                (
                    $className::getMaxAcquiredCount() !== null &&
                    count($this->acquiredGameObjects[$className]) >= Math::roundToInt(
                        $className::getMaxAcquiredCount() * (
                            $withAdaptivePerformanceLimit ?
                                $this->getGame()->getAdaptivePerformanceManager()->getAllowedResourceConsumptionRatio()
                                : 1
                        )
                    )
                ) || $className::shouldBeExcluded(
                    $pos,
                    $this->getGame()->getAdaptivePerformanceManager()->getAllowedResourceConsumptionRatio()
                )
            )
        ) {
            $this->excludedGameObjectCounts[$className]++;

            return null;
        }

        if (
            count($this->releasedGameObjects[$className]) > 0
            && (
                count($this->releasedGameObjects[$className])
                + count($this->acquiredGameObjects[$className])
            ) >= $className::getMinPoolSize()
        ) {
            $gameObject = array_shift($this->releasedGameObjects[$className]);
            assert($gameObject instanceof GameObject);
            assert($gameObject->isTerminated());
            assert(!$this->isAcquired($gameObject));
        } else {
            $gameObject = new $className();
            $gameObject->setPool($this);
        }

        $gameObject->reset($pos, $initializer);
        $this->acquiredGameObjects[$className][$gameObject->getId()] = $gameObject;

        return $gameObject;
    }

    public function release(GameObject $gameObject): void
    {
        assert($gameObject->isTerminated());
        $className = get_class($gameObject);

        $this->initClassTracking($className);

        assert($this->isAcquired($gameObject));
        assert(! $this->isReleased($gameObject));
        assert(! isset($this->releasedGameObjects[$className][$gameObject->getId()]));

        unset($this->acquiredGameObjects[$className][$gameObject->getId()]);
        $this->releasedGameObjects[$className][$gameObject->getId()] = $gameObject;
    }

    public function isAcquired(GameObject $gameObject): bool
    {
        return isset(($this->acquiredGameObjects[get_class($gameObject)] ?? [])[$gameObject->getId()]);
    }

    public function isReleased(GameObject $gameObject): bool
    {
        return isset(($this->releasedGameObjects[get_class($gameObject)] ?? [])[$gameObject->getId()]);
    }

    public function getGameObjectCount(): int
    {
        return $this->getAcquiredGameObjectCount() + $this->getReleasedGameObjectCount();
    }

    public function getAcquiredGameObjectCount(): int
    {
        $sum = 0;
        foreach ($this->acquiredGameObjects as $acquiredGameObjects) {
            $sum += count($acquiredGameObjects);
        }

        return $sum;
    }

    public function getReleasedGameObjectCount(): int
    {
        $sum = 0;
        foreach ($this->releasedGameObjects as $releasedGameObjects) {
            $sum += count($releasedGameObjects);
        }

        return $sum;
    }

    public function getStats(): array
    {
        $total = [];

        foreach ($this->acquiredGameObjects as $k => $objects) {
            $total[$k] = ($total[$k] ?? 0) + count($objects);
        }

        foreach ($this->releasedGameObjects as $k => $objects) {
            $total[$k] = ($total[$k] ?? 0) + count($objects);
        }

        asort($total);

        return [
            'total' => $total,
            'acquired' => array_map(fn (array $e) => count($e), $this->acquiredGameObjects),
            'released' => array_map(fn (array $e) => count($e), $this->releasedGameObjects),
        ];
    }

    public function resetExcludedGameObjectCounts(): void
    {
        $this->excludedGameObjectCounts = [];
    }

    /**
     * @return array
     */
    public function getExcludedGameObjectCounts(): array
    {
        return $this->excludedGameObjectCounts;
    }

    private function initClassTracking(string $className): void
    {
        assert(is_subclass_of($className, GameObject::class));

        if (! isset($this->releasedGameObjects[$className])) {
            $this->releasedGameObjects[$className] = [];
        }

        if (! isset($this->acquiredGameObjects[$className])) {
            $this->acquiredGameObjects[$className] = [];
        }

        if (! isset($this->excludedGameObjectCounts[$className])) {
            $this->excludedGameObjectCounts[$className] = 0;
        }
    }
}
