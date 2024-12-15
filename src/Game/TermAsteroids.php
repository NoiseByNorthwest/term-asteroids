<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\CacheUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Game;
use NoiseByNorthwest\TermAsteroids\Engine\Input;
use NoiseByNorthwest\TermAsteroids\Engine\InputEvent;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Asteroid\Asteroid;
use NoiseByNorthwest\TermAsteroids\Game\Asteroid\HugeAsteroid;
use NoiseByNorthwest\TermAsteroids\Game\Asteroid\LargeAsteroid;
use NoiseByNorthwest\TermAsteroids\Game\Asteroid\MediumAsteroid;
use NoiseByNorthwest\TermAsteroids\Game\Asteroid\SmallAsteroid;
use NoiseByNorthwest\TermAsteroids\Game\Flame\Flame;
use NoiseByNorthwest\TermAsteroids\Game\Smoke\Smoke;

class TermAsteroids extends Game
{
    private bool $devMode;

    private bool $benchmarkMode;

    private bool $useNativeRenderer;

    private Spaceship $spaceship;

    private bool $spawnAsteroids = true;

    private float $lastAsteroidCreationTime = 0;

    private float $asteroidHardTimeCreationPeriod = 0.15;

    private float $lastBonusCreationTime = 0;

    public function __construct(
        bool $devMode,
        bool $benchmarkMode,
        bool $useNativeRenderer,
        bool $kittyKeyboardProtocolSupported
    ) {
        parent::__construct(kittyKeyboardProtocolSupported: $kittyKeyboardProtocolSupported);

        if ($devMode && $benchmarkMode) {
            throw new \RuntimeException('Dev mode & benchmark modes cannot be selected at the same time');
        }

        $this->devMode = $devMode;
        $this->benchmarkMode = $benchmarkMode;
        $this->useNativeRenderer = $useNativeRenderer;
    }

