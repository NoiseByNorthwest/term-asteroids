<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapBuilder;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;

class Bonus extends GameObject
{
    public const TYPE_BLUE_LASER = 1;

    public const TYPE_PLASMA_BALL = 2;

    public const TYPE_ENERGY_BEAM = 3;

    private static array $typeConfigs = [
        self::TYPE_BLUE_LASER => [
            'weight' => 10,
            'color' => '#00b1ff',
        ],
        self::TYPE_PLASMA_BALL => [
            'weight' => 8,
            'color' => '#cd00cf',
        ],
        self::TYPE_ENERGY_BEAM => [
            'weight' => 4,
            'color' => '#47a247',
        ],
    ];

    private int $type;

    public function __construct()
    {
        $color = ColorUtils::createColor([255, 255, 255]);
        parent::__construct(
            new Sprite(
                5,
                5,
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

                            $renderingParameters->setBlendingColor(
                                ColorUtils::createColor(self::$typeConfigs[$this->type]['color'], alpha: 128)
                            );
                        },
                    ),
                ],
            ),
            function () {
                return new Accelerator(
                    RandomUtils::getRandomFloat(40, 100),
                    0.1,
                    0,
                );
            },
            fn () => new Vec2(-1, 0),
            zIndex: 3,
        );
    }

    public function init(Vec2 $pos)
    {
        $this->getPos()->setVec($pos);
        $this->type = self::getRandomType();
        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        foreach ($this->getOtherGameObjects() as $otherGameObject) {
            if (! $otherGameObject instanceof Player) {
                continue;
            }

            $player = $otherGameObject;

            if (! $player->collidesWith($this)) {
                continue;
            }

            $player->improveWeaponLevels(
                blueLaser: $this->type === self::TYPE_BLUE_LASER ? 1 : 0,
                plasmaBall: $this->type === self::TYPE_PLASMA_BALL ? 1 : 0,
                energyBeam: $this->type === self::TYPE_ENERGY_BEAM ? 1 : 0,
            );

            $player->repair(Player::getMaxHealth() / 3);

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
