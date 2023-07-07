<?php

namespace NoiseByNorthwest\TermAsteroids\Game\Asteroid;

use NoiseByNorthwest\TermAsteroids\Engine\Accelerator;
use NoiseByNorthwest\TermAsteroids\Engine\Bitmap;
use NoiseByNorthwest\TermAsteroids\Engine\BitmapNoiseGenerator;
use NoiseByNorthwest\TermAsteroids\Engine\CacheUtils;
use NoiseByNorthwest\TermAsteroids\Engine\ClassUtils;
use NoiseByNorthwest\TermAsteroids\Engine\ColorUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Math;
use NoiseByNorthwest\TermAsteroids\Engine\RandomUtils;
use NoiseByNorthwest\TermAsteroids\Engine\Sprite;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteEffect;
use NoiseByNorthwest\TermAsteroids\Engine\SpriteRenderingParameters;
use NoiseByNorthwest\TermAsteroids\Engine\Timer;
use NoiseByNorthwest\TermAsteroids\Engine\Vec2;
use NoiseByNorthwest\TermAsteroids\Game\Bonus;
use NoiseByNorthwest\TermAsteroids\Game\DamageableGameObject;
use NoiseByNorthwest\TermAsteroids\Game\Flame\Flame;

abstract class Asteroid extends DamageableGameObject
{
    private ?int $initiatorId = null;

    private Vec2 $dir;

    private float $velocity;

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
                        function (SpriteRenderingParameters $renderingParameters, float $age) use($color) {
                            $brightnessFactor = 1;
                            if ($this->isGoingToLeftScreenSide()) {
                                $brightnessFactor = Math::lerp(
                                    0.1,
                                    1,
                                    Math::bound(
                                        ($this->getScreen()->getWidth() - $this->getPos()->getX())
                                        / ($this->getScreen()->getWidth() * 0.3)
                                    )
                                );
                            }

                            $renderingParameters->setBrightness(
                                $brightnessFactor * Math::lerp(
                                    0.85,
                                    1,
                                    abs(sin(($this->getId() + $age) * 2))
                                )
                            );
                            $renderingParameters->setPersisted(true);
                            $renderingParameters->setPersistedColor(
                                ColorUtils::createColor(
                                    ColorUtils::applyEffects($color, brightness: 0.6),
                                    (int) Math::lerp(72, 160, Math::bound($this->velocity / 300))
                                )
                            );
                        },
                    ),
                ]
            ),
            function () {
                return new Accelerator(
                    $this->velocity,
                    0.1,
                    0,
                );
            },
            fn () => $this->dir
        );
    }

    public function init(Vec2 $pos, float $velocity, ?self $initiator = null, ?Vec2 $dir = null)
    {
        $this->getPos()->setVec($pos);
        $this->initiatorId = $initiator?->getId();
        $this->dir = $dir ?? new Vec2(-1, 0);
        $this->velocity = $velocity;
        $this->getSprite()->getCurrentAnimation()->selectRandomFrame();

        $this->setHitBoxes([
            $this->getBoundingBox()->copy()->grow(widthFactor: 0.4, heightFactor: 0.7),
            $this->getBoundingBox()->copy()->grow(widthFactor: 0.7, heightFactor: 0.4),
        ]);

        $this->setInitialized();
    }

    protected function canDamage(DamageableGameObject $otherGameObject): bool
    {
        if ($otherGameObject instanceof self) {
            if (Timer::getCurrentFrameStartTime() - $otherGameObject->getCreationTime() < 0.5) {
                return false;
            }

            if (
                $otherGameObject->isGoingToLeftScreenSide() &&
                $this->isGoingToLeftScreenSide() &&
                $otherGameObject->getPos()->getX() > $this->getScreen()->getWidth() * 0.6 &&
                $this->getPos()->getX() > $this->getScreen()->getWidth() * 0.6
            ) {
                return false;
            }
        }

        if ($this->initiatorId !== null && $otherGameObject instanceof self) {
            return $this->initiatorId !== $otherGameObject->initiatorId;
        }

        return parent::canDamage($otherGameObject);
    }

    protected function onDestruction(): void
    {
        $destructionPos = $this->getPos();

        $fragmentClassName = self::getNearestSmallerAsteroidClassName();
        if ($fragmentClassName !== null) {
            assert(is_subclass_of($fragmentClassName, self::class));
            foreach ([
                 new Vec2(- RandomUtils::getRandomFloat(0.8, 1.2), - RandomUtils::getRandomFloat(0.8, 1.2)),
                 new Vec2(- RandomUtils::getRandomFloat(0.8, 1.2), RandomUtils::getRandomFloat(0.8, 1.2)),
                 new Vec2(RandomUtils::getRandomFloat(0.8, 1.2), - RandomUtils::getRandomFloat(0.8, 1.2)),
                 new Vec2(RandomUtils::getRandomFloat(0.8, 1.2), RandomUtils::getRandomFloat(0.8, 1.2)),
            ] as $dir) {
                $fragment = $this->getPool()->acquire($fragmentClassName, true);
                if ($fragment) {
                    $fragment->init(
                        $destructionPos,
                        RandomUtils::getRandomFloat(15, 80),
                        $this,
                        $dir,
                    );

                    $this->getGame()->addGameObject($fragment);
                }
            }
        }

        $flame = $this->getPool()->acquire(static::getFlameClassName(), true);
        if ($flame) {
            $flame->init($destructionPos);
            $this->getGame()->addGameObject($flame);
        }

        $bonusCount = Math::roundToInt(RandomUtils::getRandomFloat(0, (static::getSize() ** 2) / 3000));
        // no bonus on destruction for now
        $bonusCount = 0;
        $bonusClusterRadius = static::getSize() * 0.1;
        for ($i = 0; $i < $bonusCount; $i++) {
            $bonus = $this->getPool()->acquire(Bonus::class);

            $bonus->init($destructionPos->add(
                RandomUtils::getRandomFloat(-$bonusClusterRadius, $bonusClusterRadius),
                RandomUtils::getRandomFloat(-$bonusClusterRadius, $bonusClusterRadius),
            ));

            $this->getGame()->addGameObject($bonus);
        }
    }

    private function isGoingToLeftScreenSide(): bool
    {
        return
            $this->dir->getY() === 0.0 && $this->dir->getX() === -1.0
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
