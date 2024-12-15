<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Screen
{
    private AABox $rect;

    private PhpRenderer $phpRenderer;

    private NativeRenderer $nativeRenderer;

    private RendererInterface $renderer;

    private AdaptivePerformanceManager $adaptivePerformanceManager;

    private bool $trueColorModeAvailable;

    private int $maxFrameRate = 80;

    private bool $debugInfoDisplayEnabled = false;

    private bool $debugRectDisplayEnabled = false;

    private float $renderingStartTime;

    private float $previousRenderingEndTime;

    private float $cumulatedExtraFrameLatency;

    private float $maxFrameTime = 0;

    private ?string $centeredText = null;

    private float $brightness = 1;

    private float $lastPersistenceAlphaDecreaseGameTime = 0;

    private float $graphicQuality = 1;

    private int $removedColorDepthBits = 0;

    private float $ditheringAlphaRatioThreshold = 0;

    private bool $persistenceEffectsEnabled = true;

    private int $lowResolutionMode = 0;

    private array $stats = [
        'renderedFrameCount' => 0,
        'totalTime' => 0,
        'nonRenderingTime' => 0,
        'renderingTime' => 0,
        'drawingTime' => 0,
        'updateTime' => 0,
        'updatedPixelCount' => 0,
        'drawnBitmapPixelCount' => 0,
    ];

    public function __construct(
        int $width,
        int $height,
        AdaptivePerformanceManager $adaptivePerformanceManager
    ) {
        if ($width % 4 !== 0) {
            throw new \RuntimeException('Screen width must be a multiple of 4');
        }

        if ($height % 4 !== 0) {
            throw new \RuntimeException('Screen height must be a multiple of 4');
        }

        $this->rect = new AABox(new Vec2(0, 0), new Vec2($width, $height));
        $this->phpRenderer = new PhpRenderer($width, $height);
        $this->nativeRenderer = new NativeRenderer($width, $height);
        $this->renderer = $this->phpRenderer;
        $this->adaptivePerformanceManager = $adaptivePerformanceManager;
        $this->trueColorModeAvailable = ((int) shell_exec('tput colors')) !== 256;
        $this->renderingStartTime = microtime(true);
        $this->previousRenderingEndTime = microtime(true);
        $this->cumulatedExtraFrameLatency = 0;
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

    public function toggleRenderer(): void
    {
        $this->renderer = $this->renderer === $this->nativeRenderer ? $this->phpRenderer : $this->nativeRenderer;
        $this->renderer->reset();
    }

    public function useNativeRenderer(): void
    {
        $this->renderer = $this->nativeRenderer;
        $this->renderer->reset();
    }

    public function setMaxFrameRate(int $maxFrameRate): void
    {
        $this->maxFrameRate = $maxFrameRate;
    }

    /**
     * @return bool
     */
    public function isDebugInfoDisplayEnabled(): bool
    {
        return $this->debugInfoDisplayEnabled;
    }

    public function setDebugInfoDisplayEnabled(bool $debugInfoDisplayEnabled): void
    {
        $this->debugInfoDisplayEnabled = $debugInfoDisplayEnabled;
    }

    /**
     * @return bool
     */
    public function isDebugRectDisplayEnabled(): bool
    {
        return $this->debugRectDisplayEnabled;
    }

    public function toggleDebugRectDisplayEnabled(): void
    {
        $this->debugRectDisplayEnabled = ! $this->debugRectDisplayEnabled;
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
        $this->checkTermSize();

        system('tput clear');

        echo "\033[?25l";
        echo "\033[?1049h";

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
     * @param int|null $globalBlendingColor
     * @param array $verticalBlendingColors
     * @param bool $persisted
     * @param int|null $globalPersistedColor
     * @param array $horizontalDistortionOffsets
     * @param array $horizontalBackgroundDistortionOffsets
     * @return void
     */
    public function drawBitmap(
        Bitmap $bitmap,
        int    $x,
        int    $y,
        int    $globalAlpha = 255,
        float  $brightness = 1,
        ?int   $globalBlendingColor = null,
        array  $verticalBlendingColors = [],
        bool   $persisted = false,
        ?int   $globalPersistedColor = null,
        array  $horizontalDistortionOffsets = [],
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
            $globalBlendingColor,
            $verticalBlendingColors,
            $persisted && $this->persistenceEffectsEnabled,
            $globalPersistedColor,
            $horizontalDistortionOffsets,
            $horizontalBackgroundDistortionOffsets,
            $this->ditheringAlphaRatioThreshold
        );
    }

    public function drawDebugRect(AABox $rect, int $color): void
    {
        if (! $this->debugRectDisplayEnabled) {
            return;
        }

        $this->renderer->drawRect($rect, $color);
    }

    public function update(?string $debugLine = null): void
    {
        $updateStartTime = microtime(true);

        if (((int)$updateStartTime) % 5 === 0) {
            $this->maxFrameTime = 0;
        }

        // 255/s (full opacity persists for 1s) -> 2.55/10ms
        $currentGameTime = Timer::getCurrentGameTime();
        if ($currentGameTime < $this->lastPersistenceAlphaDecreaseGameTime) {
            // it happens after a game reset
            $this->lastPersistenceAlphaDecreaseGameTime = $currentGameTime;
        }

        $persistenceAlphaDecrease = Math::bound(
            Math::roundToInt(2.55 * (Math::roundToInt($currentGameTime * 100) - Math::roundToInt($this->lastPersistenceAlphaDecreaseGameTime * 100))),
            0,
            255
        );

        if ($persistenceAlphaDecrease > 0) {
            $this->lastPersistenceAlphaDecreaseGameTime = $currentGameTime;
        }

        $removedColorDepthBits = $this->removedColorDepthBits;
        $lowResolutionMode = $this->lowResolutionMode;

        $updatedCharacterCount = $this->renderer->update(
            $this->trueColorModeAvailable,
            $this->persistenceEffectsEnabled,
            $persistenceAlphaDecrease,
            removedColorDepthBits: $removedColorDepthBits,
            lowResolutionMode: $lowResolutionMode
        );

        $drawnBitmapPixelCount = $this->renderer->getDrawnBitmapPixelCount();

        if ($this->centeredText) {
            echo "\033", '[',
            Math::roundToInt($this->getHeight() * 0.22), ';',
            max(0, Math::roundToInt($this->getWidth() * 0.5 - strlen($this->centeredText) * 0.5)), 'H';

            echo "\033", '[', 37, ';', 40, 'm';
            echo $this->centeredText;

            ob_flush();

            if (trim($this->centeredText) === '') {
                $this->centeredText = null;
            } else {
                // to be cleared for the next frame
                // FIXME centeredText should be handled by renderer's update()
                $this->centeredText = str_pad('', strlen($this->centeredText), ' ');
            }
        }

        $updateEndTime = microtime(true);

        $renderingEndTime = $updateEndTime;
        $frameTime = $renderingEndTime - $this->previousRenderingEndTime;

        $minFrameTime = 1.0 / $this->maxFrameRate;
        if ($frameTime > $minFrameTime) {
            $this->cumulatedExtraFrameLatency += $frameTime - $minFrameTime;
        } else {
            $requiredSleepTime = $minFrameTime - $frameTime - $this->cumulatedExtraFrameLatency;
            $this->cumulatedExtraFrameLatency = 0;
            if ($requiredSleepTime > 0) {
                do {
                    // Sleeping in one call could make the terminal rendering laggy (without increasing the measured
                    //  frame time).
                    usleep(70);
                    $renderingEndTime = microtime(true);
                } while ($renderingEndTime - $updateEndTime < $requiredSleepTime);

                $frameTime = $renderingEndTime - $this->previousRenderingEndTime;
            } else {
                $this->cumulatedExtraFrameLatency = -$requiredSleepTime;
            }
        }

        $this->maxFrameTime = max($this->maxFrameTime, $frameTime);
        $nonRenderingTime = $this->renderingStartTime - $this->previousRenderingEndTime;
        $renderingTime = $renderingEndTime - $this->renderingStartTime;
        $drawingTime = $updateStartTime - $this->renderingStartTime;
        $updateTime = $updateEndTime - $updateStartTime;
        $sleepTime = $renderingEndTime - $updateEndTime;

        $this->stats['renderedFrameCount']++;
        $this->stats['totalTime'] += $frameTime;
        $this->stats['nonRenderingTime'] += $nonRenderingTime;
        $this->stats['renderingTime'] += $renderingTime;
        $this->stats['drawingTime'] += $drawingTime;
        $this->stats['updateTime'] += $updateTime;
        $this->stats['updatedPixelCount'] += $updatedCharacterCount * 2;
        $this->stats['drawnBitmapPixelCount'] += $drawnBitmapPixelCount;

        echo "\033", '[', $this->getHeight() / 2, ';', 0, 'H';
        echo "\033", '[', 37, ';', 40, 'm';
        echo str_pad(
            sprintf(
                'Time: %s%s',
                date('i:s', (int)Timer::getCurrentGameTime()),
                $this->debugInfoDisplayEnabled ?
                    sprintf(
                        ' - Speed: %6.2fx - FPS: %6.1f - Min (-5s): %6.1f - Frame time: %3dms - Max (-5s): %4dms - Gameplay+physic: %3dms - Rendering time: %3dms (Drawing: %3dms / Update: %3dms / Sleep: %3dms) - Sprite fill rate: %4.1fM pixel/s - Change rate: %3.1fM char/s / %3.1fM pixel/s',
                        Timer::getGameTimeSpeedFactor(),
                        1 / $frameTime,
                        1 / $this->maxFrameTime,
                        (int)round(1000 * $frameTime),
                        (int)round(1000 * $this->maxFrameTime),
                        (int)round(1000 * $nonRenderingTime),
                        (int)round(1000 * $renderingTime),
                        (int)round(1000 * $drawingTime),
                        (int)round(1000 * $updateTime),
                        (int)round(1000 * $sleepTime),
                        $drawnBitmapPixelCount / $drawingTime / (1000 * 1000),
                        $updatedCharacterCount / $updateTime / (1000 * 1000),
                        $updatedCharacterCount * 2 / $updateTime / (1000 * 1000),
                    )
                    : ''
            ),
            $this->getWidth() - 1,
            ' '
        ), "\n";

        if ($this->debugInfoDisplayEnabled) {
            $gcStatus = gc_status();
            echo str_pad(
                sprintf(
                    'PHP: %s - Renderer: %-6s - JIT: %-3s - Memory (allocated / used): %5.1fMB / %5.1fMB - GC runs: %5d - GC roots: %3dK - Adapt perf: %-3s - ARCR: %4.2f - CD: %1db - DART: %4.2f - PE: %-3s',
                    PHP_VERSION,
                    $this->renderer === $this->nativeRenderer ? 'Native' : 'PHP',
                    opcache_get_status()['jit']['on'] ? 'On' : 'Off',
                    memory_get_usage(true) / (1024 * 1024),
                    memory_get_usage() / (1024 * 1024),
                    $gcStatus['runs'],
                    (int)($gcStatus['roots'] / 1000),
                    $this->adaptivePerformanceManager->isEnabled() ? 'On' : 'Off',
                    $this->adaptivePerformanceManager->getAllowedResourceConsumptionRatio(),
                    8 - $this->removedColorDepthBits,
                    $this->ditheringAlphaRatioThreshold,
                    $this->persistenceEffectsEnabled ? 'On' : 'Off',
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
        }

        if (! $this->adaptivePerformanceManager->isEnabled()) {
            $this->removedColorDepthBits = 0;
            $this->persistenceEffectsEnabled = true;
            $this->ditheringAlphaRatioThreshold = 0;
            $this->lowResolutionMode = 0;
        } else {
            $acceptableRenderingTimeLimit = 0.018;
            $renderingTimeVsAcceptableLimitRatio = $renderingTime / $acceptableRenderingTimeLimit;

            $this->graphicQuality += match (true) {
                $renderingTimeVsAcceptableLimitRatio > 1.6 => -0.5,
                $renderingTimeVsAcceptableLimitRatio > 1.4 => -0.3,
                $renderingTimeVsAcceptableLimitRatio > 1.3 => -0.2,
                $renderingTimeVsAcceptableLimitRatio > 1.2 => -0.15,
                $renderingTimeVsAcceptableLimitRatio > 1.1 => -0.10,
                $renderingTimeVsAcceptableLimitRatio > 1 => -0.05,
                $renderingTimeVsAcceptableLimitRatio > 0.98 => 0,
                $renderingTimeVsAcceptableLimitRatio > 0.8 => 0.01,
                $renderingTimeVsAcceptableLimitRatio > 0.7 => 0.05,
                default => 0.1,
            };

            $this->graphicQuality = Math::bound($this->graphicQuality);

            $maxRemovedColorDepthBits = 6;
            $this->removedColorDepthBits = Math::roundToInt(Math::lerpPath([
                '0' => $maxRemovedColorDepthBits,
                '0.08' => $maxRemovedColorDepthBits - 1,
                '1' => 0,
            ], $this->graphicQuality));

            $this->ditheringAlphaRatioThreshold = Math::lerpPath([
                '0' => 1,
                '0.2' => 1,
                '1' => 0
            ], $this->graphicQuality);

            $this->persistenceEffectsEnabled = $this->graphicQuality > 0.7;

            // disabled for now (too extreme / uncomfortable)
            $this->lowResolutionMode = 0;
        }

        ob_flush();
        $this->previousRenderingEndTime = $renderingEndTime;
    }

    private function checkTermSize(): void
    {
        $width = $this->rect->getSize()->getWidth();
        $height = $this->rect->getSize()->getHeight();
        $minTermWidth = $width;
        $minTermHeight = ($height / 2) + 5;

        do {
            $currentTermWidth = (int)shell_exec('tput cols');
            $currentTermHeight = (int)shell_exec('tput lines');

            if (
                $currentTermWidth >= $minTermWidth &&
                $currentTermHeight >= $minTermHeight
            ) {
                break;
            }

            system('tput clear');
            echo sprintf(
                "Terminal window is too small: at least %dx%d is required, current window size is %dx%d.\nPlease resize it.\n",
                $minTermWidth,
                $minTermHeight,
                $currentTermWidth,
                $currentTermHeight,
            );

            usleep(200 * 1000);
        } while (true);
    }
}
