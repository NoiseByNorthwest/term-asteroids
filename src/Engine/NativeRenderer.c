#define _GNU_SOURCE
#include <stdio.h>
#include <stdlib.h>
#include <main/php.h>
#include <main/php_output.h>
#include "NativeRenderer.h"

NativeRenderer * NativeRenderer_create(size_t width, size_t height)
{
    NativeRenderer * nativeRenderer = malloc(sizeof *nativeRenderer);
    if (! nativeRenderer) {
        goto error;
    }

    nativeRenderer->width = width;
    nativeRenderer->height = height;
    nativeRenderer->pixelCount = nativeRenderer->width * nativeRenderer->height;

    nativeRenderer->currentFrameBuffer = malloc(nativeRenderer->pixelCount * sizeof(int64_t));
    nativeRenderer->previousFrameBuffer = malloc(nativeRenderer->pixelCount * sizeof(int64_t));
    nativeRenderer->persistenceBuffer = malloc(nativeRenderer->pixelCount * sizeof(int64_t));

    if (
        ! nativeRenderer->currentFrameBuffer ||
        ! nativeRenderer->previousFrameBuffer ||
        ! nativeRenderer->persistenceBuffer
    ) {
        goto error;
    }

    NativeRenderer_reset(nativeRenderer);

    return nativeRenderer;

    error:
        NativeRenderer_destroy(nativeRenderer);

        return NULL;
}

void NativeRenderer_destroy(NativeRenderer * nativeRenderer)
{
    if (nativeRenderer) {
        free(nativeRenderer->currentFrameBuffer);
        free(nativeRenderer->previousFrameBuffer);
        free(nativeRenderer->persistenceBuffer);
    }

    free(nativeRenderer);
}

void NativeRenderer_reset(NativeRenderer * nativeRenderer)
{
    for (size_t i = 0; i < nativeRenderer->pixelCount; i++) {
        nativeRenderer->currentFrameBuffer[i] = -1;
        nativeRenderer->previousFrameBuffer[i] = -1;
        nativeRenderer->persistenceBuffer[i] = 0;
    }
}

