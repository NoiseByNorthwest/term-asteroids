<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\ClassUtils;
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
use NoiseByNorthwest\TermAsteroids\Game\Smoke\Smoke;

abstract class Flame extends GameObject
{
    private Vec2 $originalPos;

    private ?int $targetObjectId;

    private Vec2 $relativePos;

    private ?int $blendingColor;

    private float $smokeEmissionDelay = 0;

    private float $smokeEmissionPeriod = 0;

    private float $lastSmokeEmissionTime = 0;

    public static function shouldBeExcluded(Vec2 $pos, float $allowedResourceConsumptionRatio): bool
    {
        return ScreenAreaStats::get('flame', $pos) > 2 + 14 * $allowedResourceConsumptionRatio;
    }

    abstract public static function getSize(): int;

    /**
     * @return class-string<Smoke>
     */
    abstract public static function getSmokeClassName(): string;

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
                                            '0.1' => [0, 0, 0, 0],
                                            '0.5' => [255, 0, 0, 192],
                                            '0.65' => [255, 128, 0, 220],
                                            '0.85' => [255, 216, 0],
                                            '1' => [255, 255, 0]
                                        ],
                                        seed: [static::class, $seed],
                                        shift: $e * 0.08,
                                        radius: Math::lerpPath([
                                            '0.0' => 0.2,
                                            '0.3' => 0.8,
                                            '0.4' => 1.0,
                                            '1.0' => 1.0,
                                        ], $e / ($count - 1)),
                                        zFactor: Math::lerpPath([
                                            '0.0' => 0.3,
                                            '0.3' => 1,
                                            '0.4' => 1,
                                            '0.5' => 0.5,
                                            '1.0' => 0.4,
                                        ], $e / ($count - 1)),
                                        maxScaleCount: 10
                                    ),
                                    array_keys(array_fill(0, $count, null))
                                ))(Math::roundToInt(
                                    40 * Math::lerp(1, 3, static::getSize() / HugeFlame::getSize())
                                )),
                            ],
                        )
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters) {
                            $renderingParameters->setGlobalAlpha((int) Math::lerpPath([
                                '0.0' => 255,
                                '0.8' => 255,
                                '1.0' => 0,
                            ], $this->getSprite()->getCurrentAnimation()->getCompletionRatio()));

                            $renderingParameters->setGlobalBlendingColor($this->blendingColor);

                            $height = $this->getSprite()->getHeight();
                            $horizontalBackgroundDistortionOffsets = SpriteEffectHelper::generateHorizontalDistortionOffsets(
                                height: $height,
                                maxAmplitude: $height * 0.04,
                                timeFactor: 5,
                                shearFactor: 3 * ($height / HugeFlame::getSize())
                            );

                            $renderingParameters->setHorizontalDistortionOffsets($horizontalBackgroundDistortionOffsets);
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
        ?GameObject $targetObject = null,
        ?int $blendingColor = null,
        bool $repeated = false,
        float $smokeEmissionDelay = 0.1,
        float $smokeEmissionPeriod = 0.3,
    ): void {
        $this->originalPos = $this->getPos()->copy();
        ScreenAreaStats::inc('flame', $this->originalPos);

        $this->targetObjectId = $targetObject?->getId();
        $this->relativePos = $this->getPos()->copy()->subVec($targetObject?->getPos() ?? new Vec2(0, 0));
        $this->blendingColor = $blendingColor;
        $this->smokeEmissionDelay = $smokeEmissionDelay;
        $this->smokeEmissionPeriod = $smokeEmissionPeriod;
        $this->lastSmokeEmissionTime = Timer::getCurrentGameTime();

        $this->setRepeated($repeated);

        $this->updatePos(true);

        $this->setInitialized();
    }

    public function setRepeated(bool $repeated): void
    {
        $this->getSprite()->getCurrentAnimation()->setRepeated($repeated);
    }

    protected function doUpdate(): void
    {
        $this->updatePos();

        if ($this->getSprite()->getCurrentAnimation()->isFinished()) {
            $this->setTerminated();
            ScreenAreaStats::dec('flame', $this->originalPos);

            return;
        }

        $currentTime = Timer::getCurrentGameTime();

        if (
            $currentTime - $this->getCreationTime() > $this->smokeEmissionDelay
                && $currentTime - $this->lastSmokeEmissionTime > $this->smokeEmissionPeriod
        ) {
            $smoke = $this->getPool()->acquire(
                static::getSmokeClassName(),
                pos: $this->getPos(),
                initializer: fn (Smoke $e) => $e->init(
                    $this->blendingColor
                ),
                withLimit: true
            );

            if ($smoke) {
                $this->getGame()->addGameObject($smoke);

                $this->lastSmokeEmissionTime = $currentTime;
            }
        }
    }

    private function updatePos(bool $init = false): void
    {
        if ($this->targetObjectId !== null) {
            $targetObject = $this->getGame()->getGameObjectOrNull($this->targetObjectId);
            if ($init) {
                assert($targetObject !== null);
            }

            if ($targetObject) {
                $this->getPos()->setVec($targetObject->getPos()->copy()->addVec($this->relativePos));
            } else {
                $this->setRepeated(false);
            }
        } else {
            $this->getPos()->setVec($this->relativePos);
        }
    }
}
