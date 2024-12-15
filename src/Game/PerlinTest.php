<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffectHelper;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Smoke\HugeSmoke;
use NoiseByNorthwest\TermAsteroids\Game\Smoke\SmallSmoke;

class PerlinTest extends GameObject
{
    public function __construct(?int $seed = null)
    {
        $size = SmallSmoke::getSize();

        $color = ColorUtils::createColor([128, 128, 128]);

        parent::__construct(
            new Sprite(
                $size,
                $size,
                [
                    [
                        'name' => 'default',
                        'loopBack' => false,
                        'frames' => array_map(
                            fn (Bitmap $e) => [
                                'duration' => 0.02,
                                'bitmap' => $e,
                            ],
                            [
                                ...(fn ($count) => array_map(
                                    fn (int $e) => BitmapNoiseGenerator::generate(
                                        $size,
                                        $size,
                                        [
                                            '0' => [0, 0, 0, 0],
                                            '0.25' => [0, 0, 0, 0],
                                            '0.26' => ColorUtils::applyEffects($color, globalAlpha: 2, brightness: 0.1),
                                            '0.24' => ColorUtils::applyEffects($color, globalAlpha: 8, brightness: 0.15),
                                            '0.3' => ColorUtils::applyEffects($color, globalAlpha: 96, brightness: 0.3),
                                            '0.7' => ColorUtils::applyEffects($color, globalAlpha: 128, brightness: 0.6),
                                            '1' => ColorUtils::applyEffects($color, globalAlpha: 140, brightness: 0.8)
                                        ],
                                        seed: [static::class, $seed],
                                        shift: $e * Math::lerp(0.08, 0.015, $size / HugeSmoke::getSize()),
                                        radius: Math::lerpPath([
                                            '0.0' => 0.2,
                                            '0.5' => 1.2,
                                            '1.0' => 1.2,
                                        ], $e / ($count - 1)),
                                        zFactor: Math::lerpPath([
                                            '0.0' => 1,
                                            '0.5' => 1,
                                            '1.0' => 0.1,
                                        ], $e / ($count - 1)),
                                        maxScaleCount: 4,
                                    ),
                                    array_keys(array_fill(0, $count, null))
                                ))(
                                    Math::lerp(70, 100, $size / HugeSmoke::getSize())
                                ),
                            ],
                        )
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters) {
                            $height = $this->getSprite()->getHeight();

                            $horizontalBackgroundDistortionOffsets = SpriteEffectHelper::generateHorizontalDistortionOffsets(
                                height: $height,
                                maxAmplitude: 10 * $this->getSprite()->getCurrentAnimation()->getCompletionRatio(),
                                timeFactor: 20,
                                shearFactor: 3 * ($height / HugeSmoke::getSize())
                            );

                            $renderingParameters->setHorizontalBackgroundDistortionOffsets($horizontalBackgroundDistortionOffsets);
                        },
                    ),
                ]
            ),
            movers: [
                new Mover(
                    new Vec2(1, -0.3),
                    new Accelerator(
                        0,
                        0.1,
                        0,
                    )
                ),
            ],
            zIndex: 1
        );
    }

    public function init(
        bool $repeated = false,
    ): void {
        $this->getSprite()->getCurrentAnimation()->setRepeated($repeated);
        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        if ($this->getSprite()->getCurrentAnimation()->isFinished()) {
            $this->setTerminated();
        }
    }
}
