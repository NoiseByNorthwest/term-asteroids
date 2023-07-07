<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\CacheUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Game;
use NoiseByNorthwest\TermAsteroids\Engine\Input;
use NoiseByNorthwest\TermAsteroids\Engine\Key;
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

    private Player $player;

    private float $lastAsteroidCreationTime = 0;

    private float $lastBonusCreationTime = 0;

    public function __construct(
        bool $devMode,
        bool $benchmarkMode,
        bool $useNativeRenderer
    ) {
        parent::__construct();

        if ($devMode && $benchmarkMode) {
            throw new \RuntimeException('Dev mode & benchmark modes cannot be selected at the same time');
        }

        $this->devMode = $devMode;
        $this->benchmarkMode = $benchmarkMode;
        $this->useNativeRenderer = $useNativeRenderer;
    }

    protected function onInit(): void
    {
        ini_set('memory_limit', '1G');

        $cacheDir = __DIR__ . '/../../.tmp/cache';
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
            Player::setMaxHealth(Player::getMaxHealth() * 1000);
        }

        if ($this->benchmarkMode) {
            $this->getScreen()->setAdaptivePerformance(false);
        }
    }

    protected function onReset(): void
    {
        foreach (range(0, 100) as $_) {
            $star = $this->getGameObjectPool()->acquire(Star::class);
            $star->init();
            $this->addGameObject($star);
        }

        $this->player = $this->getGameObjectPool()->acquire(Player::class);
        $this->player->init(new Vec2(10, $this->getScreen()->getHeight() / 2));

        $this->addGameObject($this->player);

        $this->lastAsteroidCreationTime = 0;
        $this->lastBonusCreationTime = 0;

        $this->getScreen()->setBrightness(0);
    }

    protected function onUpdate(): void
    {
        $startDelay = 3;
        $this->getScreen()->setBrightness(Timer::getCurrentFrameStartTime() / $startDelay);

        if ($this->benchmarkMode) {
            $this->handleBenchmarkGameplay();
        } else {
            $this->handleNormalGameplay();
        }
    }

    private function handleNormalGameplay(): void
    {
        $pressedKey = Input::getPressedKey();
        if ($pressedKey !== null) {
            switch ($pressedKey) {
                case Key::ESC->value:
                case 'q':
                    $this->setFinished(true);

                    return;

                case 's':
                    $this->reset();

                    return;

                case Key::UP->value:
                    $this->player->getMover()->accelerate(new Vec2(0, -1));

                    break;

                case Key::DOWN->value:
                    $this->player->getMover()->accelerate(new Vec2(0, 1));

                    break;

                case Key::LEFT->value:
                    $this->player->getMover()->accelerate(new Vec2(-1, 0));

                    break;

                case Key::RIGHT->value:
                    $this->player->getMover()->accelerate(new Vec2(1, 0));

                    break;

            }

            if ($this->devMode) {
                switch ($pressedKey) {
                    case 'r':
                        $this->getScreen()->toggleRenderer();

                        break;

                    case 'a':
                        $this->getScreen()->toggleAdaptivePerformance();

                        break;

                    case 'w':
                        $this->getScreen()->toggleDebug();

                        break;

                    case 'p':
                        $this->toggleProfiling();

                        break;

                    case 'd':
                        $this->player->improveWeaponLevels(blueLaser: 1);

                        break;

                    case 'c':
                        $this->player->improveWeaponLevels(blueLaser: -1);

                        break;

                    case 'f':
                        $this->player->improveWeaponLevels(plasmaBall: 1);

                        break;

                    case 'v':
                        $this->player->improveWeaponLevels(plasmaBall: -1);

                        break;

                    case 'g':
                        $this->player->improveWeaponLevels(energyBeam: 1);

                        break;

                    case 'b':
                        $this->player->improveWeaponLevels(energyBeam: -1);

                        break;
                }
            }
        }

        if (
            $this->player->isTerminated()
        ) {
            $endDelay = 6;

            $this->getScreen()->setCenteredText(sprintf(
                'GAME OVER - You survived for %s - The game will restart in %d seconds',
                date('i:s', (int) $this->player->getTerminationTime()),
                (int) ($endDelay - (Timer::getCurrentFrameStartTime() - $this->player->getTerminationTime()))
            ));

            $this->getScreen()->setBrightness(1 - (Timer::getCurrentFrameStartTime() - $this->player->getTerminationTime()) / $endDelay);

            if (Timer::getCurrentFrameStartTime() - $this->player->getTerminationTime() > $endDelay) {
                $this->reset();
            }

            return;
        }

        $currentTime = Timer::getCurrentFrameStartTime();
        $hardTimeDelay = ($this->devMode ? 0.5 : 4) * 60;
        if (
            $currentTime - $this->lastAsteroidCreationTime
            > Math::lerp(
                1.2,
                0.15,
                Math::bound($currentTime / $hardTimeDelay)
            )
        ) {
            $p = RandomUtils::getRandomFloat();

            $asteroidClassName = match (true) {
                $p < 0.01 => HugeAsteroid::class,
                $p < 0.18 => LargeAsteroid::class,
                $p < 0.25 => MediumAsteroid::class,
                default => SmallAsteroid::class,
            };

            $asteroid = $this->getGameObjectPool()->acquire($asteroidClassName);

            $vFactor = Math::lerp(20, 110, Math::bound($currentTime / $hardTimeDelay));

            $velocity = match ($asteroidClassName) {
                HugeAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 1,
                LargeAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 1.5,
                MediumAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 2.3,
                SmallAsteroid::class => $vFactor * RandomUtils::getRandomFloat(0.5, 1) * 3.1,
                default => throw new \RuntimeException('Unsupported case')
            };

            $asteroid->init(
                new Vec2(
                    $this->getScreen()->getWidth() + $asteroid->getSprite()->getSize()->getWidth(),
                    RandomUtils::getRandomInt(
                        ($asteroid->getSprite()->getSize()->getHeight() / 2),
                        ($this->getScreen()->getHeight() - $asteroid->getSprite()->getSize()->getHeight() / 2)
                    )
                ),
                $velocity
            );

            $this->addGameObject($asteroid);

            $this->lastAsteroidCreationTime = $currentTime;
        }

        if ($currentTime - $this->lastBonusCreationTime > 9) {
            $bonus = $this->getGameObjectPool()->acquire(Bonus::class);
            $bonus->init(
                new Vec2(
                    $this->getScreen()->getWidth() + $bonus->getSprite()->getSize()->getWidth(),
                    RandomUtils::getRandomInt(
                        $bonus->getSprite()->getSize()->getHeight(),
                        $this->getScreen()->getHeight() - $bonus->getSprite()->getSize()->getHeight()
                    )
                )
            );

            $this->addGameObject($bonus);

            $this->lastBonusCreationTime = $currentTime;
        }
    }

    private function handleBenchmarkGameplay(): void
    {
        $currentTime = Timer::getCurrentFrameStartTime();
        if ($currentTime > 20) {
            $this->setFinished(true);

            $jitEnabled = opcache_get_status()['jit']['enabled'];
            $resultFileName = sprintf(
                '%s/../../.tmp/benchmark-native_renderer:%s-jit:%s.json',
                __DIR__,
                $this->useNativeRenderer ? '1' : '0',
                $jitEnabled ? '1' : '0',
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
                        'stats' => $this->getScreen()->getStats(),
                    ],
                    JSON_PRETTY_PRINT
                )
            );
        }

        $createAsteroidColumn = function (float $x) {
            $y = LargeAsteroid::getSize() / 2;
            while ($y < $this->getScreen()->getHeight()) {
                $asteroid = $this->getGameObjectPool()->acquire(LargeAsteroid::class);

                $asteroid->init(
                    new Vec2(
                        $x + LargeAsteroid::getSize(),
                        $y
                    ),
                    80
                );

                $this->addGameObject($asteroid);

                $y += LargeAsteroid::getSize() * 1.5;
            }
        };

        if ($this->getScreen()->getStats()['renderedFrameCount'] === 0) {
            $this->player->improveWeaponLevels(
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
