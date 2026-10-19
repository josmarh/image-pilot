<?php

namespace App\Services\ImageProcessing;

use App\Models\ImageProcessingBatch;
use App\Models\ImageProcessingItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImageBatchProcessingService
{
    public function __construct(
        protected ImageInspector $inspector,
    ) {}

    /**
     * Store uploaded images and queue them for asynchronous processing.
     *
     * No image optimization happens in this method.
     *
     * @param array<UploadedFile> $files
     */
    public function queue(
        array $files,
        ?int $userId,
        array $settings = [],
        ?string $guestTokenHash = null,
    ): ImageProcessingBatch {
        if ($userId === null && $guestTokenHash === null) {
            throw new InvalidArgumentException(
                'Guest batches require a guest access token hash.'
            );
        }

        if ($userId !== null && $guestTokenHash !== null) {
            throw new InvalidArgumentException(
                'Authenticated batches cannot use guest access tokens.'
            );
        }

        if (empty($files)) {
            throw new InvalidArgumentException(
                'At least one image must be provided.'
            );
        }

        // Validate basic upload integrity before storing anything.
        foreach ($files as $file) {
            if (
                !$file instanceof UploadedFile ||
                !$file->isValid()
            ) {
                throw new InvalidArgumentException(
                    'One or more uploaded files are invalid.'
                );
            }
        }

        $reference = (string) Str::uuid();

        $disk = Storage::disk('local');

        $baseDirectory = "imagepilot/batches/{$reference}";
        $originalDirectory = "{$baseDirectory}/originals";

        $storedFiles = [];
        $totalOriginalSize = 0;

        try {
            /*
             * Store and inspect all originals before creating database
             * records. If validation or storage fails, remove the files
             * and avoid creating an incomplete batch.
             */
            foreach ($files as $index => $file) {
                $originalName = $file->getClientOriginalName();

                $originalSize = $file->getSize();

                if ($originalSize === false || $originalSize <= 0) {
                    throw new InvalidArgumentException(
                        "The uploaded file '{$originalName}' is empty."
                    );
                }

                $storedFilename = sprintf(
                    '%s_%s',
                    $index + 1,
                    Str::uuid()
                );

                $relativePath = $disk->putFileAs(
                    $originalDirectory,
                    $file,
                    $storedFilename
                );

                if ($relativePath === false) {
                    throw new RuntimeException(
                        "Unable to store '{$originalName}'."
                    );
                }

                $absolutePath = $disk->path($relativePath);

                // Validate the actual image content, not just its extension.
                $inspection = $this->inspector->inspect($absolutePath);

                $storedFiles[] = [
                    'original_filename' => $originalName,
                    'original_path' => $relativePath,
                    'original_size_bytes' => $inspection['size_bytes'],
                    'original_width' => $inspection['width'],
                    'original_height' => $inspection['height'],
                    'original_format' => $inspection['format'],
                ];

                $totalOriginalSize += $inspection['size_bytes'];
            }

            /*
             * Create the batch and all item records together.
             * Every item receives original_path, which is required
             * by the current database schema.
             */
            $batch = DB::transaction(function () use (
                $userId,
                $reference,
                $files,
                $settings,
                $storedFiles,
                $totalOriginalSize,
                $guestTokenHash
            ) {
                $batch = ImageProcessingBatch::create([
                    'user_id' => $userId,
                    'batch_reference' => $reference,
                    'original_count' => count($files),
                    'successful_count' => 0,
                    'failed_count' => 0,
                    'original_size_bytes' => $totalOriginalSize,
                    'optimized_size_bytes' => 0,
                    'status' => 'pending',
                    'settings' => $settings,
                    'expires_at' => now()->addDays(
                        config('imagepilot.batch.retention_days', 1)
                    ),
                    'guest_token_hash' => $guestTokenHash,
                ]);

                foreach ($storedFiles as $storedFile) {
                    ImageProcessingItem::create([
                        'batch_id' => $batch->id,

                        'original_filename' =>
                            $storedFile['original_filename'],

                        'original_path' =>
                            $storedFile['original_path'],

                        'original_size_bytes' =>
                            $storedFile['original_size_bytes'],

                        'original_width' =>
                            $storedFile['original_width'],

                        'original_height' =>
                            $storedFile['original_height'],

                        'original_format' =>
                            $storedFile['original_format'],

                        'status' => 'pending',
                    ]);
                }

                return $batch;
            });

            return $batch->fresh('items');
        } catch (Throwable $exception) {
            // Remove stored originals if validation or DB persistence fails.
            if ($disk->exists($baseDirectory)) {
                $disk->deleteDirectory($baseDirectory);
            }

            throw $exception;
        }
    }
}