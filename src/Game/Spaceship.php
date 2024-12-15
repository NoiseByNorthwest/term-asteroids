<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapBuilder;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Flame\Flame;
use NoiseByNorthwest\TermAsteroids\Game\Flame\MediumFlame;
use NoiseByNorthwest\TermAsteroids\Game\Flame\VerySmallFlame;

class Spaceship extends DamageableGameObject
{
    public const THRUSTER_UP = 0;

    public const THRUSTER_DOWN = 1;

    public const THRUSTER_LEFT = 2;

    public const THRUSTER_RIGHT = 3;

    private const BULLET_TIME_DURATION = 8;

    private static int $maxHealth = 5000;

    private float $lastFireTime;

    private int $blueLaserLevel = 0;

    private int $plasmaBallLevel = 0;

    private int $energyBeamLevel = 0;

    private float $lastBulletTimeStartTime = -INF;

    /**
     * @var EnergyBeamSection[]
     */
    private array $energyBeamSections;

    private float $energyBeamAngleDeg;

    private int $energyBeamDir;

    private ?Flame $damageFlame;

    public static function getMainWeaponPowerIndex(): float
    {
        return 15;
    }

    public static function getMaxHealth(): int
    {
        return self::$maxHealth;
    }

    public static function setMaxHealth(int $maxHealth): void
    {
        self::$maxHealth = $maxHealth;
    }

