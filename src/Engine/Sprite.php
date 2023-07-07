<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Sprite
{
    private Vec2 $size;

    private Vec2 $pos;

    private AABox $boundingBox;

    /**
     * @var SpriteAnimation[]
     */
    private array $animations;

    private string $currentAnimationName;

    private SpriteRenderingParameters $renderingParameters;

    /**
     * @var SpriteEffect[]
     */
    private array $effects = [];

    /**
     * @param int $width
     * @param int $height
     * @param array $animationsData
     * @param SpriteEffect[] $effects
     */
    public function __construct(
        int $width,
        int $height,
        array $animationsData,
        array $effects = []
    ) {
        $this->size = new Vec2($width, $height);
        $this->pos = new Vec2();
        $this->boundingBox = new AABox(
            $this->pos->creatRelativePos(-$width / 2, -$height / 2),
            $this->size
        );

        $this->animations = [];
        foreach ($animationsData as $animationData) {
            $this->animations[$animationData['name']] = new SpriteAnimation(
                $this,
                $animationData['name'],
                $animationData['repeat'] ?? true,
                $animationData['loopBack'] ?? false,
                $animationData['frames'],
            );
        }

        $this->currentAnimationName = array_keys($this->animations)[0];

        $this->renderingParameters = new SpriteRenderingParameters();

        foreach ($effects as $effect) {
            $this->addEffect($effect);
        }
    }

    public function addEffect(SpriteEffect $effect): void
    {
        if (isset($this->effects[$effect->getKey()])) {
            throw new \RuntimeException('Duplicate sprite effect key: ' . $effect->getKey());
        }

        $this->effects[$effect->getKey()] = $effect;
    }

    public function reset(): void
    {
        foreach ($this->animations as $animation) {
            $animation->reset();
        }

        foreach ($this->effects as $effect) {
            $effect->reset();
        }
    }

    public function getSize(): Vec2
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->size->getWidth();
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->size->getHeight();
    }

    /**
     * @return Vec2
     */
    public function getPos(): Vec2
    {
        return $this->pos;
    }

    public function getBoundingBox(): AABox
    {
        return $this->boundingBox;
    }

    public function getRelativeCenter(): Vec2
    {
        return $this->size->copy()->mul(0.5);
    }

    /**
     * @return array
     */
    public function getAnimations(): array
    {
        return $this->animations;
    }

    public function getCurrentAnimation(): SpriteAnimation
    {
        return $this->animations[$this->currentAnimationName];
    }

    public function setCurrentAnimationName(string $name): void
    {
        assert(isset($this->animations[$name]));

        $this->currentAnimationName = $name;
    }

    public function getCurrentFrame(): SpriteFrame
    {
        return $this->getCurrentAnimation()->getCurrentFrame();
    }

    /**
     * @return SpriteRenderingParameters
     */
    public function getRenderingParameters(): SpriteRenderingParameters
    {
        return $this->renderingParameters;
    }

    public function update(): void
    {
        $this->getCurrentAnimation()->update();

        $this->renderingParameters->reset();
        foreach ($this->effects as $effect) {
            $effect->updateRenderingParameters($this->renderingParameters);
        }
    }

    public function startEffect(string $name): void
    {
        $this->effects[$name]->start();
    }

    public function draw(Screen $screen): void
    {
        $boundingBoxPos = $this->getBoundingBox()->getPos();

        $screen->drawBitmap(
            $this->getCurrentFrame()->getBitmap(),
            Math::roundToInt($boundingBoxPos->getX()),
            Math::roundToInt($boundingBoxPos->getY()),
            $this->getRenderingParameters()->getGlobalAlpha(),
            $this->getRenderingParameters()->getBrightness(),
            $this->getRenderingParameters()->getBlendingColor(),
            $this->getRenderingParameters()->isPersisted(),
            $this->getRenderingParameters()->getPersistedColor(),
            $this->getRenderingParameters()->getHorizontalBackgroundDistortionOffsets(),
        );

        if ($screen->isDebug()) {
            $screen->drawDebugRect($this->getBoundingBox(), ColorUtils::createColor([0, 0, 128]));
        }
    }
}
