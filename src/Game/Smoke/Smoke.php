<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Smoke;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\ClassUtils;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\ScreenAreaStats;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffectHelper;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;

abstract class Smoke extends GameObject
{
    private Vec2 $originalPos;

    private const FRAME_DURATION = 0.03;

    private ?int $blendingColor;

    private float $duration;

    private float $baseVelocity;

    public static function shouldBeExcluded(Vec2 $pos, float $allowedResourceConsumptionRatio): bool
    {
        return ScreenAreaStats::get('smoke', $pos) > 2 + 14 * $allowedResourceConsumptionRatio;
    }

    abstract public static function getSize(): int;

    public static function getFlameSizeRatio(): float
    {
        return 1.6;
    }

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
                                'duration' => self::FRAME_DURATION,
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
                                    Math::roundToInt(Math::lerp(40, 60, $size / HugeSmoke::getSize()))
                                ),
                            ],
                        )
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters) {
                            $renderingParameters->setGlobalBlendingColor($this->blendingColor);

                            $completionRatio = $this->getSprite()->getCurrentAnimation()->getCompletionRatio();

                            $renderingParameters->setGlobalAlpha((int) Math::lerpPath([
                                '0.0' => 220,
                                '0.6' => 220,
                                '1.0' => 0,
                            ], $completionRatio));

                            $height = $this->getSprite()->getHeight();
                            $horizontalBackgroundDistortionOffsets = SpriteEffectHelper::generateHorizontalDistortionOffsets(
                                height: $height,
                                maxAmplitude: Math::lerpPath([
                                    '0.0' => 0,
                                    '5.0' => 22,
                                    '1.0' => 0,
                                ],$completionRatio),
                                timeFactor: 5,
                                shearFactor: 15 * ($height / HugeSmoke::getSize())
                            );

                            $renderingParameters->setHorizontalBackgroundDistortionOffsets($horizontalBackgroundDistortionOffsets);
                        },
                    )
                ],
            ),
            zIndex: 1
        );
    }

    public function init(
        ?int $blendingColor = null,
        ?float $duration = null,
        float $baseVelocity = 1,
    ): void {
        $this->originalPos = $this->getPos()->copy();
        ScreenAreaStats::inc('smoke', $this->originalPos);

        $this->blendingColor = $blendingColor;
        $this->duration = $duration ?? count($this->getSprite()->getCurrentAnimation()->getFrames()) * self::FRAME_DURATION;
        $this->baseVelocity = $baseVelocity;

        $this->setMovers([
            new Mover(
                new Vec2(1, -0.3),
                new Accelerator(
                    $this->baseVelocity * RandomUtils::getRandomInt(10, 30),
                    0.1,
                    0,
                )
            ),
        ]);

        $this->setInitialized();
    }

    protected function doUpdate(): void
    {
        if (Timer::getCurrentGameTime() - $this->getCreationTime() >= $this->duration) {
            $this->setTerminated();
            ScreenAreaStats::dec('smoke', $this->originalPos);
        }
    }
}
