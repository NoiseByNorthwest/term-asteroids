
typedef struct {
    size_t width;
    size_t height;
    size_t pixelCount;
    int64_t * currentFrameBuffer;
    int64_t * previousFrameBuffer;
    int64_t * persistenceBuffer;
} NativeRenderer;

NativeRenderer * NativeRenderer_create(size_t width, size_t height);

void NativeRenderer_destroy(NativeRenderer * nativeRenderer);

void NativeRenderer_reset(NativeRenderer * nativeRenderer);

void NativeRenderer_clear(NativeRenderer * nativeRenderer, int64_t color);

void NativeRenderer_drawBitmap(
    NativeRenderer * nativeRenderer,
    int64_t * bitmapPixels,
    size_t bitmapWidth,
    size_t bitmapHeight,
    int64_t x,
    int64_t y,
    int64_t globalAlpha,
    float brightness,
    int64_t blendingColor,
    int64_t persisted,
    int64_t globalPersistedColor,
    int64_t * horizontalBackgroundDistortionOffsets
);

void NativeRenderer_drawRect(
    NativeRenderer * nativeRenderer,
    size_t rectWidth,
    size_t rectHeight,
    int64_t x,
    int64_t y,
    int64_t color
);

size_t NativeRenderer_update(
    NativeRenderer * nativeRenderer,
    int64_t persistenceEffectsEnabled,
    int64_t persistenceAlphaDecrease,
    int64_t colorReductionFactor
);
