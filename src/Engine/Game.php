<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

abstract class Game
{
    private Screen $screen;

    private bool $finished = false;

    /**
     * @var GameObject[]
     */
    private array $gameObjects = [];

    private GameObjectPool $gameObjectPool;

    private bool $profilingEnabled;

    private bool $profilingStarted = false;

    function __construct()
    {
        $this->gameObjectPool = new GameObjectPool($this);
        $this->profilingEnabled = getenv('SPX_ENABLED') === '1' && getenv('SPX_AUTO_START') === '0';
    }

    public function getGameObjectPool(): GameObjectPool
    {
        return $this->gameObjectPool;
    }

    public function run(): void
    {
        Timer::init();
        Input::init();

        $this->screen = new Screen(300, 144);
        $this->onInit();
        $this->screen->init();
        $this->reset();

        while (!$this->finished) {
            Timer::startFrame();

            $this->onUpdate();

            // we save the current list so that new objects will be updated & rendered in the next frame
            $gameObjects = $this->gameObjects;
            $renderedGameObjects = [];
            $renderedGameObjectStats = [];
            foreach ($gameObjects as $gameObject) {
                if (
                    // it could have been terminated by another object within this loop
                    $gameObject->isTerminated() ||
                        ! $gameObject->isActive()
                ) {
                    continue;
                }

                $gameObject->update();

                if (
                    // it could have been terminated itself (i.e. by its update() method called above)
                    $gameObject->isTerminated() ||
                    ! $gameObject->isActive()
                ) {
                    continue;
                }

                $renderedGameObjects[] = $gameObject;

                $className = get_class($gameObject);
                if (!isset($renderedGameObjectStats[$className])) {
                    $renderedGameObjectStats[$className] = 0;
                }

                $renderedGameObjectStats[$className]++;
            }

            $this->screen->clear(ColorUtils::createColor('#000000'));

            usort($renderedGameObjects, fn (GameObject $a, GameObject $b) => $a->getZIndex() <=> $b->getZIndex());
            foreach ($renderedGameObjects as $gameObject) {
                $gameObject->render();
            }

            arsort($renderedGameObjectStats);

            $this->screen->update(sprintf(
                'Game objects: total: %4d - rendered: %4d - acquired: %4d - released: %4d - rendered by type: (%s)',
                $this->gameObjectPool->getGameObjectCount(),
                count($renderedGameObjects),
                $this->gameObjectPool->getAcquiredGameObjectCount(),
                $this->gameObjectPool->getReleasedGameObjectCount(),
                implode(' - ', array_map(
                    fn ($k) => sprintf(
                        '%s: %5d',
                        array_slice(explode('\\', $k), -1, 1)[0],
                        $renderedGameObjectStats[$k]
                    ),
                    array_keys(array_slice($renderedGameObjectStats, 0, 6))
                ))
            ));
        }
    }

    public function getScreen(): Screen
    {
        return $this->screen;
    }

    public function setFinished(bool $finished): void
    {
        $this->finished = $finished;
    }

    /**
     * @return GameObject[]
     */
    public function getGameObjects(): array
    {
        return $this->gameObjects;
    }

    public function hasGameObject(int $id): bool
    {
        return isset($this->gameObjects[$id]);
    }

    public function getGameObject(int $id): GameObject
    {
        return $this->gameObjects[$id];
    }

    public function getGameObjectOrNull(int $id): ?GameObject
    {
        return $this->gameObjects[$id] ?? null;
    }

    public function addGameObject(GameObject $gameObject): void
    {
        assert($this->gameObjectPool->isAcquired($gameObject));
        assert($gameObject->isInitialized());
        assert(! $gameObject->isTerminated());
        assert(! isset($this->gameObjects[$gameObject->getId()]));

        $this->gameObjects[$gameObject->getId()] = $gameObject;
    }

    public function removeGameObject(GameObject $gameObject): void
    {
        assert($this->gameObjectPool->isReleased($gameObject));
        assert($gameObject->isTerminated());
        assert(isset($this->gameObjects[$gameObject->getId()]));

        unset($this->gameObjects[$gameObject->getId()]);
    }

    abstract protected function onInit(): void;

    abstract protected function onReset(): void;

    abstract protected function onUpdate(): void;

    protected function reset(): void
    {
        Timer::reset();

        $this->screen->reset();

        $this->gameObjects = [];
        $this->gameObjectPool->reset();

        $this->onReset();
    }

    protected function toggleProfiling(): void
    {
        if (!$this->profilingEnabled) {
            return;
        }

        if ($this->profilingStarted) {
            spx_profiler_stop();
        } else {
            spx_profiler_start();
        }

        $this->profilingStarted = ! $this->profilingStarted;
    }
}
