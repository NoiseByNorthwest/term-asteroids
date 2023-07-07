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
    private array $excludedGameObjectCount = [];

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
        $this->excludedGameObjectCount = [];
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
     * @return T|null
     */
    public function acquire(string $className, bool $withLimit = false)
    {
        $this->initClassTracking($className);

        assert(is_subclass_of($className, GameObject::class));

        if (
            $withLimit &&
            $className::getMaxAcquiredCount() !== null &&
            count($this->acquiredGameObjects[$className]) >= $className::getMaxAcquiredCount()
        ) {
            $this->excludedGameObjectCount[$className]++;

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
            $gameObject->reset();
            $this->acquiredGameObjects[$className][$gameObject->getId()] = $gameObject;

            return $gameObject;
        }

        $gameObject = new $className();
        $gameObject->setPool($this);
        assert(!$this->isAcquired($gameObject));
        assert(!$this->isReleased($gameObject));
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
        return array_sum(
            array_map(
                fn (array $e) => count($e),
                $this->acquiredGameObjects
            )
        );
    }

    public function getReleasedGameObjectCount(): int
    {
        return array_sum(
            array_map(
                fn (array $e) => count($e),
                $this->releasedGameObjects
            )
        );
    }

    /**
     * @return array
     */
    public function getExcludedGameObjectCount(): array
    {
        return $this->excludedGameObjectCount;
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

        if (! isset($this->excludedGameObjectCount[$className])) {
            $this->excludedGameObjectCount[$className] = 0;
        }
    }
}
