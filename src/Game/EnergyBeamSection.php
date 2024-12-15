<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffectHelper;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Smoke\Smoke;
use NoiseByNorthwest\TermAsteroids\Game\Smoke\VerySmallSmoke;

class EnergyBeamSection extends GameObject
{
    public const WIDTH = 4;

    public const HEIGHT = 41;

    public const PHASE_COUNT = 6;

    private static array $hitColorComponents = [
        255,
        0,
        0
    ];

    private int $initiatorId;

    private int $level = 0;

    private float $lastHitTime;

    public function __construct()
    {
        $phaseCount = self::PHASE_COUNT;

        parent::__construct(
            new Sprite(
                self::WIDTH,
                self::HEIGHT,
                array_map(
                    fn (array $e) => [
                        'name' => $e['animationName'],
                        'frames' => [
                            [
                                'bitmap' => Bitmap::generateVerticalGradient(
                                    self::WIDTH,
                                    self::HEIGHT,
                                    [
                                        '0' => [0, 0, 0, 0],
                                        (string) ($e['gradientStart'] - 0.01) => [0, 0, 0, 0],
                                        (string) $e['gradientStart'] => [0, 64, 0, 4],
                                        (string) ($e['gradientStart'] + 0.15) => [0, 64, 0, 32],
                                        '1' => [128, 255, 128]
                                    ],
                                    loopBack: true
                                )
                            ],
                        ],
                    ],
                    array_map(
                        fn (int $e) => [
                            'animationName' => (string) ($e + 1),
                            'gradientStart' => Math::lerp(0.7, 0.03, $e / ($phaseCount - 1))
                        ],
                        array_keys(array_fill(0, $phaseCount, 0))
                    )
                ),
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setGlobalAlpha(Math::roundToInt(Math::lerp(
                                64,
                                192,
                                (sin($age + 8 * $this->getPos()->getX() / $this->getScreen()->getWidth()) + 1) / 2
                            )));

                            $currentTime = Timer::getCurrentGameTime();
                            if ($currentTime - $this->lastHitTime < 0.4) {
                                $hitColorAlphaRatio = 1 - Math::bound(($currentTime - $this->lastHitTime) / 0.4);
                                $renderingParameters->setGlobalBlendingColor(
                                    ColorUtils::createColor(
                                        self::$hitColorComponents,
                                        alpha: (int) (255 * $hitColorAlphaRatio)
                                    )
                                );

                                $renderingParameters->setPersisted(true);
                                $renderingParameters->setPersistedColor(ColorUtils::createColor(
                                 self::$hitColorComponents,
                                    alpha: (int) (80 * $hitColorAlphaRatio)
                                ));
                            }

                            $height = $this->getSprite()->getHeight();

                            $maxAmplitude = Math::roundToInt(Math::lerp(3, 10, ($this->level - 1) / (self::PHASE_COUNT - 1)));
                            $amplitude = 2 + ($maxAmplitude - 2) * (0.5 + 0.5 * sin(3 * Timer::getCurrentGameTime()));

                            $horizontalBackgroundDistortionOffsets = SpriteEffectHelper::generateHorizontalDistortionOffsets(
                                height: $height,
                                amplitude: $amplitude,
                                shearFactor: 1 + 0.2 * ($maxAmplitude - $amplitude)
                            );

                            $renderingParameters->setHorizontalBackgroundDistortionOffsets($horizontalBackgroundDistortionOffsets);
                        },
                    ),
                ],
            ),
            zIndex: 3
        );
    }

    public function init(GameObject $initiator): void
    {
        $this->initiatorId = $initiator->getId();
        $this->level = 0;
        $this->lastHitTime = 0;

        $this->setInitialized();
        $this->setActive(false);
    }

    public function terminateWhenOffScreen(): bool
    {
        return false;
    }

    public function getLastHitTime(): float
    {
        return $this->lastHitTime;
    }

    protected function doUpdate(): void
    {
        $initiator = $this->getGame()->getGameObjectOrNull($this->initiatorId);

        if (! $initiator || $initiator->isTerminated()) {
            $this->setTerminated();

            return;
        }

        self::$hitColorComponents = ColorUtils::colorToRgba(ColorUtils::createColorWithinGradient(
            [
                '0' => [255, 0, 0],
                '0.5' => [255, 32, 0],
                '1' => [255, 0, 192],
            ],
            0.5 + 0.5 * sin($this->getPos()->getX() * 0.05 + 15 * Timer::getCurrentGameTime())
        ));

        $currentTime = Timer::getCurrentGameTime();
        if ($currentTime - $this->lastHitTime < 0.4) {
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

            if (RandomUtils::getRandomBool(0.35)) {
                $smoke = $this->getPool()->acquire(
                    VerySmallSmoke::class,
                    pos: new Vec2(
                        $this->getBoundingBox()->getRight(),
                        $this->getPos()->getY(),
                    ),
                    initializer: fn (Smoke $e) => $e->init(
                        blendingColor: ColorUtils::createColor(self::$hitColorComponents, alpha: 92),
                        baseVelocity: 0
                    ),
                    withLimit: true
                );

                if ($smoke) {
                    $this->getGame()->addGameObject($smoke);
                }
            }

            $otherGameObject->hit(Math::roundToInt(1 * Spaceship::getMainWeaponPowerIndex() * $this->level));

            $this->lastHitTime = $currentTime;
        }
    }

    public function setLevel(int $level): void
    {
        if ($this->level === $level) {
            return;
        }

        $this->level = $level % (count($this->getSprite()->getAnimations()) + 1);

        if ($this->level > 0) {
            $this->getSprite()->setCurrentAnimationName((string) $this->level);
        }

        $this->updateHitBoxes();
    }

    protected function resolveHitBoxes(): array
    {
        return [
            $this->getBoundingBox()->copy()->grow(widthFactor: 1, heightFactor: (($this->level + 2) * 2 - 1) / self::HEIGHT)
        ];
    }
}