void NativeRenderer_clear(NativeRenderer * nativeRenderer, int64_t color)
{
    for (size_t i = 0; i < nativeRenderer->pixelCount; i++) {
        nativeRenderer->currentFrameBuffer[i] = color;
    }
}

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
) {
    if (globalAlpha == 0) {
        return;
    }

    for (size_t i = 0; i < bitmapHeight; i++) {
        const size_t pxPosY = y + i;

        if (
            pxPosY < 0 || pxPosY >= nativeRenderer->height
        ) {
            continue;
        }

        const int64_t horizontalBackgroundDistortionOffset = horizontalBackgroundDistortionOffsets[i];

        for (size_t j = 0; j < bitmapWidth; j++) {
            const size_t pxPosX = x + j;

            if (
                pxPosX < 0 || pxPosX >= nativeRenderer->width
            ) {
                continue;
            }

            int64_t color = bitmapPixels[i * bitmapWidth + j];

            if (color < 0) {
                continue;
            }

            const int64_t alpha = (color >> 24) & 0xff;
            if (alpha == 0) {
                continue;
            }

            int64_t persistedColor = globalPersistedColor >= 0 ? globalPersistedColor : color;

            const size_t pxIndex = pxPosY * nativeRenderer->width + pxPosX;

            if (
                alpha < 255
                || globalAlpha < 255
                || brightness != 1
                || blendingColor >= 0
            ) {
                int64_t colorR = (color >> 16) & 0xff;
                int64_t colorG = (color >> 8) & 0xff;
                int64_t colorB = color & 0xff;

                const int64_t blendingColorA = blendingColor >= 0 ? (blendingColor >> 24) & 0xff : 0;

                if (blendingColorA > 0) {
                    const int64_t blendingColorR = (blendingColor >> 16) & 0xff;
                    const int64_t blendingColorG = (blendingColor >> 8) & 0xff;
                    const int64_t blendingColorB = blendingColor & 0xff;

                    colorR = (int64_t) (
                        blendingColorR * (blendingColorA / 255.0)
                        + colorR * (1 - (blendingColorA / 255.0))
                    );

                    colorG = (int64_t) (
                        blendingColorG * (blendingColorA / 255.0)
                        + colorG * (1 - (blendingColorA / 255.0))
                    );

                    colorB = (int64_t) (
                        blendingColorB * (blendingColorA / 255.0)
                        + colorB * (1 - (blendingColorA / 255.0))
                    );
                }

                colorR = (int64_t) (colorR * brightness);
                colorG = (int64_t) (colorG * brightness);
                colorB = (int64_t) (colorB * brightness);

                int64_t combinedAlpha = 255;
                if (globalAlpha < 255 || alpha < 255) {
                    combinedAlpha = (int64_t) (255 * (globalAlpha / 255.0) * (alpha / 255.0));
                }

                if (persisted) {
                    if (globalPersistedColor < 0) {
                        persistedColor =
                            (combinedAlpha << 24) |
                            ((colorR & 0xff) << 16) |
                            ((colorG & 0xff) << 8) |
                            (colorB & 0xff)
                        ;
                    }

                    if (brightness != 1) {
                        persistedColor =
                            (((persistedColor >> 24) & 0xff) << 24) |
                            ((((int) (((persistedColor >> 16) & 0xff) * brightness) & 0xff)) << 16) |
                            ((((int) (((persistedColor >> 8) & 0xff) * brightness) & 0xff)) << 8) |
                            ((((int) (((persistedColor >> 0) & 0xff) * brightness) & 0xff)) << 0)
                        ;
                    }
                }

                if (combinedAlpha < 255) {
                    const int64_t backgroundColor = pxIndex + horizontalBackgroundDistortionOffset < nativeRenderer->pixelCount
                        ? nativeRenderer->currentFrameBuffer[pxIndex + horizontalBackgroundDistortionOffset] : 0;

                    const int64_t backgroundColorR = (backgroundColor >> 16) & 0xff;
                    const int64_t backgroundColorG = (backgroundColor >> 8) & 0xff;
                    const int64_t backgroundColorB = backgroundColor & 0xff;

                    colorR = (int64_t) (
                        colorR * (combinedAlpha / 255.0)
                        + backgroundColorR * (1 - (combinedAlpha / 255.0))
                    );

                    colorG = (int64_t) (
                        colorG * (combinedAlpha / 255.0)
                        + backgroundColorG * (1 - (combinedAlpha / 255.0))
                    );

                    colorB = (int64_t) (
                        colorB * (combinedAlpha / 255.0)
                        + backgroundColorB * (1 - (combinedAlpha / 255.0))
                    );
                }

                color =
                    (255 << 24) |
                    ((colorR & 0xff) << 16) |
                    ((colorG & 0xff) << 8) |
                    (colorB & 0xff)
                ;
            }

            nativeRenderer->currentFrameBuffer[pxIndex] = color;
            if (persisted && alpha > 1) {
                const int64_t currentPersistedColor = nativeRenderer->persistenceBuffer[pxIndex];
                const int64_t currentPersistedColorA = (currentPersistedColor >> 24) & 0xff;
                if (currentPersistedColorA <= 2) {
                    nativeRenderer->persistenceBuffer[pxIndex] = persistedColor;
                } else {
                    const int64_t currentPersistedColorR = (currentPersistedColor >> 16) & 0xff;
                    const int64_t currentPersistedColorG = (currentPersistedColor >> 8) & 0xff;
                    const int64_t currentPersistedColorB = currentPersistedColor & 0xff;

                    int64_t persistedColorA = (persistedColor >> 24) & 0xff;
                    int64_t persistedColorR = (persistedColor >> 16) & 0xff;
                    int64_t persistedColorG = (persistedColor >> 8) & 0xff;
                    int64_t persistedColorB = persistedColor & 0xff;

                    const double blendingRatio = persistedColorA / (double) (persistedColorA + currentPersistedColorA);

                    persistedColorA = persistedColorA > currentPersistedColorA ? persistedColorA : currentPersistedColorA;

                    persistedColorR = (int64_t) (
                        persistedColorR * blendingRatio
                            + currentPersistedColorR * (1 - blendingRatio)
                    );

                    persistedColorG = (int64_t) (
                        persistedColorG * blendingRatio
                        + currentPersistedColorG * (1 - blendingRatio)
                    );

                    persistedColorB = (int64_t) (
                        persistedColorB * blendingRatio
                        + currentPersistedColorB * (1 - blendingRatio)
                    );

                    nativeRenderer->persistenceBuffer[pxIndex] =
                        ((persistedColorA & 0xff) << 24) |
                        ((persistedColorR & 0xff) << 16) |
                        ((persistedColorG & 0xff) << 8) |
                        (persistedColorB & 0xff)
                    ;
                }
            }
        }
    }
}

