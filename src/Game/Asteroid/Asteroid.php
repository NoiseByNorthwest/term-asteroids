<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Asteroid;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\CacheUtils;
use NoiseByNorthwest\TermAsteroids\Engine\ClassUtils;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\Mover;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\DamageableGameObject;
use NoiseByNorthwest\TermAsteroids\Game\Flame\Flame;
use NoiseByNorthwest\TermAsteroids\Game\Spaceship;

abstract class Asteroid extends DamageableGameObject
{
    private ?int $initiatorId = null;

    abstract public static function getSize(): int;

    abstract public static function getMaxVariantCount(): int;

    /**
     * @return class-string<Flame>
     */
    abstract public static function getFlameClassName(): string;

    public static function getMaxHealth(): int
    {
        return (int) ceil((static::getSize() * 0.4) ** 3);
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
        $seed = $seed ?? RandomUtils::getRandomInt(0, static::getMaxVariantCount() - 1);
        $size = static::getSize();

        $color = ColorUtils::createColor('#83490f');

        parent::__construct(
            new Sprite(
                $size,
                $size,
                CacheUtils::memoize([static::class, __METHOD__, $seed], fn () => [
                    [
                        'name' => 'default',
                        'frames' => array_map(
                            fn (Bitmap $e) => [
                                'duration' => 0.05,
                                'bitmap' => $e,
                            ],
                            Bitmap::generateCenteredRotationAnimation(
                                BitmapNoiseGenerator::generate(
                                    $size,
                                    $size,
                                    [
                                        '0' => [0, 0, 0, 0],
                                        '0.3' => [0, 0, 0, 0],
                                        '0.3001' => ColorUtils::applyEffects($color, brightness: 0.1),
                                        '0.5' => ColorUtils::applyEffects($color, brightness: 0.5),
                                        '1' => ColorUtils::applyEffects($color, brightness: 1),
                                    ],
                                    seed: [static::class, $seed],
                                    radius: 1.2,
                                ),
                                Math::bound(Math::roundToInt(($size ** 2) * 0.12), 1, INF)
                            ),
                        )
                    ]
                ]),
                [
                    new SpriteEffect(
                        function (SpriteRenderingParameters $renderingParameters) use($color, $size) {
                            if ($this->isGoingToLeftScreenSide()) {
                                $renderingParameters->setBrightness(
                                    Math::lerpPath([
                                        '0.0' => 1,
                                        '0.8' => 1,
                                        '1.0' => 0,
                                    ], Math::bound($this->getPos()->getX() / $this->getScreen()->getWidth()))
                                );
                            }

                            $verticalBlendingColors = [];

                            for ($i = 0; $i < $size; $i++) {
                                // FIXME could be computed once
                                $verticalBlendingColors[$i] = ColorUtils::createColor([0, 0, 0, (int) Math::lerpPath([
                                    '0.0' => 0,
                                    '0.3' => 0,
                                    '0.42' => 24,
                                    '0.55' => 96,
                                    '0.7' => 192,
                                    '1.0' => 255,
                                ], $i / ($size - 1))]);
                            }

                            $renderingParameters->setVerticalBlendingColors($verticalBlendingColors);

                            $renderingParameters->setPersisted(true);
                            $renderingParameters->setPersistedColor(
                                ColorUtils::createColor(
                                    ColorUtils::applyEffects($color, brightness: 0.6),
                                    48
                                )
                            );
                        },
                    ),
                ]
            ),
        );
    }

    public function init(float $velocity, ?self $initiator = null, ?Vec2 $dir = null): void
    {
        $this->initiatorId = $initiator?->getId();

        $this->setMovers([
            new Mover(
                $dir ?? new Vec2(-1, 0),
                new Accelerator(
                    $velocity,
                    0.1,
                    0,
                )
            ),
        ]);

        $this->getSprite()->getCurrentAnimation()->selectRandomFrame();

        $this->damageableObjectInit();
    }

    protected function resolveHitBoxes(): array
    {
        return [
            $this->getBoundingBox()->copy()->grow(widthFactor: 0.4, heightFactor: 0.7),
            $this->getBoundingBox()->copy()->grow(widthFactor: 0.7, heightFactor: 0.4),
        ];
    }

