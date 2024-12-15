<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffectHelper;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Flame\Flame;
use NoiseByNorthwest\TermAsteroids\Game\Flame\SmallFlame;

class PlasmaBall extends GameObject
{
    const MAX_VARIANT_COUNT = 9;

    private int $initiatorId;

    private float $duration;

    public static function getMinPoolSize(): int
    {
        return 50;
    }

    public static function warmCaches(): void
    {
        for ($i = 0; $i < self::MAX_VARIANT_COUNT; $i++) {
            new self($i);
        }
    }

    public function __construct(?int $seed = null)
    {
        $seed = $seed ?? RandomUtils::getRandomInt(0, self::MAX_VARIANT_COUNT - 1);
        $size = 14;
        parent::__construct(
            new Sprite(
                $size,
                $size,
                [
                    [
                        'name' => 'default',
                        'repeat' => true,
                        'loopBack' => true,
                        'frames' => array_map(
                            fn (Bitmap $e) => [
                                'duration' => 0.05,
                                'bitmap' => $e,
                            ],
                            [
                                ...(fn ($count) => array_map(
                                    fn (int $e) => BitmapNoiseGenerator::generate(
                                        $size,
                                        $size,
                                        [
                                            '0' => [0, 0, 0, 0],
                                            '0.2' => [0, 0, 255, 128],
                                            '0.4' => [128, 0, 255],
                                            '1' => [255, 0, 255],
                                        ],
                                        seed: [static::class, $seed],
                                        shift: $e / 15,
                                        scales: [5],
                                    ),
                                    array_keys(array_fill(0, $count, null))
                                ))(20),
                            ],
                        )
                    ],
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setGlobalAlpha(
                                (int) (255 * min(1.0, ($age / 0.3)))
                            );

                            $height = $this->getSprite()->getHeight();
                            $maxAmplitude = 7;
                            $horizontalDistortionOffsets = SpriteEffectHelper::generateHorizontalDistortionOffsets(
                                height: $height,
                                amplitude: $maxAmplitude * (0.5 + 0.5 * sin(1.4 * M_PI + 2.5 * $age)),
                                shearFactor: 11
                            );

                            $renderingParameters->setHorizontalDistortionOffsets($horizontalDistortionOffsets);

                            $renderingParameters->setPersisted(true);
                            $renderingParameters->setPersistedColor(ColorUtils::createColor([128, 0, 128, 112]));
                        },
                    ),
                ]
            ),
        );
    }

    public function init(
        GameObject $initiator,
        Vec2 $dir,
    ): void {
        $this->initiatorId = $initiator->getId();
        $this->duration = 100;

        $this->setMovers([
            new Mover(
                $dir,
                new Accelerator(
                    120,
                    0.1,
                    0,
                )
            ),
        ]);

        $this->setInitialized();
    }

    protected function resolveHitBoxes(): array
    {
        return [
            $this->getBoundingBox()->copy()->grow(factor: 0.65),
        ];
    }

    protected function doUpdate(): void
    {
        $currentTime = Timer::getCurrentGameTime();

        if ($this->getCreationTime() + $this->duration < $currentTime) {
            $this->setTerminated();

            return;
        }

        foreach ($this->getGame()->getGameObjects() as $otherGameObject) {
            if ($this === $otherGameObject) {
                continue;
            }

            if (!$otherGameObject instanceof DamageableGameObject) {
                continue;
            }

            if ($otherGameObject->getId() === $this->initiatorId) {
                continue;
            }

            if (! $otherGameObject->collidesWith($this)) {
                continue;
            }

            $flame = $this->getPool()->acquire(
                SmallFlame::class,
                pos: new Vec2(
                    $this->getBoundingBox()->getRight(),
                    $this->getPos()->getY(),
                ),
                initializer: fn (Flame $e) => $e->init(
                    $otherGameObject,
                    ColorUtils::createColor([92, 0, 255, 128])
                ),
            );

            $this->getGame()->addGameObject($flame);

            $otherGameObject->hit(Math::roundToInt(4.5 * Spaceship::getMainWeaponPowerIndex()));
            $this->setTerminated();

            break;
        }
    }
}
