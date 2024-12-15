<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

abstract class Game
{
    private AdaptivePerformanceManager $adaptivePerformanceManager;

    private Screen $screen;

    private bool $kittyKeyboardProtocolSupported;

    private bool $finished = false;

    /**
     * @var GameObject[]
     */
    private array $gameObjects = [];

    private GameObjectPool $gameObjectPool;

    private bool $profilerEnabled;

    private bool $profilingEnabled = false;

    function __construct(bool $kittyKeyboardProtocolSupported)
    {
        $this->kittyKeyboardProtocolSupported = $kittyKeyboardProtocolSupported;
        $this->gameObjectPool = new GameObjectPool($this);
        $this->profilerEnabled = getenv('SPX_ENABLED') === '1' && getenv('SPX_AUTO_START') === '0';
    }

    public function getGameObjectPool(): GameObjectPool
    {
        return $this->gameObjectPool;
    }

    public function run(): void
    {
        gc_disable();

        Timer::init();

        $this->adaptivePerformanceManager = new AdaptivePerformanceManager(45);
        $this->screen = new Screen(300, 144, $this->adaptivePerformanceManager);
        $this->onInit();
        $this->screen->init();
        // must be called after screen init to not disable kitty's keyboard protocol
        Input::init(kittyKeyboardProtocolSupported: $this->kittyKeyboardProtocolSupported);
        $this->reset();

        while (!$this->finished) {
            $profilingEnabled = $this->profilingEnabled;
            if ($profilingEnabled) {
                spx_profiler_start();
            }

            Timer::startFrame();
            $this->adaptivePerformanceManager->update();

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

            usort($renderedGameObjects, fn (GameObject $a, GameObject $b) => $a->getZIndex() <=> $b->getZIndex());

            arsort($renderedGameObjectStats);
            $debugLine = sprintf(
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
                    array_keys(array_slice($renderedGameObjectStats, 0, 7))
                ))
            );

            $this->screen->clear(ColorUtils::createColor('#000000'));
            foreach ($renderedGameObjects as $gameObject) {
                $gameObject->render();
            }

            $this->screen->update($debugLine);

            $this->gameObjectPool->resetExcludedGameObjectCounts();

            if ($profilingEnabled) {
                spx_profiler_stop();
            }
        }
    }

    public function getAdaptivePerformanceManager(): AdaptivePerformanceManager
    {
        return $this->adaptivePerformanceManager;
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
        for ($i = 0; $i < 10; ++$i) {
            $gcStatus = gc_status();

            if ($gcStatus['roots'] < 1000) {
                break;
            }

            gc_collect_cycles();
        }

        Timer::reset();

        $this->screen->reset();

        $this->gameObjects = [];
        $this->gameObjectPool->reset();

        $this->onReset();
    }

    protected function toggleProfiling(): void
    {
        if (! $this->profilerEnabled) {
            return;
        }

        $this->profilingEnabled = ! $this->profilingEnabled;
    }
}
