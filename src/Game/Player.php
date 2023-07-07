<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapBuilder;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Flame\Flame;
use NoiseByNorthwest\TermAsteroids\Game\Flame\MediumFlame;
use NoiseByNorthwest\TermAsteroids\Game\Flame\VerySmallFlame;

class Player extends DamageableGameObject
{
    private static int $maxHealth = 15000;

    private float $lastFireTime;

    private int $blueLaserLevel = 0;

    private int $plasmaBallLevel = 0;

    private int $energyBeamLevel = 0;

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
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setBlendingColor(
                                ColorUtils::rgbaToColor(
                                    255,
                                    0,
                                    0,
                                    (int) (
                                        255 * (1 - $this->getHealth() / static::getMaxHealth()) * abs(sin($age * 2))
                                    )
                                )
                            );
                        },
                    )
                ]
            ),
            function () {
                return new Accelerator(
                    70,
                    0.2,
                    0.1,
                    0.2
                );
            }
        );
    }

    public function init(Vec2 $pos): void
    {
        $this->lastFireTime = 0;
        $this->energyBeamSections = [];
        for ($i = 0; $i < $this->getScreen()->getWidth(); $i += EnergyBeamSection::WIDTH) {
            $energyBeamSection = $this->getPool()->acquire(EnergyBeamSection::class);

            $energyBeamSection->init(
                $this,
                new Vec2(
                    $i,
                    40,
                )
            );

            $this->getGame()->addGameObject($energyBeamSection);
            $this->energyBeamSections[] = $energyBeamSection;
        }

        $this->energyBeamAngleDeg = 0;
        $this->energyBeamDir = 1;
        $this->damageFlame = null;

        $this->getPos()->setVec($pos);
        $this->getMover()->setBoundingRect($this->getScreen()->getRect());

        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        $currentTime = Timer::getCurrentFrameStartTime();
        if ($currentTime - $this->lastFireTime > 0.4) {
            $this->fireMultiplePlasmaBalls();
            $this->fireBlueLaser();
            $this->lastFireTime = $currentTime;
        }

        $this->updateEnergyBeam();
    }

    public function improveWeaponLevels(int $blueLaser = 0, int $plasmaBall = 0, int $energyBeam = 0): void
    {
        $this->blueLaserLevel += $blueLaser;
        $this->blueLaserLevel = Math::bound($this->blueLaserLevel, 0, 4);
        $this->plasmaBallLevel += $plasmaBall;
        $this->plasmaBallLevel = Math::bound($this->plasmaBallLevel, 0, 4);
        $this->energyBeamLevel += $energyBeam;
        $this->energyBeamLevel = Math::bound($this->energyBeamLevel, 0, 4);
    }

    protected function onHit(): void
    {
        if ($this->getHealth() < self::getMaxHealth() * 0.5 && ! $this->damageFlame) {
            $flame = $this->getPool()->acquire(VerySmallFlame::class);

            $flame->init(
                $this->getPos()->copy()->addVec(new Vec2(-$this->getSprite()->getWidth() / 2, 0)),
                $this,
                repeated: true,
                smokeEmissionPeriod: 0.5,
            );

            $this->getGame()->addGameObject($flame);

            $this->damageFlame = $flame;
        }
    }

    protected function onRepair(): void
    {
        if ($this->getHealth() >= self::getMaxHealth() * 0.5 && $this->damageFlame) {
            $this->damageFlame->setRepeated(false);
            $this->damageFlame = null;
        }
    }


    protected function onDestruction(): void
    {
        $flame = $this->getPool()->acquire(MediumFlame::class);
        $flame->init($this->getPos());
        $this->getGame()->addGameObject($flame);
    }

    private function fireBlueLaser(): void
    {
        $count = $this->blueLaserLevel * 2 - 1;
        for ($i = 0; $i < $count; $i++) {
            $laser = $this->getPool()->acquire(BlueLaser::class);

            $laser->init(
                $this,
                new Vec2(
                    $this->getBoundingBox()->getRight() + 10,
                    $this->getPos()->getY() + 1
                        + 2 * ($i % 2 === 0 ? -1 : 1) * ceil($i / 2),
                )
            );

            $this->getGame()->addGameObject($laser);
        }
    }

    private function fireMultiplePlasmaBalls(): void
    {
        $count = $this->plasmaBallLevel * 2 - 1;
        for ($i = 0; $i < $count; $i++) {
            $plasmaBall = $this->getPool()->acquire(PlasmaBall::class);

            $plasmaBall->init(
                $this,
                new Vec2(
                    $this->getBoundingBox()->getRight() + 10,
                    $this->getPos()->getY() + 1
                ),
                new Vec2(
                    1,
                    (
                        0.1 * ($i % 2 === 0 ? -1 : 1) * ceil($i / 2)
                    )
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

        $this->energyBeamAngleDeg += Timer::getPreviousFrameTime()
            * Math::lerp(12, 60, Math::bound((Timer::getCurrentFrameStartTime() - $lastHitTime) / 0.8))
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
            ->minVec($firePos)
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