    public function __construct()
    {
        parent::__construct(
            new Sprite(
                20,
                5,
                [
                    [
                        'name' => 'default',
                        'frames' => [
                            [
                                'bitmap' => (new BitmapBuilder(
                                    [
                                        'MMM                 ',
                                        ' MMM       MMMMM    ',
                                        'MMMMMMMMMMMMMMMMM   ',
                                        '  MMMMMMMMMMMMMMMMMM',
                                        '   MMMMMMMMMMMMMMM  ',
                                    ],
                                    [' ' => -1, 'M' => [128, 128, 128]]
                                ))
                                    ->build()
                            ]
                        ]
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters) {
                            if (Timer::getCurrentGameTime() - $this->lastBulletTimeStartTime < self::BULLET_TIME_DURATION) {
                                $width = $this->getSprite()->getWidth();
                                $verticalBlendingColors = [];
                                for ($i = 0; $i < $width; $i++) {
                                    $verticalBlendingColors[$i] = ColorUtils::rgbaToColor(
                                        255,
                                        255,
                                        0,
                                        (int) (
                                            255 * (0.5 + 0.5 * sin(($i / ($width - 1)) * M_PI + 50 * Timer::getCurrentGameTime()))
                                        )
                                    );
                                }

                                $renderingParameters->setVerticalBlendingColors($verticalBlendingColors);
                            }

                            $renderingParameters->setPersisted(true);
                            $renderingParameters->setPersistedColor(ColorUtils::createColor([128, 128, 128, 32]));
                        },
                    ),
                ]
            ),
        );
    }

    public function init(): void
    {
        $this->lastFireTime = 0;
        $this->energyBeamSections = [];
        for ($i = 0; $i < $this->getScreen()->getWidth(); $i += EnergyBeamSection::WIDTH) {
            $energyBeamSection = $this->getPool()->acquire(
                EnergyBeamSection::class,
                pos: new Vec2(
                    $i,
                    40,
                ),
                initializer: fn (EnergyBeamSection $e) => $e->init(
                    $this,
                )
            );

            $this->getGame()->addGameObject($energyBeamSection);
            $this->energyBeamSections[] = $energyBeamSection;
        }

        $this->energyBeamAngleDeg = 0;
        $this->energyBeamDir = 1;
        $this->damageFlame = null;

        $this->setMovers(
            array_map(
                fn ($dir) => new Mover(
                    $dir,
                    new Accelerator(
                        70,
                        0.01,
                        0.07,
                        0.2,
                        autoStart: false
                    )
                ),
                [
                    new Vec2(0, -1),
                    new Vec2(0, 1),
                    new Vec2(-1, 0),
                    new Vec2(1, 0),
                ]
            )
        );

        $this->damageableObjectInit();
    }

    protected function afterPosUpdate(): void
    {
        $this->getPos()->boundToRect($this->getScreen()->getRect());
    }

    protected function doUpdate(): void
    {
        parent::doUpdate();

        $currentTime = Timer::getCurrentGameTime();
        if ($currentTime - $this->lastFireTime > 0.4) {
            $this->fireMultiplePlasmaBalls();
            $this->fireBlueLaser();
            $this->lastFireTime = $currentTime;
        }

        $this->updateEnergyBeam();

        if ($currentTime - $this->lastBulletTimeStartTime < self::BULLET_TIME_DURATION) {
            Timer::setGameTimeSpeedFactor(Math::lerpPath([
                '0.0' => 1,
                '0.5' => 0.3,
                '0.8' => 0.2,
                '1.0' => 1,
            ], ($currentTime - $this->lastBulletTimeStartTime) / self::BULLET_TIME_DURATION));
        } elseif ($this->lastBulletTimeStartTime !== -INF) {
            Timer::setGameTimeSpeedFactor(1);
            $this->lastBulletTimeStartTime = -INF;
        }
    }

    public function startThruster(int $thruster): void
    {
        $this->getMovers()[$thruster]->getAccelerator()->restart();
    }

    public function stopThruster(int $thruster): void
    {
        $this->getMovers()[$thruster]->getAccelerator()->stop();
    }

    public function improveWeaponLevels(int $blueLaser = 0, int $plasmaBall = 0, int $energyBeam = 0): void
    {
        $this->blueLaserLevel += $blueLaser;
        $this->blueLaserLevel = Math::bound($this->blueLaserLevel, 0, 4);
        $this->plasmaBallLevel += $plasmaBall;
        $this->plasmaBallLevel = Math::bound($this->plasmaBallLevel, 0, 4);
        $this->energyBeamLevel += $energyBeam;
        $this->energyBeamLevel = Math::bound($this->energyBeamLevel, 0, 6);
    }

    public function startBulletTime(): void
    {
        $this->lastBulletTimeStartTime = Timer::getCurrentGameTime();
    }

    protected function onCollision(DamageableGameObject $other, int $otherHealthBeforeCollision): void
    {
        parent::onCollision($other, $otherHealthBeforeCollision);

        $this->hit($otherHealthBeforeCollision);

        if ($this->isTerminated()) {
            return;
        }

        if ($this->getHealth() < self::getMaxHealth() * 0.6 && ! $this->damageFlame) {
            $flame = $this->getPool()->acquire(
                VerySmallFlame::class,
                pos: $this->getPos()->copy()->addVec(new Vec2(-$this->getSprite()->getWidth() / 2, 0)),
                initializer: fn (VerySmallFlame $e) => $e->init(
                    $this,
                    repeated: true,
                    smokeEmissionPeriod: 0.1,
                ),
            );

            $this->getGame()->addGameObject($flame);

            $this->damageFlame = $flame;
        }
    }

    protected function onRepair(): void
    {
        if ($this->getHealth() >= self::getMaxHealth() * 0.6 && $this->damageFlame) {
            $this->damageFlame->setRepeated(false);
            $this->damageFlame = null;
        }
    }


    protected function onDestructionPhaseEnd(): void
    {
        $flame = $this->getPool()->acquire(
            MediumFlame::class,
            pos: $this->getPos(),
            initializer: fn (MediumFlame $e) => $e->init()
        );

        $this->getGame()->addGameObject($flame);
    }

    private function fireBlueLaser(): void
    {
        $count = $this->blueLaserLevel * 2 - 1;
        for ($i = 0; $i < $count; $i++) {
            $laser = $this->getPool()->acquire(
                BlueLaser::class,
                pos: new Vec2(
                    $this->getBoundingBox()->getRight() + 10,
                    $this->getPos()->getY() + 1
                    + 2 * ($i % 2 === 0 ? -1 : 1) * ceil($i / 2),
                ),
                initializer: fn (BlueLaser $e) => $e->init(
                    $this,
                ),
            );

            $this->getGame()->addGameObject($laser);
        }
    }

    private function fireMultiplePlasmaBalls(): void
    {
        $count = $this->plasmaBallLevel * 2 - 1;
        for ($i = 0; $i < $count; $i++) {
            $plasmaBall = $this->getPool()->acquire(
                PlasmaBall::class,
                pos: new Vec2(
                    $this->getBoundingBox()->getRight() + 10,
                    $this->getPos()->getY() + 1
                ),
                initializer: fn (PlasmaBall $e) => $e->init(
                    $this,
                    new Vec2(
                        1,
                        (
                            0.1 * ($i % 2 === 0 ? -1 : 1) * ceil($i / 2)
                        )
                    ),
                )
            );

            $this->getGame()->addGameObject($plasmaBall);
        }
    }

    private function updateEnergyBeam(): void
    {
        $lastHitTime = 0;
        foreach ($this->energyBeamSections as $energyBeamSection) {
            $energyBeamSection->setLevel($this->energyBeamLevel);
            $energyBeamSection->setActive(false);
            $lastHitTime = max($lastHitTime, $energyBeamSection->getLastHitTime());
        }

        if ($this->energyBeamLevel === 0) {
            return;
        }

        $this->energyBeamAngleDeg += Timer::getElapsedGameTimeSincePreviousFrame()
            * Math::lerp(12, 60, Math::bound((Timer::getCurrentGameTime() - $lastHitTime) / 0.8))
            * $this->energyBeamDir
        ;

        $maxAngle = 27;
        if ($this->energyBeamAngleDeg > $maxAngle) {
            $this->energyBeamAngleDeg = $maxAngle;
            $this->energyBeamDir = -1;
        }

        if ($this->energyBeamAngleDeg < -$maxAngle) {
            $this->energyBeamAngleDeg = -$maxAngle;
            $this->energyBeamDir = 1;
        }

        $targetPos = $this->getPos()->copy()->add(
            ($this->getScreen()->getWidth() - $this->getPos()->getX() - 1) * cos($this->energyBeamAngleDeg * M_PI / 180),
            ($this->getScreen()->getWidth() - $this->getPos()->getX() - 1) * sin($this->energyBeamAngleDeg * M_PI / 180),
        );

        $firePos = new Vec2(
            $this->getBoundingBox()->getRight() + 1,
            $this->getPos()->getY() + 1,
        );

        $delta = $targetPos
            ->copy()
            ->subVec($firePos)
        ;

        foreach ($this->energyBeamSections as $energyBeamSection) {
            $energyBeamSection->setActive(
                $firePos->getX() <= $energyBeamSection->getBoundingBox()->getPos()->getX()
            );

            if (! $energyBeamSection->isActive()) {
                continue;
            }

            $energyBeamSection->getPos()->set(
                $energyBeamSection->getPos()->getX(),
                $firePos->getY()
                + $delta->getY() * (($energyBeamSection->getPos()->getX() - $firePos->getX()) / $delta->getX())
            );
        }
    }
}
