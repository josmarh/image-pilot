<?php

namespace App\Services\ImageProcessing;

use GdImage;
use RuntimeException;

class ImageResizer
{
    /**
     * Resize an image using the selected mode.
     */
    public function resize(
        GdImage $source,
        ?int $width = null,
        ?int $height = null,
        string $mode = 'contain',
        bool $preventUpscaling = true
    ): GdImage {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            throw new RuntimeException(
                'The source image has invalid dimensions.'
            );
        }

        if ($width === null && $height === null) {
            throw new RuntimeException(
                'Provide at least one target dimension.'
            );
        }

        if (
            ($width !== null && $width <= 0) ||
            ($height !== null && $height <= 0)
        ) {
            throw new RuntimeException(
                'Target dimensions must be greater than zero.'
            );
        }

        if (
            ($width !== null && $width > 12000) ||
            ($height !== null && $height > 12000)
        ) {
            throw new RuntimeException(
                'Target dimensions cannot exceed 12000 pixels.'
            );
        }

        if ($width === null) {
            $width = max(
                1,
                (int) round(
                    $sourceWidth * ($height / $sourceHeight)
                )
            );
        }

        if ($height === null) {
            $height = max(
                1,
                (int) round(
                    $sourceHeight * ($width / $sourceWidth)
                )
            );
        }

        $mode = strtolower($mode);

        if (!in_array(
            $mode,
            ['contain', 'cover', 'stretch'],
            true
        )) {
            throw new RuntimeException(
                'Unsupported resize mode.'
            );
        }

        $scale = match ($mode) {
            'contain' => min(
                $width / $sourceWidth,
                $height / $sourceHeight
            ),

            'cover' => max(
                $width / $sourceWidth,
                $height / $sourceHeight
            ),

            'stretch' => null,
        };

        if ($preventUpscaling && $mode !== 'stretch') {
            $scale = min(1, $scale);
        }

        if ($mode === 'stretch') {
            if (
                $preventUpscaling &&
                ($width > $sourceWidth || $height > $sourceHeight)
            ) {
                $stretchScale = min(
                    $width / $sourceWidth,
                    $height / $sourceHeight,
                    1
                );

                $width = max(
                    1,
                    (int) round($sourceWidth * $stretchScale)
                );

                $height = max(
                    1,
                    (int) round($sourceHeight * $stretchScale)
                );
            }

            $targetWidth = $width;
            $targetHeight = $height;

            $sourceX = 0;
            $sourceY = 0;

            $cropWidth = $sourceWidth;
            $cropHeight = $sourceHeight;
        } else {
            $scaledWidth = max(
                1,
                (int) round($sourceWidth * $scale)
            );

            $scaledHeight = max(
                1,
                (int) round($sourceHeight * $scale)
            );

            if ($mode === 'contain') {
                $targetWidth = $scaledWidth;
                $targetHeight = $scaledHeight;

                $sourceX = 0;
                $sourceY = 0;

                $cropWidth = $sourceWidth;
                $cropHeight = $sourceHeight;
            } else {
                $targetWidth = $width;
                $targetHeight = $height;

                $sourceX = (int) floor(
                    ($scaledWidth - $width) / 2
                );

                $sourceY = (int) floor(
                    ($scaledHeight - $height) / 2
                );

                $cropWidth = (int) floor(
                    $width / $scale
                );

                $cropHeight = (int) floor(
                    $height / $scale
                );

                $sourceX = (int) floor(
                    ($sourceWidth - $cropWidth) / 2
                );

                $sourceY = (int) floor(
                    ($sourceHeight - $cropHeight) / 2
                );
            }
        }

        if ($targetWidth <= 0 || $targetHeight <= 0) {
            throw new RuntimeException(
                'Calculated output dimensions are invalid.'
            );
        }

        if (($targetWidth * $targetHeight) > 40000000) {
            throw new RuntimeException(
                'The output exceeds the maximum allowed pixel count.'
            );
        }

        $destination = imagecreatetruecolor(
            $targetWidth,
            $targetHeight
        );

        if (!$destination instanceof GdImage) {
            throw new RuntimeException(
                'Unable to allocate the destination image.'
            );
        }

        // Preserve transparency for PNG and WebP sources.
        imagealphablending($destination, false);
        imagesavealpha($destination, true);

        $transparent = imagecolorallocatealpha(
            $destination,
            0,
            0,
            0,
            127
        );

        imagefill($destination, 0, 0, $transparent);

        $success = imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        if (!$success) {
            imagedestroy($destination);

            throw new RuntimeException(
                'Unable to resize the image.'
            );
        }

        return $destination;
    }
}