    protected function canCollide(DamageableGameObject $otherGameObject): bool
    {
        if (! parent::canCollide($otherGameObject)) {
            return false;
        }

        if (Timer::getCurrentGameTime() - $this->getCreationTime() < 0.6) {
            return false;
        }

        if ($otherGameObject instanceof self) {
            if ($this->initiatorId !== null && $this->initiatorId === $otherGameObject->initiatorId) {
                return false;
            }

            if (
                $this->isGoingToLeftScreenSide() &&
                $this->getPos()->getX() > $this->getScreen()->getWidth() * 0.8
            ) {
                return false;
            }
        }

        return true;
    }

    protected function onCollision(DamageableGameObject $other, int $otherHealthBeforeCollision): void
    {
        parent::onCollision($other, $otherHealthBeforeCollision);

        $this->hit($otherHealthBeforeCollision);
    }

    protected function onDestructionPhaseEnd(): void
    {
        $destructionPos = $this->getPos();

        $minSpaceshipDist = INF;
        foreach ($this->getGame()->getGameObjects() as $gameObject) {
            if ($gameObject instanceof Spaceship) {
                $spaceshipDist = $gameObject->getPos()->copy()->subVec($destructionPos)->computeLength();
                if ($spaceshipDist < $minSpaceshipDist) {
                    $minSpaceshipDist = $spaceshipDist;
                }
            }
        }

        $flame = $this->getPool()->acquire(
            static::getFlameClassName(),
            pos: $destructionPos,
            initializer: fn (Flame $e) => $e->init(targetObject: $this),
        );

        $this->getGame()->addGameObject($flame);

        $fragmentClassName = self::getNearestSmallerAsteroidClassName();
        if ($fragmentClassName !== null) {
            assert(is_subclass_of($fragmentClassName, self::class));
            $generateRandomDirComponent = fn() => RandomUtils::getRandomFloat(1, 5);
            $fragmentDist = static::getSize() * 0.11;
            foreach ([
                 [
                     'pos' => $destructionPos->copy()->add(-$fragmentDist, -$fragmentDist),
                     'dir' => new Vec2(- $generateRandomDirComponent(), - $generateRandomDirComponent()),
                 ],
                 [
                     'pos' => $destructionPos->copy()->add(-$fragmentDist, $fragmentDist),
                     'dir' => new Vec2(- $generateRandomDirComponent(), $generateRandomDirComponent()),
                 ],
                 [
                     'pos' => $destructionPos->copy()->add($fragmentDist, -$fragmentDist),
                     'dir' => new Vec2($generateRandomDirComponent(), - $generateRandomDirComponent()),
                 ],
                 [
                     'pos' => $destructionPos->copy()->add($fragmentDist, $fragmentDist),
                     'dir' => new Vec2($generateRandomDirComponent(), $generateRandomDirComponent()),
                 ],
            ] as $fragmentParameters) {
                $fragment = $this->getPool()->acquire(
                    $fragmentClassName,
                    pos: $fragmentParameters['pos'],
                    initializer: fn (Asteroid $e) => $e->init(
                        RandomUtils::getRandomFloat(15, 40),
                        $this,
                        $fragmentParameters['dir'],
                    ),
                    withLimit: $minSpaceshipDist > 30,
                    withAdaptivePerformanceLimit: false
                );

                if ($fragment) {
                    $this->getGame()->addGameObject($fragment);

//                    $fragmentFlame = $this->getPool()->acquire(
//                        $fragmentClassName::getFlameClassName(),
//                        pos: $fragmentParameters['pos']->copy()->add(
//                            RandomUtils::getRandomFloat(0, static::getSize() * 0.2),
//                            RandomUtils::getRandomFloat(0, static::getSize() * 0.2),
//                        ),
//                        initializer: fn (Flame $e) => $e->init(),
//                        withLimit: true
//                    );
//
//                    if ($fragmentFlame) {
//                        $this->getGame()->addGameObject($fragmentFlame);
//                    }
                }
            }
        }
    }

    private function isGoingToLeftScreenSide(): bool
    {
        $dir = $this->getSingleMover()->getDir();
        return
            $dir->getY() === 0.0 && $dir->getX() < 0.0
        ;
    }

    private static function getNearestSmallerAsteroidClassName(): ?string
    {
        $childClassNames = ClassUtils::getLocalChildClassNames(self::class);
        usort($childClassNames, function ($a, $b) {
            assert(is_subclass_of($a, self::class));
            assert(is_subclass_of($b, self::class));
            return $b::getSize() - $a::getSize();
        });

        foreach ($childClassNames as $childClassName) {
            assert(is_subclass_of($childClassName, self::class));
            if ($childClassName::getSize() < static::getSize()) {
                return $childClassName;
            }
        }

        return null;
    }
}
