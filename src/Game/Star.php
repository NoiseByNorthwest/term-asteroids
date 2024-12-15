<?php

namespace NoiseByNorthwest\TermAsteroids\Game;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapBuilder;
use NoiseByNorthwest\TermAsteroids\Engine\GameObject;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;

class Star extends GameObject
{
    public function __construct()
    {
        $brightness = RandomUtils::getRandomInt(16, 255);

        parent::__construct(
            new Sprite(
                1,
                1,
                [
                    [
                        'name' => 'default',
                        'frames' => [
                            [
                                'bitmap' => (new BitmapBuilder(
                                    [
                                        'M',
                                    ],
                                    [' ' => -1, 'M' => [$brightness, $brightness, $brightness]]
                                ))
                                    ->build(),
                            ]
                        ]
                    ]
                ],
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters, float $age) {
                            $renderingParameters->setBrightness(
                                0.5 + 0.5 * abs(sin(($this->getId() + $age) * 2))
                            );
                        },
                    ),
                ]
            ),
            movers: [
                new Mover(
                    new Vec2(-1, 0),
                    new Accelerator(
                        $brightness * 0.5,
                        0.1,
                        0,
                    )
                ),
            ]
        );
    }

    public function init(): void
    {
        $this->getPos()->set(
            RandomUtils::getRandomInt(2, $this->getScreen()->getWidth() - 3),
            RandomUtils::getRandomInt(2, $this->getScreen()->getHeight() - 3),
        );

        $this->setInitialized();
    }

    public function terminateWhenOffScreen(): bool
    {
        return false;
    }

    protected function doUpdate(): void
    {
        if ($this->getPos()->getX() < 0) {
            $this->getPos()->set(
                $this->getScreen()->getWidth() - 1,
                RandomUtils::getRandomInt(2, $this->getScreen()->getHeight() - 3)
            );
        }
    }
}
