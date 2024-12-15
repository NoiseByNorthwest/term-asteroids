<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapBuilder;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;

class Bonus extends GameObject
{
    public const SIZE = 5;

    public const TYPE_BLUE_LASER = 1;

    public const TYPE_PLASMA_BALL = 2;

    public const TYPE_ENERGY_BEAM = 3;

    public const TYPE_BULLET_TIME = 4;

    private static array $typeConfigs = [
        self::TYPE_BLUE_LASER => [
            'weight' => 10,
            'color' => '#00b1ff',
        ],
        self::TYPE_PLASMA_BALL => [
            'weight' => 9,
            'color' => '#cd00cf',
        ],
        self::TYPE_ENERGY_BEAM => [
            'weight' => 4,
            'color' => '#47a247',
        ],
        self::TYPE_BULLET_TIME => [
            'weight' => 2,
            'color' => '#ffff00',
        ],
    ];

    private int $type;

    public function __construct()
    {
        $color = ColorUtils::createColor([255, 255, 255]);
        parent::__construct(
            new Sprite(
                self::SIZE,
                self::SIZE,
                [
                    [
                        'name' => 'default',
                        'frames' => [
                            [
                                'bitmap' => (new BitmapBuilder(
                                    [
                                        '  M  ',
                                        ' MNM ',
                                        'MNONM',
                                    ],
                                    [
                                        ' ' => -1,
                                        'M' => ColorUtils::applyEffects($color, brightness: 0.5),
                                        'N' => ColorUtils::applyEffects($color, brightness: 0.75),
                                        'O' => ColorUtils::applyEffects($color, brightness: 1),
                                    ]
                                ))
                                    ->mirrorDown(1)
                                    ->build(),
                            ]
                        ]
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setBrightness(Math::lerp(
                                0.4,
                                1,
                                (sin($age * 5) + 1) / 2
                            ));

                            $renderingParameters->setGlobalBlendingColor(
                                ColorUtils::createColor(self::$typeConfigs[$this->type]['color'], alpha: 128)
                            );
                        },
                    ),
                ],
            ),
            movers: [
                new Mover(
                    new Vec2(-1, 0),
                    new Accelerator(
                        RandomUtils::getRandomFloat(40, 100),
                        0.1,
                        0,
                    )
                ),
            ],
            zIndex: 4,
        );
    }

    public function init(): void
    {
        $this->type = self::getRandomType();
        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        foreach ($this->getGame()->getGameObjects() as $otherGameObject) {
            if ($this === $otherGameObject) {
                continue;
            }

            if (! $otherGameObject instanceof Spaceship) {
                continue;
            }

            $spaceship = $otherGameObject;

            if (! $spaceship->collidesWith($this)) {
                continue;
            }

            switch ($this->type) {
                case self::TYPE_BLUE_LASER:
                case self::TYPE_PLASMA_BALL:
                case self::TYPE_ENERGY_BEAM:
                    $spaceship->improveWeaponLevels(
                        blueLaser: $this->type === self::TYPE_BLUE_LASER ? 1 : 0,
                        plasmaBall: $this->type === self::TYPE_PLASMA_BALL ? 1 : 0,
                        energyBeam: $this->type === self::TYPE_ENERGY_BEAM ? 1 : 0,
                    );

                    $spaceship->repair(Math::roundToInt(Spaceship::getMaxHealth() / 3));

                    break;

                case self::TYPE_BULLET_TIME:
                    $spaceship->startBulletTime();
            }


            $this->setTerminated();

            break;
        }
    }


    private static function getRandomType(): int
    {
        $weightSum = 0;
        foreach (self::$typeConfigs as $typeConfig) {
            $weightSum += $typeConfig['weight'];
        }

        $n = RandomUtils::getRandomFloat();
        foreach (self::$typeConfigs as $k => $typeConfig) {
            $normalizedWeight = $typeConfig['weight'] / $weightSum;
            if ($n <= $normalizedWeight) {
                return $k;
            }

            $n -= $normalizedWeight;
        }

        throw new \RuntimeException('Unreachable');
    }
}