void NativeRenderer_drawRect(
    NativeRenderer * nativeRenderer,
    size_t rectWidth,
    size_t rectHeight,
    int64_t x,
    int64_t y,
    int64_t color
) {
    for (size_t i = 0; i < rectHeight; i++) {
        const int64_t pxPosY = y + i;

        if (
            pxPosY < 0 || pxPosY >= nativeRenderer->height
        ) {
            continue;
        }

        for (size_t j = 0; j < rectWidth; j++) {
            const int64_t pxPosX = x + j;

            if (
                pxPosX < 0 || pxPosX >= nativeRenderer->width
            ) {
                continue;
            }

            if (
                i > 0 && i < rectHeight - 1 &&
                j > 0 && j < rectWidth - 1
            ) {
                continue;
            }

            const size_t pxIndex = pxPosY * nativeRenderer->width + pxPosX;
            nativeRenderer->currentFrameBuffer[pxIndex] = color;
        }
    }
}

size_t NativeRenderer_update(
    NativeRenderer * nativeRenderer,
    int64_t persistenceEffectsEnabled,
    int64_t persistenceAlphaDecrease,
    int64_t colorReductionFactor
) {
    static char buffer[16 * 1024];

    buffer[0] = 0;
    char * bufferCursor = buffer;
    size_t remainingBufferSize = sizeof buffer;

    size_t updatedCharacterCount = 0;
    int lastPxCol;
    int lastUpperColor = 0, lastLowerColor = 0;

    for (size_t i = 0; i < nativeRenderer->height; i += 2) {
        lastPxCol = nativeRenderer->width;
        for (size_t j = 0; j < nativeRenderer->width; j++) {
            const size_t upperPxIndex = i * nativeRenderer->width + j;
            const size_t lowerPxIndex = (i + 1) * nativeRenderer->width + j;

            int64_t upperColor = nativeRenderer->currentFrameBuffer[upperPxIndex];
            int64_t lowerColor = nativeRenderer->currentFrameBuffer[lowerPxIndex];

            int64_t persistedColor = nativeRenderer->persistenceBuffer[upperPxIndex];
            int64_t persistedColorA = (persistedColor >> 24) & 0xff;

            if (!persistenceEffectsEnabled && persistedColorA > 0) {
                nativeRenderer->persistenceBuffer[upperPxIndex] = 0;
            }

            if (persistenceEffectsEnabled && persistedColorA > 0) {
                const int64_t persistedColorR = (persistedColor >> 16) & 0xff;
                const int64_t persistedColorG = (persistedColor >> 8) & 0xff;
                const int64_t persistedColorB = persistedColor & 0xff;

                int64_t colorR = (upperColor >> 16) & 0xff;
                int64_t colorG = (upperColor >> 8) & 0xff;
                int64_t colorB = upperColor & 0xff;

                colorR = (int64_t) (
                    colorR * (1 - (persistedColorA / 255.0))
                    + persistedColorR * (persistedColorA / 255.0)
                );

                colorG = (int64_t) (
                    colorG * (1 - (persistedColorA / 255.0))
                    + persistedColorG * (persistedColorA / 255.0)
                );

                colorB = (int64_t) (
                    colorB * (1 - (persistedColorA / 255.0))
                    + persistedColorB * (persistedColorA / 255.0)
                );

                upperColor =
                    (255 << 24) |
                    ((colorR & 0xff) << 16) |
                    ((colorG & 0xff) << 8) |
                    (colorB & 0xff)
                ;

                persistedColorA -= persistenceAlphaDecrease;
                if (persistedColorA < 0) {
                    persistedColorA = 0;
                }

                nativeRenderer->persistenceBuffer[upperPxIndex] =
                    ((persistedColorA & 0xff) << 24) |
                    ((persistedColorR & 0xff) << 16) |
                    ((persistedColorG & 0xff) << 8) |
                    (persistedColorB & 0xff)
                ;

                nativeRenderer->currentFrameBuffer[upperPxIndex] = upperColor;
            }

            persistedColor = nativeRenderer->persistenceBuffer[lowerPxIndex];
            persistedColorA = (persistedColor >> 24) & 0xff;

            if (!persistenceEffectsEnabled && persistedColorA > 0) {
                nativeRenderer->persistenceBuffer[lowerPxIndex] = 0;
            }

            if (persistenceEffectsEnabled && persistedColorA > 0) {
                const int64_t persistedColorR = (persistedColor >> 16) & 0xff;
                const int64_t persistedColorG = (persistedColor >> 8) & 0xff;
                const int64_t persistedColorB = persistedColor & 0xff;

                int64_t colorR = (lowerColor >> 16) & 0xff;
                int64_t colorG = (lowerColor >> 8) & 0xff;
                int64_t colorB = lowerColor & 0xff;

                colorR = (int64_t) (
                    colorR * (1 - (persistedColorA / 255.0))
                    + persistedColorR * (persistedColorA / 255.0)
                );

                colorG = (int64_t) (
                    colorG * (1 - (persistedColorA / 255.0))
                    + persistedColorG * (persistedColorA / 255.0)
                );

                colorB = (int64_t) (
                    colorB * (1 - (persistedColorA / 255.0))
                    + persistedColorB * (persistedColorA / 255.0)
                );

                lowerColor =
                    (255 << 24) |
                    ((colorR & 0xff) << 16) |
                    ((colorG & 0xff) << 8) |
                    (colorB & 0xff)
                ;

                persistedColorA -= persistenceAlphaDecrease;
                if (persistedColorA < 0) {
                    persistedColorA = 0;
                }

                nativeRenderer->persistenceBuffer[lowerPxIndex] =
                    ((persistedColorA & 0xff) << 24) |
                    ((persistedColorR & 0xff) << 16) |
                    ((persistedColorG & 0xff) << 8) |
                    (persistedColorB & 0xff)
                ;

                nativeRenderer->currentFrameBuffer[lowerPxIndex] = lowerColor;
            }
            
            if (colorReductionFactor > 1) {
                if ((upperColor & 0xffffff) != 0) {
                    int64_t colorR = (upperColor >> 16) & 0xff;
                    int64_t colorG = (upperColor >> 8) & 0xff;
                    int64_t colorB = upperColor & 0xff;

                    const double brightness = (colorR + colorG + colorB) / (255.0 * 3);

                    colorR = (int64_t) (
                        (colorR / colorReductionFactor + 0.95 + brightness)
                    ) * colorReductionFactor;
                    colorR = colorR > 255 ? 255 : colorR;
    
                    colorG = (int64_t) (
                        (colorG / colorReductionFactor + 0.95 + brightness)
                    ) * colorReductionFactor;
                    colorG = colorG > 255 ? 255 : colorG;
    
                    colorB = (int64_t) (
                        (colorB / colorReductionFactor + 0.95 + brightness)
                    ) * colorReductionFactor;
                    colorB = colorB > 255 ? 255 : colorB;
    
                    upperColor =
                        (255 << 24) |
                        ((colorR & 0xff) << 16) |
                        ((colorG & 0xff) << 8) |
                        (colorB & 0xff)
                    ;
    
                    nativeRenderer->currentFrameBuffer[upperPxIndex] = upperColor;
                }
    
                if ((lowerColor & 0xffffff) != 0) {
                    int64_t colorR = (lowerColor >> 16) & 0xff;
                    int64_t colorG = (lowerColor >> 8) & 0xff;
                    int64_t colorB = lowerColor & 0xff;

                    const double brightness = (colorR + colorG + colorB) / (255.0 * 3);

                    colorR = (int64_t) (
                        (colorR / colorReductionFactor + 0.95 + brightness)
                    ) * colorReductionFactor;
                    colorR = colorR > 255 ? 255 : colorR;

                    colorG = (int64_t) (
                        (colorG / colorReductionFactor + 0.95 + brightness)
                    ) * colorReductionFactor;
                    colorG = colorG > 255 ? 255 : colorG;

                    colorB = (int64_t) (
                        (colorB / colorReductionFactor + 0.95 + brightness)
                    ) * colorReductionFactor;
                    colorB = colorB > 255 ? 255 : colorB;
    
                    lowerColor =
                        (255 << 24) |
                        ((colorR & 0xff) << 16) |
                        ((colorG & 0xff) << 8) |
                        (colorB & 0xff)
                    ;
    
                    nativeRenderer->currentFrameBuffer[lowerPxIndex] = lowerColor;
                }
            }

            const int64_t prevUpperColor = nativeRenderer->previousFrameBuffer[upperPxIndex];
            const int64_t prevLowerColor = nativeRenderer->previousFrameBuffer[lowerPxIndex];

            if (
                upperColor == prevUpperColor &&
                lowerColor == prevLowerColor
            ) {
                continue;
            }

            updatedCharacterCount++;

            int writtenCharCount;

            if (j <= 1 || lastPxCol != j - 1) {
                writtenCharCount = snprintf(
                    bufferCursor,
                    remainingBufferSize,
                    "\033[%zu;%zuH",
                    i / 2, j
                );

                remainingBufferSize -= writtenCharCount;
                bufferCursor += writtenCharCount;
            }

            if (lastUpperColor != upperColor || lastLowerColor != lowerColor) {
                writtenCharCount = snprintf(
                    bufferCursor,
                    remainingBufferSize,
                    "\033[38;2;%lu;%lu;%lu;48;2;%lu;%lu;%lum",
                    (upperColor >> 16) & 0xff, (upperColor >> 8) & 0xff, upperColor & 0xff,
                    (lowerColor >> 16) & 0xff, (lowerColor >> 8) & 0xff, lowerColor & 0xff
                );

                remainingBufferSize -= writtenCharCount;
                bufferCursor += writtenCharCount;

                lastUpperColor = upperColor;
                lastLowerColor = lowerColor;
            }

            writtenCharCount = snprintf(
                bufferCursor,
                remainingBufferSize,
                "▀"
            );

            lastPxCol = j;

            remainingBufferSize -= writtenCharCount;
            bufferCursor += writtenCharCount;

            if (remainingBufferSize < 512) {
                php_output_write(buffer, strlen(buffer));

                buffer[0] = 0;
                bufferCursor = buffer;
                remainingBufferSize = sizeof buffer;
            }
        }
    }

    php_output_write(buffer, strlen(buffer));

    memcpy(
        nativeRenderer->previousFrameBuffer,
        nativeRenderer->currentFrameBuffer,
        nativeRenderer->pixelCount * sizeof(int64_t)
    );

    return updatedCharacterCount;
}
