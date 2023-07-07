<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\ClassUtils;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;

abstract class Smoke extends GameObject
{
    private ?int $blendingColor;

    abstract public static function getSize(): int;

    public static function getMaxVariantCount(): int
    {
        return 1;
    }

    public static function warmCaches(): void
    {
        foreach (ClassUtils::getLocalChildClassNames(self::class) as $childClassName) {
            for ($i = 0; $i < $childClassName::getMaxVariantCount(); $i++) {
                new $childClassName($i);
            }
        }
    }

    public function __construct(?int $seed = null)
    {
        $seed = $seed ?? RandomUtils::getRandomInt(0, self::getMaxVariantCount() - 1);
        $size = static::getSize();

        $color = ColorUtils::createColor([128, 128, 128]);

        parent::__construct(
            new Sprite(
                $size,
                $size,
                [
                    [
                        'name' => 'default',
                        'repeat' => false,
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
                                            '0.2' => ColorUtils::applyEffects($color, globalAlpha: 128, brightness: 0.1),
                                            '0.6' => ColorUtils::applyEffects($color, brightness: 0.3),
                                            '1' => ColorUtils::applyEffects($color, brightness: 0.6)
                                        ],
                                        seed: [static::class, $seed],
                                        shift: $e / 10,
                                        radius: Math::lerp(0.3, 1.2, ($e + 1) / $count),
                                        maxScaleCount: 4,
                                    ),
                                    array_keys(array_fill(0, $count, null))
                                ))(60),
                            ],
                        )
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setBlendingColor($this->blendingColor);

                            $alphaRatio = Math::lerpPath(
                                [
                                    '0' => 0,
                                    '0.1' => 0.8,
                                    '0.2' => 1,
                                    '0.5' => 1,
                                    '1' => 0,
                                ],
                                $this->getSprite()->getCurrentAnimation()->getCurrentFrameIdx() / (count($this->getSprite()->getCurrentAnimation()->getFrames()) - 1)
                            );

                            $alphaRatio *= Math::lerp(0.2, 0.8, Math::bound(static::getSize() / HugeSmoke::getSize()));

                            $alpha = (int) (255 * $alphaRatio);

                            $renderingParameters->setGlobalAlpha($alpha);

                            $horizontalBackgroundDistortionOffsets = [];
                            $height = $this->getSprite()->getHeight();
                            $screenHeight = $this->getScreen()->getHeight();
                            $posY = $this->getPos()->getY();
                            for ($i = 0; $i < $height; $i++) {
                                $offset = 1 + (int) round(
                                    1 * $alphaRatio
                                        * sin(5 * Timer::getCurrentFrameStartTime() + 20 * M_PI * ($posY + $i) / $screenHeight)
                                );

                                assert($offset >= 0);

                                $horizontalBackgroundDistortionOffsets[] = $offset;
                            }

                            $renderingParameters->setHorizontalBackgroundDistortionOffsets($horizontalBackgroundDistortionOffsets);
                        },
                    )
                ],
            ),
            function () {
                return new Accelerator(
                    RandomUtils::getRandomInt(10, 30),
                    0.1,
                    0,
                );
            },
            fn () => new Vec2(-1, -0.3),
            zIndex: 1
        );
    }

    public function init(
        Vec2 $pos,
        ?int $blendingColor = null
    ): void {
        $this->getPos()->setVec($pos);
        $this->blendingColor = $blendingColor;
        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        if ($this->getSprite()->getCurrentAnimation()->isFinished()) {
            $this->setTerminated();
        }
    }
}
