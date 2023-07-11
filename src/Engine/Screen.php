<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Screen
{
    private AABox $rect;

    private PhpRenderer $phpRenderer;

    private NativeRenderer $nativeRenderer;

    private RendererInterface $renderer;

    private bool $adaptivePerformance = true;

    private bool $debug = false;

    private float $renderingStartTime;

    private float $previousRenderingEndTime;

    private ?string $centeredText = null;

    private float $brightness = 1;

    private bool $persistenceEffectsEnabled = true;

    private int $colorReductionFactor = 1;

    private int $lowResolutionMode = 0;

    private array $stats = [
        'renderedFrameCount' => 0,
        'totalTime' => 0,
        'nonRenderingTime' => 0,
        'renderingTime' => 0,
        'drawingTime' => 0,
        'updateTime' => 0,
        'flushingTime' => 0,
        'updatedPixelCount' => 0,
    ];

    public function __construct(int $width, int $height)
    {
        if ($width % 4 !== 0) {
            throw new \RuntimeException('Screen width must be a multiple of 4');
        }

        if ($height % 4 !== 0) {
            throw new \RuntimeException('Screen height must be a multiple of 4');
        }

        $minTermWidth = $width;
        $minTermHeight = ($height / 2) + 5;
        $currentTermWidth = (int) shell_exec('tput cols');
        $currentTermHeight = (int) shell_exec('tput lines');
        if (
            $currentTermWidth < $minTermWidth ||
            $currentTermHeight < $minTermHeight
        ) {
            throw new \RuntimeException(sprintf(
                'Terminal window is too small, at least %dx%d is required, current window size is %dx%d',
                $minTermWidth,
                $minTermHeight,
                $currentTermWidth,
                $currentTermHeight,
            ));
        }

        $this->rect = new AABox(new Vec2(0, 0), new Vec2($width, $height));
        $this->phpRenderer = new PhpRenderer($width, $height);
        $this->nativeRenderer = new NativeRenderer($width, $height);
        $this->renderer = $this->phpRenderer;
        $this->renderingStartTime = microtime(true);
        $this->previousRenderingEndTime = microtime(true);
    }

    /**
     * @return AABox
     */
    public function getRect(): AABox
    {
        return $this->rect;
    }

    public function getSize(): Vec2
    {
        return $this->rect->getSize();
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->getSize()->getWidth();
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->getSize()->getHeight();
    }

    public function toggleRenderer()
    {
        $this->renderer = $this->renderer === $this->nativeRenderer ? $this->phpRenderer : $this->nativeRenderer;
        $this->renderer->reset();
    }

    public function useNativeRenderer(): void
    {
        $this->renderer = $this->nativeRenderer;
        $this->renderer->reset();
    }

    public function toggleAdaptivePerformance()
    {
        $this->adaptivePerformance = ! $this->adaptivePerformance;
    }

    public function setAdaptivePerformance(bool $adaptivePerformance): void
    {
        $this->adaptivePerformance = $adaptivePerformance;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function toggleDebug()
    {
        $this->debug = ! $this->debug;
    }

    /**
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    public function init(): void
    {
        system('tput clear');

        echo "\033[?25l";

        assert(ob_get_level() === 0);
        ob_start();
    }

    public function reset(): void
    {
        $this->centeredText = null;
        $this->phpRenderer->reset();
        $this->nativeRenderer->reset();
    }

    public function clear(int|Vec3 $color): void
    {
        $this->renderingStartTime = microtime(true);

        $this->renderer->clear($color);
    }

    /**
     * @param string|null $centeredText
     */
    public function setCenteredText(?string $centeredText): void
    {
        if (strlen($this->centeredText ?? '') > strlen($centeredText ?? '')) {
            $this->renderer->reset();
        }

        $this->centeredText = $centeredText;
    }

    /**
     * @param float $brightness
     */
    public function setBrightness(float $brightness): void
    {
        $this->brightness = Math::bound($brightness);
    }

    /**
     * @param Bitmap $bitmap
     * @param int $x
     * @param int $y
     * @param int $globalAlpha
     * @param float $brightness
     * @param int|null $blendingColor
     * @param bool $persisted
     * @param int|null $globalPersistedColor
     * @param array $horizontalBackgroundDistortionOffsets
     * @return void
     */
    public function drawBitmap(
        Bitmap $bitmap,
        int    $x,
        int    $y,
        int    $globalAlpha = 255,
        float  $brightness = 1,
        ?int   $blendingColor = null,
        bool   $persisted = false,
        ?int   $globalPersistedColor = null,
        array  $horizontalBackgroundDistortionOffsets = [],
    ): void {
        if ($globalAlpha === 0) {
            return;
        }

        $this->renderer->drawBitmap(
            $bitmap,
            $x,
            $y,
            $globalAlpha,
            $brightness * $this->brightness,
            $blendingColor,
            $persisted && $this->persistenceEffectsEnabled,
            $globalPersistedColor,
            $horizontalBackgroundDistortionOffsets,
        );
    }

    public function drawDebugRect(AABox $rect, int $color): void
    {
        if (! $this->debug) {
            return;
        }

        $this->renderer->drawRect($rect, $color);
    }

    function update(?string $debugLine = null): void
    {
        $maxColorReductionFactor = 64;

        $updateStartTime = microtime(true);

        $persistenceAlphaDecrease = (int) max(1, ($updateStartTime - $this->previousRenderingEndTime) / 0.0008);
        $colorReductionFactor = $this->colorReductionFactor;
        $lowResolutionMode = $this->lowResolutionMode;

        $updatedCharacterCount = $this->renderer->update(
            $this->persistenceEffectsEnabled,
            $persistenceAlphaDecrease,
            $colorReductionFactor,
            $lowResolutionMode
        );

        if ($this->centeredText) {
            echo "\033", '[',
            Math::roundToInt($this->getHeight() * 0.22), ';',
            max(0, Math::roundToInt($this->getWidth() * 0.5 - strlen($this->centeredText) * 0.5)), 'H'
            ;

            echo "\033", '[', 37, ';', 40, 'm';
            echo $this->centeredText;
        }

        $updateEndTime = microtime(true);

        ob_flush();

        $renderingEndTime = microtime(true);

        $frameTime = $renderingEndTime - $this->previousRenderingEndTime;
        $nonRenderingTime = $this->renderingStartTime - $this->previousRenderingEndTime;
        $renderingTime = $renderingEndTime - $this->renderingStartTime;
        $drawingTime = $updateStartTime - $this->renderingStartTime;
        $updateTime = $updateEndTime - $updateStartTime;
        $flushingTime = $renderingEndTime - $updateEndTime;

        $this->stats['renderedFrameCount']++;
        $this->stats['totalTime'] += $frameTime;
        $this->stats['nonRenderingTime'] += $nonRenderingTime;
        $this->stats['renderingTime'] += $renderingTime;
        $this->stats['drawingTime'] += $drawingTime;
        $this->stats['updateTime'] += $updateTime;
        $this->stats['flushingTime'] += $flushingTime;
        $this->stats['updatedPixelCount'] += $updatedCharacterCount * 2;

        echo "\033", '[', $this->getHeight() / 2, ';', 0, 'H';
        echo "\033", '[', 37, ';', 40, 'm';
        echo str_pad(
            sprintf(
                'Time: %s - Renderer: %-6s - FPS: %6.1f - Frame time: %3dms - Gameplay+physic: %3dms - Rendering time: %3dms (Drawing: %3dms / Update: %3dms / Flushing: %3dms) - Changes: %4.1fK chars / %4.1fK pixels - Change rate: %3.1fM char/s / %3.1fM pixel/s - Adapt perf: %-3s - CRF: %2d',
                date('i:s', (int) Timer::getCurrentFrameStartTime()),
                $this->renderer === $this->nativeRenderer ? 'Native' : 'PHP',
                1 / $frameTime,
                (int)round(1000 * $frameTime),
                (int)round(1000 * $nonRenderingTime),
                (int)round(1000 * $renderingTime),
                (int)round(1000 * $drawingTime),
                (int)round(1000 * $updateTime),
                (int)round(1000 * $flushingTime),
                $updatedCharacterCount / 1000,
                $updatedCharacterCount * 2 / 1000,
                $updatedCharacterCount / ($renderingEndTime - $updateStartTime) / (1000 * 1000),
                $updatedCharacterCount * 2 / ($renderingEndTime - $updateStartTime) / (1000 * 1000),
                $this->adaptivePerformance ? 'On' : 'Off',
                $this->colorReductionFactor
            ),
            $this->getWidth() - 1,
            ' '
        ), "\n";

        $gcStatus = gc_status();
        echo str_pad(
            sprintf(
                'Memory (allocated / used): %5.1fMB / %5.1fMB - GC runs: %5d - GC roots: %3dK - JIT: %-3s',
                memory_get_usage(true) / (1024 * 1024),
                memory_get_usage() / (1024 * 1024),
                $gcStatus['runs'],
                (int) ($gcStatus['roots'] / 1000),
                opcache_get_status()['jit']['enabled'] ? 'On' : 'Off'
            ),
            $this->getWidth() - 1,
            ' '
        ), "\n";

        if ($debugLine !== null) {
            echo str_pad(
                $debugLine,
                $this->getWidth() - 1,
                ' '
            ), "\n";
        }

        $frameTimeMs = 1000 * ($renderingEndTime - $this->previousRenderingEndTime);
        $minFps = 35;
        $acceptableFrameTimeLimitMs = 1000 / $minFps;
        $this->colorReductionFactor += match (true) {
            $frameTimeMs / $acceptableFrameTimeLimitMs > 1.8 => 32,
            $frameTimeMs / $acceptableFrameTimeLimitMs > 1.6 => 16,
            $frameTimeMs / $acceptableFrameTimeLimitMs > 1.4 => 8,
            $frameTimeMs / $acceptableFrameTimeLimitMs > 1.3 => 6,
            $frameTimeMs / $acceptableFrameTimeLimitMs > 1.2 => 4,
            $frameTimeMs / $acceptableFrameTimeLimitMs > 1.1 => 2,
            $frameTimeMs / $acceptableFrameTimeLimitMs > 1 => 1,
            $frameTimeMs / $acceptableFrameTimeLimitMs > 0.9 => 0,
            default => -1,
        };

        if (! $this->adaptivePerformance) {
            $this->colorReductionFactor = 1;
        }

        if ($this->colorReductionFactor > $maxColorReductionFactor) {
            $this->colorReductionFactor = $maxColorReductionFactor;
        } else if ($this->colorReductionFactor < 1) {
            $this->colorReductionFactor = 1;
        }

        $this->lowResolutionMode = 0;
        if ($this->colorReductionFactor >= $maxColorReductionFactor * 0.95) {
            $this->lowResolutionMode = 2;
        } else if ($this->colorReductionFactor >= $maxColorReductionFactor * 0.8) {
            $this->lowResolutionMode = 1;
        }

        // disabled for now (too extreme / uncomfortable)
        $this->lowResolutionMode = 0;

        $this->persistenceEffectsEnabled = $this->colorReductionFactor < $maxColorReductionFactor * 0.6;

        ob_flush();
        $this->previousRenderingEndTime = $renderingEndTime;

        if ($gcStatus['roots'] > 15000) {
            gc_collect_cycles();
        }
    }
}
