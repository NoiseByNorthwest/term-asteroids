<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class MomentumBasedMover
{
    private Vec2 $pos;

    /**
     * @var callable
     */
    private $acceleratorFactory;

    /**
     * @var null|callable
     */
    private $nextMoveDirResolver;

    /**
     * @var MoveVectorResolver[]
     */
    private array $moveVectorResolvers = [];

    private ?AABox $boundingRect = null;

    private float $lastStepDistance = 0;

    public function __construct(
        Vec2 $pos,
        callable $acceleratorFactory,
        ?callable $nextMoveDirResolver = null
    ) {
        $this->pos = $pos;
        $this->acceleratorFactory = $acceleratorFactory;
        $this->nextMoveDirResolver = $nextMoveDirResolver;
    }

    public function reset(): void
    {
        $this->moveVectorResolvers = [];
    }

    public function accelerate(Vec2 $dir): void
    {
        $this->moveVectorResolvers[] = new MoveVectorResolver(
            $dir,
            ($this->acceleratorFactory)()
        );
    }

    public function updatePos(): void
    {
        $combinedMoveVector = new Vec2();
        $moveVectorLengths = [];
        foreach ($this->moveVectorResolvers as $k => $moveVectorResolver) {
            if ($moveVectorResolver->isStopped()) {
                unset($this->moveVectorResolvers[$k]);
            }

            $moveVector = $moveVectorResolver->getMoveVectorSinceLastStep();
            $moveVectorLengths[] = $moveVector->computeLength();
            $combinedMoveVector->addVec($moveVector);
        }

        if (count($moveVectorLengths) === 0) {
            if ($this->nextMoveDirResolver !== null) {
                $nextMoveDir = ($this->nextMoveDirResolver)();
                if ($nextMoveDir) {
                    $this->accelerate($nextMoveDir);
                }
            }

            return;
        }

        $stepDistance = max($moveVectorLengths);
        $combinedMoveVector->setLength($stepDistance);
        $this->pos->addVec($combinedMoveVector);
        if ($this->boundingRect) {
            $this->pos->boundToRect($this->boundingRect);
        }

        $this->lastStepDistance = $stepDistance;
    }

    public function setBoundingRect(AABox $boundingRect)
    {
        $this->boundingRect = $boundingRect;
    }

    /**
     * @return float
     */
    public function getLastStepDistance(): float
    {
        return $this->lastStepDistance;
    }
}

class MoveVectorResolver
{
    private Vec2 $dir;

    private Accelerator $accelerator;

    public function __construct(Vec2 $dir, Accelerator $accelerator)
    {
        $this->dir = $dir->copy();
        $this->dir->normalize();
        $this->accelerator = $accelerator;
    }

    public function getMoveVectorSinceLastStep(): Vec2
    {
        return $this->dir->copy()->mul(
            $this->accelerator->getDistanceSinceLastStep()
        );
    }

    public function isStopped(): bool
    {
        return $this->accelerator->isStopped();
    }
}
