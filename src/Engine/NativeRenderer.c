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

    nativeRenderer->drawnBitmapPixelCount = 0;

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

    nativeRenderer->drawnBitmapPixelCount = 0;
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
    int64_t globalBlendingColor,
    int64_t * verticalBlendingColors,
    int64_t persisted,
    int64_t globalPersistedColor,
    int64_t * horizontalDistortionOffsets,
    int64_t * horizontalBackgroundDistortionOffsets,
    float ditheringAlphaRatioThreshold
) {
    if (globalAlpha == 0) {
        return;
    }

    const double fullBrightnessReciprocal = 1 / 255.0;

    for (size_t i = 0; i < bitmapHeight; i++) {
        const size_t pxPosY = y + i;

        if (
            pxPosY < 0 || pxPosY >= nativeRenderer->height
        ) {
            continue;
        }

        const int64_t horizontalDistortionOffset = horizontalDistortionOffsets[i];
        const int64_t horizontalBackgroundDistortionOffset = horizontalBackgroundDistortionOffsets[i];

        for (size_t j = 0; j < bitmapWidth; j++) {
            const size_t pxPosX = x + j + horizontalDistortionOffset;

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

            int64_t blendingColor = globalBlendingColor;
            const int64_t verticalBlendingColor = verticalBlendingColors[j];
            if (blendingColor == -1) {
                blendingColor = verticalBlendingColor;
            } else if (verticalBlendingColor != -1) {
                const int64_t verticalBlendingColorA = (verticalBlendingColor >> 24) & 0xff;
                if (verticalBlendingColorA != 0) {
                    int64_t blendingColorA = (blendingColor >> 24) & 0xff;
                    if (blendingColorA == 0) {
                        blendingColor = verticalBlendingColor;
                    } else {
                        const int64_t verticalBlendingColorR = (verticalBlendingColor >> 16) & 0xff;
                        const int64_t verticalBlendingColorG = (verticalBlendingColor >> 8) & 0xff;
                        const int64_t verticalBlendingColorB = (verticalBlendingColor >> 0) & 0xff;
                        int64_t blendingColorR = (blendingColor >> 16) & 0xff;
                        int64_t blendingColorG = (blendingColor >> 8) & 0xff;
                        int64_t blendingColorB = (blendingColor >> 0) & 0xff;

                        const double blendingColorAlphaRatio = blendingColorA / ((double) blendingColorA + verticalBlendingColorA);

                        blendingColorR = (int64_t) (
                            blendingColorR * blendingColorAlphaRatio
                                + verticalBlendingColorR * (1 - blendingColorAlphaRatio)
                        );

                        blendingColorG = (int64_t) (
                            blendingColorG * blendingColorAlphaRatio
                                + verticalBlendingColorG * (1 - blendingColorAlphaRatio)
                        );

                        blendingColorB = (int64_t) (
                            blendingColorB * blendingColorAlphaRatio
                                + verticalBlendingColorB * (1 - blendingColorAlphaRatio)
                        );

                        blendingColorA = (int64_t) (
                            blendingColorA * blendingColorAlphaRatio
                                + verticalBlendingColorA * (1 - blendingColorAlphaRatio)
                        );

                        blendingColor =
                            blendingColorA << 24
                            | blendingColorR << 16
                            | blendingColorG << 8
                            | blendingColorB
                        ;
                    }
                }
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

                    const double blendingColorAlphaRatio = blendingColorA * fullBrightnessReciprocal;

                    colorR = (int64_t) (
                        blendingColorR * blendingColorAlphaRatio
                        + colorR * (1 - blendingColorAlphaRatio)
                    );

                    colorG = (int64_t) (
                        blendingColorG * blendingColorAlphaRatio
                        + colorG * (1 - blendingColorAlphaRatio)
                    );

                    colorB = (int64_t) (
                        blendingColorB * blendingColorAlphaRatio
                        + colorB * (1 - blendingColorAlphaRatio)
                    );
                }

                colorR = (int64_t) (colorR * brightness);
                colorG = (int64_t) (colorG * brightness);
                colorB = (int64_t) (colorB * brightness);

                int64_t combinedAlpha = 255;
                if (globalAlpha < 255 || alpha < 255) {
                    combinedAlpha = (int64_t) (255 * (globalAlpha * fullBrightnessReciprocal) * (alpha * fullBrightnessReciprocal));
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
                    double combinedAlphaRatio = combinedAlpha * fullBrightnessReciprocal;

                    if (ditheringAlphaRatioThreshold > 0 && combinedAlphaRatio <= ditheringAlphaRatioThreshold) {
                        const uint64_t rn = (214013 * pxIndex + 2531011) & 0xffff;
                        combinedAlphaRatio = (combinedAlphaRatio * 0xffff) < rn ? 0.0 : 1.0;

                        if (combinedAlphaRatio == 0.0 && horizontalBackgroundDistortionOffset == 0) {
                            continue;
                        }
                    }

                    if (combinedAlphaRatio < 1) {
                        const int64_t backgroundColor = pxIndex + horizontalBackgroundDistortionOffset < nativeRenderer->pixelCount
                            ? nativeRenderer->currentFrameBuffer[pxIndex + horizontalBackgroundDistortionOffset] : 0;

                        const int64_t backgroundColorR = (backgroundColor >> 16) & 0xff;
                        const int64_t backgroundColorG = (backgroundColor >> 8) & 0xff;
                        const int64_t backgroundColorB = backgroundColor & 0xff;

                        if (combinedAlphaRatio == 0.0) {
                            colorR = backgroundColorR;
                            colorG = backgroundColorG;
                            colorB = backgroundColorB;
                        } else {
                            colorR = (int64_t) (
                                colorR * combinedAlphaRatio
                                + backgroundColorR * (1 - combinedAlphaRatio)
                            );

                            colorG = (int64_t) (
                                colorG * combinedAlphaRatio
                                + backgroundColorG * (1 - combinedAlphaRatio)
                            );

                            colorB = (int64_t) (
                                colorB * combinedAlphaRatio
                                + backgroundColorB * (1 - combinedAlphaRatio)
                            );
                        }
                    }
                }

                color =
                    (255 << 24) |
                    ((colorR & 0xff) << 16) |
                    ((colorG & 0xff) << 8) |
                    (colorB & 0xff)
                ;
            }

            nativeRenderer->currentFrameBuffer[pxIndex] = color;
            nativeRenderer->drawnBitmapPixelCount++;
            if (persisted && alpha > 1) {
                const int64_t currentPersistedColor = nativeRenderer->persistenceBuffer[pxIndex];
                const int64_t currentPersistedColorA = (currentPersistedColor >> 24) & 0xff;
                if (currentPersistedColorA <= 2) {
                    nativeRenderer->persistenceBuffer[pxIndex] = persistedColor;
                } else {
                    int64_t persistedColorA = (persistedColor >> 24) & 0xff;
                    double blendingRatio = persistedColorA / (double) (persistedColorA + currentPersistedColorA);

                    if (ditheringAlphaRatioThreshold > 0 && blendingRatio <= ditheringAlphaRatioThreshold) {
                        const uint64_t rn = (214013 * pxIndex + 2531011) & 0xffff;
                        blendingRatio = (blendingRatio * 0xffff) < rn ? 0.0 : 1.0;
                    }

                    if (blendingRatio == 1.0) {
                        nativeRenderer->persistenceBuffer[pxIndex] = persistedColor;
                    } else if (blendingRatio > 0) {
                        const int64_t currentPersistedColorR = (currentPersistedColor >> 16) & 0xff;
                        const int64_t currentPersistedColorG = (currentPersistedColor >> 8) & 0xff;
                        const int64_t currentPersistedColorB = currentPersistedColor & 0xff;

                        int64_t persistedColorR = (persistedColor >> 16) & 0xff;
                        int64_t persistedColorG = (persistedColor >> 8) & 0xff;
                        int64_t persistedColorB = persistedColor & 0xff;

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
            nativeRenderer->drawnBitmapPixelCount++;
        }
    }
}

size_t NativeRenderer_getDrawnBitmapPixelCount(
    NativeRenderer * nativeRenderer
) {
    return nativeRenderer->drawnBitmapPixelCount;
}

size_t NativeRenderer_update(
    NativeRenderer * nativeRenderer,
    int64_t trueColorModeEnabled,
    int64_t persistenceEffectsEnabled,
    int64_t persistenceAlphaDecrease,
    int64_t removedColorDepthBits
) {
    static char buffer[4 * 1024];

    buffer[0] = 0;
    char * bufferCursor = buffer;
    size_t remainingBufferSize = sizeof buffer;

    size_t updatedCharacterCount = 0;
    const double fullBrightnessReciprocal = 1 / 255.0;
    const uint64_t colorReductionCorrectionMask = removedColorDepthBits != 0 ? 1 << (removedColorDepthBits - 1) : 0;

    int lastPxCol;
    int lastUpperColor = 0, lastLowerColor = 0;

    for (size_t i = 0; i < nativeRenderer->height; i += 2) {
        lastPxCol = nativeRenderer->width;
        for (size_t j = 0; j < nativeRenderer->width; j++) {
            size_t upperPxIndex = 0;
            size_t lowerPxIndex = 0;

            int64_t upperColor = 0;
            int64_t lowerColor = 0;

            for (size_t k = 0; k < 2; k++) {
                const size_t pxIndex = (i + k) * nativeRenderer->width + j;

                int64_t color = nativeRenderer->currentFrameBuffer[pxIndex];
                int64_t persistedColor = nativeRenderer->persistenceBuffer[pxIndex];

                int64_t persistedColorA = (persistedColor >> 24) & 0xff;

                if (!persistenceEffectsEnabled && persistedColorA > 0) {
                    nativeRenderer->persistenceBuffer[pxIndex] = 0;
                }

                if (persistenceEffectsEnabled && persistedColorA > 0) {
                    const int64_t persistedColorR = (persistedColor >> 16) & 0xff;
                    const int64_t persistedColorG = (persistedColor >> 8) & 0xff;
                    const int64_t persistedColorB = persistedColor & 0xff;

                    int64_t colorR = (color >> 16) & 0xff;
                    int64_t colorG = (color >> 8) & 0xff;
                    int64_t colorB = color & 0xff;

                    const double persistedColorAlphaRatio = persistedColorA * fullBrightnessReciprocal;

                    colorR = (int64_t) (
                        colorR * (1 - persistedColorAlphaRatio)
                        + persistedColorR * persistedColorAlphaRatio
                    );

                    colorG = (int64_t) (
                        colorG * (1 - persistedColorAlphaRatio)
                        + persistedColorG * persistedColorAlphaRatio
                    );

                    colorB = (int64_t) (
                        colorB * (1 - persistedColorAlphaRatio)
                        + persistedColorB * persistedColorAlphaRatio
                    );

                    color =
                        (255 << 24) |
                        ((colorR & 0xff) << 16) |
                        ((colorG & 0xff) << 8) |
                        (colorB & 0xff)
                    ;

                    persistedColorA -= persistenceAlphaDecrease;
                    if (persistedColorA < 0) {
                        persistedColorA = 0;
                    }

                    nativeRenderer->persistenceBuffer[pxIndex] =
                        ((persistedColorA & 0xff) << 24) |
                        ((persistedColorR & 0xff) << 16) |
                        ((persistedColorG & 0xff) << 8) |
                        (persistedColorB & 0xff)
                    ;

                    nativeRenderer->currentFrameBuffer[pxIndex] = color;
                }

                if (removedColorDepthBits > 0 && (color & 0xffffff) != 0) {
                    int64_t colorR = (color >> 16) & 0xff;
                    int64_t colorG = (color >> 8) & 0xff;
                    int64_t colorB = color & 0xff;

                    color =
                        (255 << 24) |
                        ((((colorR >> removedColorDepthBits) << removedColorDepthBits) | colorReductionCorrectionMask)<< 16) |
                        ((((colorG >> removedColorDepthBits) << removedColorDepthBits) | colorReductionCorrectionMask) << 8) |
                        (((colorB >> removedColorDepthBits) << removedColorDepthBits) | colorReductionCorrectionMask)
                    ;

                    nativeRenderer->currentFrameBuffer[pxIndex] = color;
                }

                if (k == 0) {
                    upperPxIndex = pxIndex;
                    upperColor = color;
                } else {
                    lowerPxIndex = pxIndex;
                    lowerColor = color;
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
                if (trueColorModeEnabled) {
                    writtenCharCount = snprintf(
                        bufferCursor,
                        remainingBufferSize,
                        "\033[38;2;%lu;%lu;%lu;48;2;%lu;%lu;%lum",
                        (upperColor >> 16) & 0xff, (upperColor >> 8) & 0xff, upperColor & 0xff,
                        (lowerColor >> 16) & 0xff, (lowerColor >> 8) & 0xff, lowerColor & 0xff
                    );

                    lastUpperColor = upperColor;
                    lastLowerColor = lowerColor;
                } else {
                    double brightnessBoost = 0.3;

                    const int upperColorTableIdx = 16 + fmin(215,
                        + 36 * (int) round(brightnessBoost + 5 * ((upperColor >> 16) & 0xff) * fullBrightnessReciprocal)
                        + 6 * (int) round(brightnessBoost + 5 * ((upperColor >> 8) & 0xff) * fullBrightnessReciprocal)
                        + (int) round(brightnessBoost + 5 * ((upperColor >> 0) & 0xff) * fullBrightnessReciprocal)
                    );

                    const int lowerColorTableIdx = 16 + fmin(215,
                        + 36 * (int) round(brightnessBoost + 5 * ((lowerColor >> 16) & 0xff) * fullBrightnessReciprocal)
                        + 6 * (int) round(brightnessBoost + 5 * ((lowerColor >> 8) & 0xff) * fullBrightnessReciprocal)
                        + (int) round(brightnessBoost + 5 * ((lowerColor >> 0) & 0xff) * fullBrightnessReciprocal)
                    );

                    writtenCharCount = snprintf(
                        bufferCursor,
                        remainingBufferSize,
                        "\033[38;5;%d;48;5;%dm",
                        upperColorTableIdx,
                        lowerColorTableIdx
                    );

                    lastUpperColor = upperColorTableIdx;
                    lastLowerColor = lowerColorTableIdx;
                }

                remainingBufferSize -= writtenCharCount;
                bufferCursor += writtenCharCount;
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
                php_output_flush();

                buffer[0] = 0;
                bufferCursor = buffer;
                remainingBufferSize = sizeof buffer;
            }
        }
    }

    php_output_write(buffer, strlen(buffer));
    php_output_flush();

    memcpy(
        nativeRenderer->previousFrameBuffer,
        nativeRenderer->currentFrameBuffer,
        nativeRenderer->pixelCount * sizeof(int64_t)
    );

    return updatedCharacterCount;
}
