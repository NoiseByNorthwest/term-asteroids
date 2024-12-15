<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapBuilder;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Flame\Flame;
use NoiseByNorthwest\TermAsteroids\Game\Flame\VerySmallFlame;

/**
 * FIXME add a projectile super type ?
 */
class BlueLaser extends GameObject
{
    private int $initiatorId;

    public function __construct()
    {
        parent::__construct(
            new Sprite(
                18,
                1,
                [
                    [
                        'name' => 'default',
                        'frames' => [
                            [
                                'bitmap' => (new BitmapBuilder(
                                    [
                                        'NNNNNMMMMMMMNNNNNN',
                                    ],
                                    [' ' => -1, 'M' => [0, 0, 255], 'N' => [0, 0, 255, 128]]
                                ))
                                    ->build(),
                            ]
                        ]
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setGlobalBlendingColor(
                                ColorUtils::rgbaToColor(
                                    0,
                                    255,
                                    255,
                                    (int) (255 * abs(sin($age * 5)))
                                )
                            );
                        },
                    ),
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters) {
                            $renderingParameters->setPersisted(true);
                            $renderingParameters->setPersistedColor(ColorUtils::createColor([0, 128, 255, 64]));
                        },
                    ),
                ]
            ),
            movers: [
                new Mover(
                    new Vec2(1, 0),
                    new Accelerator(
                        200,
                        0.1,
                        0,
                    )
                ),
            ]
        );
    }

    public function init(GameObject $initiator): void
    {
        $this->initiatorId = $initiator->getId();
        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        foreach ($this->getGame()->getGameObjects() as $otherGameObject) {
            if ($this === $otherGameObject) {
                continue;
            }

            if (! $otherGameObject instanceof DamageableGameObject) {
                continue;
            }

            if ($otherGameObject->getId() === $this->initiatorId) {
                continue;
            }

            if (! $this->collidesWith($otherGameObject)) {
                continue;
            }

            $flame = $this->getPool()->acquire(
                VerySmallFlame::class,
                pos: new Vec2(
                    $this->getBoundingBox()->getRight(),
                    $this->getPos()->getY(),
                ),
                initializer: fn (Flame $e) => $e->init(
                    $otherGameObject,
                ),
            );

            $this->getGame()->addGameObject($flame);

            $otherGameObject->hit(Math::roundToInt(3.2 * Spaceship::getMainWeaponPowerIndex()));
            $this->setTerminated();

            break;
        }
    }
}
