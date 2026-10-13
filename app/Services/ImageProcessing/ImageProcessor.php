<?php

namespace App\Services\ImageProcessing;

use GdImage;
use RuntimeException;
use Throwable;

class ImageProcessor
{
    public function __construct(
        private readonly ImageInspector $inspector
    ) {}


    /**
     * Load an image into a GD image resource.
     *
     * Corrects JPEG EXIF orientation where the PHP EXIF extension is available.
     *
     * @throws RuntimeException
     */
    public function load(string $path): GdImage
    {
        $imageInfo = $this->inspector->inspect($path);

        $image = match ($imageInfo['format']) {
            'jpg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($path)
                : false,
            default => false,
        };

        if (!$image instanceof GdImage) {
            throw new RuntimeException(
                'Unable to load the image. Check your PHP GD installation '
                . 'and supported image formats.'
            );
        }

        if ($imageInfo['format'] !== 'jpg') {
            return $image;
        }

        try {
            if (!function_exists('exif_read_data')) {
                return $image;
            }

            $exif = @exif_read_data($path, 'IFD0', true);

            $orientation = (int) (
                $exif['IFD0']['Orientation']
                ?? $exif['Orientation']
                ?? 1
            );

            $correctedImage = $this->correctJpegOrientation(
                $image,
                $orientation
            );

            if ($correctedImage !== $image) {
                imagedestroy($image);
                $image = $correctedImage;
            }

            return $image;
        } catch (Throwable $exception) {
            imagedestroy($image);

            throw new RuntimeException(
                'Unable to process the JPEG image orientation.',
                previous: $exception
            );
        }
    }


    /**
     * Apply the EXIF orientation transform to a loaded JPEG.
     */
    protected function correctJpegOrientation(
        GdImage $image,
        int $orientation
    ): GdImage {
        $rotate = static function (
            GdImage $source,
            float $angle
        ): GdImage {
            $transparent = imagecolorallocatealpha(
                $source,
                0,
                0,
                0,
                127
            );

            $rotated = imagerotate($source, $angle, $transparent);

            if (!$rotated instanceof GdImage) {
                throw new RuntimeException(
                    'Unable to rotate the JPEG image.'
                );
            }

            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);

            return $rotated;
        };

        switch ($orientation) {
            case 2:
                if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
                    throw new RuntimeException(
                        'Unable to flip the JPEG image.'
                    );
                }

                return $image;

            case 3:
                return $rotate($image, 180);

            case 4:
                if (!imageflip($image, IMG_FLIP_VERTICAL)) {
                    throw new RuntimeException(
                        'Unable to flip the JPEG image.'
                    );
                }

                return $image;

            case 5:
                if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
                    throw new RuntimeException(
                        'Unable to flip the JPEG image.'
                    );
                }

                return $rotate($image, 90);

            case 6:
                return $rotate($image, -90);

            case 7:
                if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
                    throw new RuntimeException(
                        'Unable to flip the JPEG image.'
                    );
                }

                return $rotate($image, -90);

            case 8:
                return $rotate($image, 90);

            default:
                return $image;
        }
    }


    /**
     * Release a GD image resource.
     */
    public function release(?GdImage $image): void
    {
        if ($image instanceof GdImage) {
            imagedestroy($image);
        }
    }
}