    protected function onInit(): void
    {
        ini_set('memory_limit', '1536M');

        $cacheDir = __DIR__ . '/../../.tmp/cache-' . PHP_VERSION;
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir);
        }

        $cacheFileName = $cacheDir . '/init.dat';
        if (file_exists($cacheFileName)) {
            if (!CacheUtils::loadFromFile($cacheFileName)) {
                unlink($cacheFileName);
            }
        }

        echo 'Warming up Asteroid caches...';
        echo sprintf(
            " DONE (%.2fs)\n",
            Timer::getExecutionTime(fn () => Asteroid::warmCaches())
        );

        echo 'Warming up Flame caches...';
        echo sprintf(
            " DONE (%.2fs)\n",
            Timer::getExecutionTime(fn () => Flame::warmCaches())
        );

        echo 'Warming up Smoke caches...';
        echo sprintf(
            " DONE (%.2fs)\n",
            Timer::getExecutionTime(fn () => Smoke::warmCaches())
        );

        echo 'Warming up PlasmaBall caches...';
        echo sprintf(
            " DONE (%.2fs)\n",
            Timer::getExecutionTime(fn () => PlasmaBall::warmCaches())
        );

        if (! file_exists($cacheFileName)) {
            CacheUtils::saveToFile($cacheFileName);
        }

        if ($this->useNativeRenderer) {
            $this->getScreen()->useNativeRenderer();
        }

        if ($this->devMode || $this->benchmarkMode) {
            $this->getScreen()->setMaxFrameRate(10000);
            $this->getScreen()->setDebugInfoDisplayEnabled(true);
            Spaceship::setMaxHealth(Spaceship::getMaxHealth() * 100000);
        }

        if ($this->benchmarkMode) {
            $this->getAdaptivePerformanceManager()->setEnabled(false);
        }
    }

    protected function onReset(): void
    {
        foreach (range(0, 100) as $_) {
            $star = $this->getGameObjectPool()->acquire(
                Star::class,
                new Vec2(0, 0),
                initializer: fn (Star $e) => $e->init(),
            );

            $this->addGameObject($star);
        }

        $this->spaceship = $this->getGameObjectPool()->acquire(
            Spaceship::class,
            pos: new Vec2(10, $this->getScreen()->getHeight() / 2),
            initializer: fn (Spaceship $e) => $e->init(),
        );

        $this->addGameObject($this->spaceship);

        $this->lastAsteroidCreationTime = 0;
        $this->lastBonusCreationTime = 0;

        $this->getScreen()->setBrightness(0);
    }

    protected function onUpdate(): void
    {
        $startDelay = 3;
        $this->getScreen()->setBrightness(Timer::getCurrentGameTime() / $startDelay);

        if ($this->benchmarkMode) {
            $this->handleBenchmarkGameplay();
        } else {
            $this->handleNormalGameplay();
        }
    }

    private function handleNormalGameplay(): void
    {
        foreach (Input::getEvents() as $inputEvent) {
            if ($inputEvent->isPress()) {
                switch ($inputEvent->getKey()) {
                    case InputEvent::KEY_ESC:
                    case 'q':
                        $this->setFinished(true);

                        return;

                    case 's':
                        $this->reset();

                        return;

                    case 'p':
                        Timer::toggleGameTimeFrozen();

                        return;

                    case '8':
                    case InputEvent::KEY_UP:
                        $this->spaceship->startThruster(Spaceship::THRUSTER_UP);

                        break;

                    case '5':
                    case InputEvent::KEY_DOWN:
                        $this->spaceship->startThruster(Spaceship::THRUSTER_DOWN);

                        break;

                    case '4':
                    case InputEvent::KEY_LEFT:
                        $this->spaceship->startThruster(Spaceship::THRUSTER_LEFT);

                        break;

                    case '6':
                    case InputEvent::KEY_RIGHT:
                        $this->spaceship->startThruster(Spaceship::THRUSTER_RIGHT);

                        break;
                }

                if ($this->devMode) {
                    switch ($inputEvent->getKey()) {
                        case 'r':
                            $this->getScreen()->toggleRenderer();

                            break;

                        case 'a':
                            $this->getAdaptivePerformanceManager()->toggleEnabled();

                            break;

                        case 'w':
                            $this->getScreen()->toggleDebugRectDisplayEnabled();

                            break;

                        case 'm':
                            $this->toggleProfiling();

                            break;

                        case 'd':
                            $this->spaceship->improveWeaponLevels(blueLaser: 1);

                            break;

                        case 'c':
                            $this->spaceship->improveWeaponLevels(blueLaser: -1);

                            break;

                        case 'f':
                            $this->spaceship->improveWeaponLevels(plasmaBall: 1);

                            break;

                        case 'v':
                            $this->spaceship->improveWeaponLevels(plasmaBall: -1);

                            break;

                        case 'g':
                            $this->spaceship->improveWeaponLevels(energyBeam: 1);

                            break;

                        case 'b':
                            $this->spaceship->improveWeaponLevels(energyBeam: -1);

                            break;

                        case 't':
                            $this->spawnAsteroids = ! $this->spawnAsteroids;

                            break;

                        case 'y':
                            $perlinTest = $this->getGameObjectPool()->acquire(
                                PerlinTest::class,
                                pos: $this->getScreen()->getSize()->copy()->mul(0.5),
                                initializer: fn (PerlinTest $e) => $e->init(
                                    repeated: true,
                                ),
                            );

                            $this->addGameObject($perlinTest);

                            break;

                        case 'u':
                            Timer::setGameTimeSpeedFactor(Timer::getGameTimeSpeedFactor() * 1.1);

                            break;

                        case 'i':
                            Timer::setGameTimeSpeedFactor(Timer::getGameTimeSpeedFactor() / 1.1);

                            break;

                        case 'o':
                            Timer::setGameTimeSpeedFactor(1);

                            break;

                        case 'k':
                            $this->asteroidHardTimeCreationPeriod /= 1.1;

                            break;

                        case 'l':
                            $this->asteroidHardTimeCreationPeriod *= 1.1;

                            break;
                    }
                }
            }

            if ($inputEvent->isRelease()) {
                switch ($inputEvent->getKey()) {
                    case '8':
                    case InputEvent::KEY_UP:
                        $this->spaceship->stopThruster(Spaceship::THRUSTER_UP);

                        break;

                    case '5':
                    case InputEvent::KEY_DOWN:
                        $this->spaceship->stopThruster(Spaceship::THRUSTER_DOWN);

                        break;

                    case '4':
                    case InputEvent::KEY_LEFT:
                        $this->spaceship->stopThruster(Spaceship::THRUSTER_LEFT);

                        break;

                    case '6':
                    case InputEvent::KEY_RIGHT:
                        $this->spaceship->stopThruster(Spaceship::THRUSTER_RIGHT);

                        break;
                }
            }
        }

        if (Timer::isGameTimeFrozen()) {
            $this->getScreen()->setCenteredText('- Pause -');
        }

        if (
            $this->spaceship->isTerminated()
        ) {
            $endDelay = 6;

            $this->getScreen()->setCenteredText(sprintf(
                'GAME OVER - You survived for %s - The game will restart in %d seconds',
                date('i:s', (int) $this->spaceship->getTerminationTime()),
                (int) ($endDelay - (Timer::getCurrentGameTime() - $this->spaceship->getTerminationTime()))
            ));

            $this->getScreen()->setBrightness(1 - (Timer::getCurrentGameTime() - $this->spaceship->getTerminationTime()) / $endDelay);

            if (Timer::getCurrentGameTime() - $this->spaceship->getTerminationTime() > $endDelay) {
                $this->reset();
            }

            return;
        }

        $currentTime = Timer::getCurrentGameTime();
        $hardTimeDelay = ($this->devMode ? 0.2 : 4) * 60;
        if (
            $this->spawnAsteroids &&
                $currentTime - $this->lastAsteroidCreationTime
                    >
                Math::lerp(
                    1.2,
                    $this->asteroidHardTimeCreationPeriod,
                    Math::bound($currentTime / $hardTimeDelay)
                )
        ) {
            do {
                $p = RandomUtils::getRandomFloat();

                $asteroidClassName = match (true) {
                    $p < 0.03 => HugeAsteroid::class,
                    $p < 0.25 => LargeAsteroid::class,
                    $p < 0.30 => MediumAsteroid::class,
                    default => SmallAsteroid::class,
                };

                $vFactor = Math::lerp(20, 180, Math::bound($currentTime / ($hardTimeDelay * 3)));

                $velocity = match ($asteroidClassName) {
                    HugeAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 1,
                    LargeAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 1.4,
                    MediumAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 2.1,
                    SmallAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 2.6,
                    default => throw new \RuntimeException('Unsupported case')
                };

                $asteroid = $this->getGameObjectPool()->acquire(
                    $asteroidClassName,
                    pos: new Vec2(
                        $this->getScreen()->getWidth() + $asteroidClassName::getSize(),
                        RandomUtils::getRandomInt(
                            ($asteroidClassName::getSize() / 2),
                            ($this->getScreen()->getHeight() - $asteroidClassName::getSize() / 2)
                        )
                    ),
                    initializer: fn (Asteroid $e) => $e->init(
                        $velocity
                    ),
                    withLimit: true,
                    withAdaptivePerformanceLimit: false
                );
            } while (! $asteroid);

            $this->addGameObject($asteroid);

            $this->lastAsteroidCreationTime = $currentTime;
        }

        if ($currentTime - $this->lastBonusCreationTime > 9) {
            $bonus = $this->getGameObjectPool()->acquire(
                Bonus::class,
                pos: new Vec2(
                    $this->getScreen()->getWidth() + Bonus::SIZE,
                    RandomUtils::getRandomInt(
                        Bonus::SIZE,
                        $this->getScreen()->getHeight() - Bonus::SIZE
                    )
                ),
                initializer: fn (Bonus $e) => $e->init()
            );

            $this->addGameObject($bonus);

            $this->lastBonusCreationTime = $currentTime;
        }
    }

    private function handleBenchmarkGameplay(): void
    {
        $currentTime = Timer::getCurrentGameTime();
        if ($currentTime > 20) {
            $this->setFinished(true);

            $stats = $this->getScreen()->getStats();

            $stats['avgDrawingTimeMs'] = Math::roundToInt(1000 * $stats['drawingTime'] / $stats['renderedFrameCount']);
            $stats['avgUpdateTimeMs'] = Math::roundToInt(1000 * $stats['updateTime'] / $stats['renderedFrameCount']);
            $stats['avgFrameTimeMs'] = Math::roundToInt(1000 * $stats['totalTime'] / $stats['renderedFrameCount']);

            $jitEnabled = opcache_get_status()['jit']['on'];
            $resultFileName = sprintf(
                '%s/../../.tmp/benchmark-%s:%s:%s-jit:%s.%05d.json',
                __DIR__,
                date('Ymd_His'),
                PHP_VERSION,
                $this->useNativeRenderer ? '1' : '0',
                $jitEnabled ? '1' : '0',
                $stats['renderedFrameCount']
            );

            file_put_contents(
                $resultFileName,
                json_encode(
                    [
                        'phpVersion' => PHP_VERSION,
                        'cpu' => trim(shell_exec(
                            "cat /proc/cpuinfo | grep -Po 'model name\s+: \K.+' | head -1"
                        )),
                        'nativeRenderer' => $this->useNativeRenderer,
                        'jit' => $jitEnabled,
                        'stats' => $stats,
                        'gameObjectPoolStats' => $this->getGameObjectPool()->getStats(),
                    ],
                    JSON_PRETTY_PRINT
                )
            );
        }

        $createAsteroidColumn = function (float $x) {
            $y = LargeAsteroid::getSize() / 2;
            while ($y < $this->getScreen()->getHeight()) {
                $asteroid = $this->getGameObjectPool()->acquire(
                    LargeAsteroid::class,
                    pos: new Vec2(
                        $x + LargeAsteroid::getSize(),
                        $y
                    ),
                    initializer: fn (LargeAsteroid $e) => $e->init(
                        80
                    )
                );

                $this->addGameObject($asteroid);

                $y += LargeAsteroid::getSize() * 1.5;
            }
        };

        if ($this->getScreen()->getStats()['renderedFrameCount'] === 0) {
            $this->spaceship->improveWeaponLevels(
                blueLaser: 100,
                plasmaBall: 100,
                energyBeam: 100,
            );

            $x = $this->getScreen()->getWidth() * 0.3;
            while ($x < $this->getScreen()->getWidth()) {
                $createAsteroidColumn($x);
                $x += LargeAsteroid::getSize() * 1.5;
            }
        }

        if (
            $currentTime - $this->lastAsteroidCreationTime > 0.5
        ) {
            $createAsteroidColumn($this->getScreen()->getWidth());

            $this->lastAsteroidCreationTime = $currentTime;
        }
    }
}
