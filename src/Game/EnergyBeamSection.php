<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;

class EnergyBeamSection extends GameObject
{
    public const WIDTH = 4;

    public const HEIGHT = 41;

    private int $initiatorId;

    private int $level = 0;

    private float $lastHitTime;

    public function __construct()
    {
        $phaseCount = 6;
        parent::__construct(
            new Sprite(
                self::WIDTH,
                self::HEIGHT,
                array_map(
                    fn (int $e) => [
                        'name' => (string) ($e + 1),
                        'frames' => [
                            [
                                'bitmap' => Bitmap::generateVerticalGradient(
                                    self::WIDTH,
                                    self::HEIGHT,
                                    [
                                        '0' => [0, 0, 0, 0],
                                        (string) Math::lerp(0.7, 0.2, $e / ($phaseCount - 1))  => [0, 64, 0, 32],
                                        '1' => [128, 255, 128]
                                    ],
                                    loopBack: true
                                )
                            ],
                        ],
                    ],
                    array_keys(array_fill(0, $phaseCount, 0))
                ),
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setGlobalAlpha(Math::roundToInt(Math::lerp(
                                64,
                                192,
                                (sin($age + 8 * $this->getPos()->getX() / $this->getScreen()->getWidth()) + 1) / 2
                            )));

                            $currentTime = Timer::getCurrentFrameStartTime();
                            if ($currentTime - $this->lastHitTime < 0.4) {
                                $renderingParameters->setBlendingColor(
                                    ColorUtils::createColor([
                                        255,
                                        0,
                                        0,
                                       (int) (255 * (1 - Math::bound(($currentTime - $this->lastHitTime) / 0.4)))
                                    ])
                                );
                            }

                            $horizontalBackgroundDistortionOffsets = [];
                            $height = $this->getSprite()->getHeight();
                            $screenHeight = $this->getScreen()->getHeight();
                            $posY = $this->getPos()->getY();
                            for ($i = 0; $i < $height; $i++) {
                                $offset = 1 + (int) round(
                                    1.5
                                    * sin(5 * Timer::getCurrentFrameStartTime() + 20 * M_PI * ($posY + $i) / $screenHeight)
                                );

                                assert($offset >= 0);

                                $horizontalBackgroundDistortionOffsets[] = $offset;
                            }

                            $renderingParameters->setHorizontalBackgroundDistortionOffsets($horizontalBackgroundDistortionOffsets);
                        },
                    ),
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setPersisted(true);
                        },
                    ),
                ],
            ),
            function () {
                return new Accelerator(
                    0,
                    0.1,
                    0,
                );
            },
            zIndex: 2
        );
    }

    public function init(GameObject $initiator, Vec2 $pos)
    {
        $this->initiatorId = $initiator->getId();
        $this->level = 0;
        $this->lastHitTime = 0;

        $this->getPos()->setVec($pos);

        $this->updateHitBoxes();

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

        $currentTime = Timer::getCurrentFrameStartTime();
        if ($currentTime - $this->lastHitTime < 0.4) {
            return;
        }

        foreach ($this->getOtherGameObjects() as $otherGameObject) {
            if (!$otherGameObject instanceof DamageableGameObject) {
                continue;
            }

            if ($otherGameObject->getId() === $this->initiatorId) {
                continue;
            }

            if (! $otherGameObject->collidesWith($this)) {
                continue;
            }

            $otherGameObject->hit( Math::roundToInt(1 * Player::getMainWeaponPowerIndex() * $this->level));

            $this->lastHitTime = $currentTime;
        }
    }

    public function setLevel(int $level)
    {
        $this->level = $level % (count($this->getSprite()->getAnimations()) + 1);
        if ($this->level > 0) {
            $this->getSprite()->setCurrentAnimationName((string) $this->level);
        }

        $this->updateHitBoxes();
    }

    private function updateHitBoxes(): void
    {
        $this->setHitBoxes([
            $this->getBoundingBox()->copy()->grow(widthFactor: 1, heightFactor: (($this->level + 2) * 2 - 1) / self::HEIGHT)
        ]);
    }
}
