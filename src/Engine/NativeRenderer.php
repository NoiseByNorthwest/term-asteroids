<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class NativeRenderer implements RendererInterface
{
    const BITMAP_DIMENSION_MAX_SIZE = 256;

    private \FFI $ffi;

    private object $nativeRendererFfi;

    private object $bitmapPixelsFfiBuffer;

    private object $horizontalBackgroundDistortionOffsetsFfiBuffer;

    public function __construct(int $width, int $height)
    {
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
            'gcc -O3 -msse2 -mfpmath=sse -march=native -Werror -Wall %s -shared -fPIC -o %s %s',
            implode(' ', $includePathFlags),
            $sharedObjectFileName,
            __DIR__ . '/NativeRenderer.c',
        ));

        $this->ffi = \FFI::cdef(
            file_get_contents(__DIR__ . '/NativeRenderer.h'),
            $sharedObjectFileName
        );

        $this->nativeRendererFfi = $this->ffi->NativeRenderer_create($width, $height);

        $this->bitmapPixelsFfiBuffer = \FFI::new(sprintf(
            'int64_t[%d]',
            self::BITMAP_DIMENSION_MAX_SIZE ** 2)
        );

        $this->horizontalBackgroundDistortionOffsetsFfiBuffer = \FFI::new(sprintf(
            'int64_t[%d]',
            self::BITMAP_DIMENSION_MAX_SIZE
        ));
    }

    public function __destruct()
    {
        \FFI::free(\FFI::addr($this->bitmapPixelsFfiBuffer));
        \FFI::free(\FFI::addr($this->horizontalBackgroundDistortionOffsetsFfiBuffer));
        $this->ffi->NativeRenderer_destroy($this->nativeRendererFfi);
    }

    public function reset(): void
    {
        $this->ffi->NativeRenderer_reset($this->nativeRendererFfi);
    }

    public function clear(int $color): void
    {
        $this->ffi->NativeRenderer_clear($this->nativeRendererFfi, $color);
    }

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
        $bitmapWidth = $bitmap->getWidth();
        $bitmapHeight = $bitmap->getHeight();
        $bitmapPixelCount = $bitmapWidth * $bitmapHeight;
        $bitmapPixels = $bitmap->getPixels();

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

        for ($i = 0; $i < $bitmapPixelCount; $i++) {
            $this->bitmapPixelsFfiBuffer[$i] = $bitmapPixels[$i];
        }

        for ($i = 0; $i < $bitmapHeight; $i++) {
            $this->horizontalBackgroundDistortionOffsetsFfiBuffer[$i] = $horizontalBackgroundDistortionOffsets[$i] ?? 0;
        }

        $this->ffi->NativeRenderer_drawBitmap(
            $this->nativeRendererFfi,
            $this->bitmapPixelsFfiBuffer,
            $bitmapWidth,
            $bitmapHeight,
            $x,
            $y,
            $globalAlpha,
            $brightness,
            $blendingColor !== null ? $blendingColor : -1,
            $persisted ? 1 : 0,
            $globalPersistedColor !== null ? $globalPersistedColor : -1,
            $this->horizontalBackgroundDistortionOffsetsFfiBuffer,
        );
    }

    public function drawRect(AABox $rect, int $color): void
    {
        $this->ffi->NativeRenderer_drawRect(
            $this->nativeRendererFfi,
            (int) $rect->getSize()->getWidth(),
            (int) $rect->getSize()->getHeight(),
            Math::roundToInt($rect->getPos()->getX()),
            Math::roundToInt($rect->getPos()->getY()),
            $color,
        );
    }

    function update(
        bool $persistenceEffectsEnabled,
        int $persistenceAlphaDecrease,
        int $colorReductionFactor,
        int $lowResolutionMode,
    ): int {
        // $lowResolutionMode is not implemented yet

        return $this->ffi->NativeRenderer_update(
            $this->nativeRendererFfi,
            $persistenceEffectsEnabled ? 1 : 0,
            $persistenceAlphaDecrease,
            $colorReductionFactor,
        );
    }
}
