<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Flame;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\ClassUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Smoke\Smoke;

abstract class Flame extends GameObject
{
    private ?int $targetObjectId;

    private Vec2 $relativePos;

    private ?int $blendingColor;

    private float $smokeEmissionPeriod = 0;

    private float $lastSmokeEmissionTime = 0;

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
                        'loopBack' => true,
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
                                            '0.4' => [255, 0, 0, 128],
                                            '0.6' => [255, 128, 0],
                                            '1' => [255, 255, 0]
                                        ],
                                        seed: [static::class, $seed],
                                        shift: $e / 5,
                                        radius: Math::lerp(0.1, 1.2, ($e + 1) / $count)
                                    ),
                                    array_keys(array_fill(0, $count, null))
                                ))(Math::roundToInt(Math::lerp(8, 20, static::getSize() / HugeFlame::getSize()))),
                            ],
                        )
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setBlendingColor($this->blendingColor);
                        },
                    ),
                ]
            ),
            function () {
                return new Accelerator(
                    0,
                    0.1,
                    0,
                );
            },
            fn () => new Vec2(1, -0.3)
        );
    }

    public function init(
        Vec2 $pos ,
        ?GameObject $targetObject = null,
        ?int $blendingColor = null,
        bool $repeated = false,
        float $smokeEmissionPeriod = 10
    ): void {
        $this->targetObjectId = $targetObject?->getId();
        $this->relativePos = $pos->copy()->subVec($targetObject?->getPos() ?? new Vec2(0, 0));
        $this->blendingColor = $blendingColor;
        $this->smokeEmissionPeriod = $smokeEmissionPeriod;
        $this->lastSmokeEmissionTime = 0;

        $this->setRepeated($repeated);

        $this->updatePos(true);

        $this->setInitialized();
    }

    public function setRepeated(bool $repeated)
    {
        $this->getSprite()->getCurrentAnimation()->setRepeated($repeated);
    }

    protected function doUpdate(): void
    {
        $this->updatePos();

        if ($this->getSprite()->getCurrentAnimation()->isFinished()) {
            $this->setTerminated();

            return;
        }

        $currentTime = Timer::getCurrentFrameStartTime();

        if (
            $currentTime - $this->lastSmokeEmissionTime > $this->smokeEmissionPeriod
        ) {
            $smoke = $this->getPool()->acquire(static::getSmokeClassName(), true);
            if ($smoke) {
                $smoke->init(
                    $this->getPos()->addVec(
                        $this->getSprite()->getSize()->copy()->minVec($smoke->getSprite()->getSize())->mul(0.5)
                    ),
                    $this->blendingColor
                );
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
