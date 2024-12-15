<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class NativeRenderer implements RendererInterface
{
    const BITMAP_DIMENSION_MAX_SIZE = 256;

    private object $nativeRendererFfi;

    private object $verticalBlendingColorsFfiBuffer;

    private object $horizontalDistortionOffsetsFfiBuffer;

    private object $horizontalBackgroundDistortionOffsetsFfiBuffer;

    private static ?\FFI $ffi = null;

    public static function getFfi(): \FFI
    {
        if (self::$ffi === null) {
            $sharedObjectDirName = realpath(__DIR__ . '/../../.tmp');
            assert($sharedObjectDirName !== false);

            $includePathFlags = [
                '-I/usr/local/include/php',
                '-I/usr/local/include/php/main',
                '-I/usr/local/include/php/TSRM',
                '-I/usr/local/include/php/Zend',
                '-I/usr/local/include/php/ext',
                '-I/usr/local/include/php/ext/date/lib',
            ];

            $sharedObjectFileName = $sharedObjectDirName . '/NativeRenderer.so';
            shell_exec('rm -rf ' . $sharedObjectFileName);
            shell_exec(sprintf(
                'gcc -O3 -march=native -ffast-math -Werror -Wall %s -shared -fPIC -o %s %s',
                implode(' ', $includePathFlags),
                $sharedObjectFileName,
                __DIR__ . '/NativeRenderer.c',
            ));

            self::$ffi = \FFI::cdef(
                file_get_contents(__DIR__ . '/NativeRenderer.h'),
                $sharedObjectFileName
            );
        }

        return self::$ffi;
    }

    public function __construct(int $width, int $height)
    {
        $this->nativeRendererFfi = self::getFfi()->NativeRenderer_create($width, $height);

        $this->verticalBlendingColorsFfiBuffer = self::getFfi()->new(sprintf(
            'int64_t[%d]',
            self::BITMAP_DIMENSION_MAX_SIZE
        ));

        $this->horizontalDistortionOffsetsFfiBuffer = self::getFfi()->new(sprintf(
            'int64_t[%d]',
            self::BITMAP_DIMENSION_MAX_SIZE
        ));

        $this->horizontalBackgroundDistortionOffsetsFfiBuffer = self::getFfi()->new(sprintf(
            'int64_t[%d]',
            self::BITMAP_DIMENSION_MAX_SIZE
        ));
    }

    public function __destruct()
    {
        self::getFfi()->NativeRenderer_destroy($this->nativeRendererFfi);
    }

    public function reset(): void
    {
        self::getFfi()->NativeRenderer_reset($this->nativeRendererFfi);
    }

    public function clear(int $color): void
    {
        self::getFfi()->NativeRenderer_clear($this->nativeRendererFfi, $color);
    }

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
        float  $ditheringAlphaRatioThreshold = 0,
    ): void {
        $bitmapWidth = $bitmap->getWidth();
        $bitmapHeight = $bitmap->getHeight();
        $bitmapNativePixels = $bitmap->getNativePixels();

        if (
            $bitmapWidth > self::BITMAP_DIMENSION_MAX_SIZE ||
            $bitmapHeight > self::BITMAP_DIMENSION_MAX_SIZE
        ) {
            throw new \RuntimeException(sprintf(
                'A bitmap cannot exceed %dx%d',
                self::BITMAP_DIMENSION_MAX_SIZE,
                self::BITMAP_DIMENSION_MAX_SIZE
            ));
        }

        for ($i = 0; $i < $bitmapWidth; $i++) {
            $this->verticalBlendingColorsFfiBuffer[$i] = $verticalBlendingColors[$i] ?? -1;
        }

        for ($i = 0; $i < $bitmapHeight; $i++) {
            $this->horizontalDistortionOffsetsFfiBuffer[$i] = $horizontalDistortionOffsets[$i] ?? 0;
            $this->horizontalBackgroundDistortionOffsetsFfiBuffer[$i] = $horizontalBackgroundDistortionOffsets[$i] ?? 0;
        }

        self::getFfi()->NativeRenderer_drawBitmap(
            $this->nativeRendererFfi,
            $bitmapNativePixels,
            $bitmapWidth,
            $bitmapHeight,
            $x,
            $y,
            $globalAlpha,
            $brightness,
            $globalBlendingColor !== null ? $globalBlendingColor : -1,
            $this->verticalBlendingColorsFfiBuffer,
            $persisted ? 1 : 0,
            $globalPersistedColor !== null ? $globalPersistedColor : -1,
            $this->horizontalDistortionOffsetsFfiBuffer,
            $this->horizontalBackgroundDistortionOffsetsFfiBuffer,
            $ditheringAlphaRatioThreshold
        );
    }

    public function drawRect(AABox $rect, int $color): void
    {
        self::getFfi()->NativeRenderer_drawRect(
            $this->nativeRendererFfi,
            (int) $rect->getSize()->getWidth(),
            (int) $rect->getSize()->getHeight(),
            Math::roundToInt($rect->getPos()->getX()),
            Math::roundToInt($rect->getPos()->getY()),
            $color,
        );
    }

    public function getDrawnBitmapPixelCount(): int
    {
        return self::getFfi()->NativeRenderer_getDrawnBitmapPixelCount(
            $this->nativeRendererFfi,
        );
    }

    function update(
        bool $trueColorModeEnabled,
        bool $persistenceEffectsEnabled,
        int $persistenceAlphaDecrease,
        int $removedColorDepthBits,
        int $lowResolutionMode,
    ): int {
        // $lowResolutionMode is not implemented yet

        return self::getFfi()->NativeRenderer_update(
            $this->nativeRendererFfi,
            $trueColorModeEnabled ? 1 : 0,
            $persistenceEffectsEnabled ? 1 : 0,
            $persistenceAlphaDecrease,
            $removedColorDepthBits,
        );
    }
}
