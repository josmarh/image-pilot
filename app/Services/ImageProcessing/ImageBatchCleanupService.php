<?php

namespace App\Services\ImageProcessing;

use App\Models\ImageProcessingBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageBatchCleanupService
{
    /**
     * Remove expired batches and their associated files.
     *
     * @return array{
     *     processed: int,
     *     deleted: int,
     *     skipped: int,
     *     failed: int
     * }
     */
    public function cleanup(int $limit = 100): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException(
                'The cleanup limit must be at least 1.'
            );
        }

        $summary = [
            'processed' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        ImageProcessingBatch::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $batchId) use (&$summary) {
                $summary['processed']++;

                try {
                    $result = $this->deleteBatch($batchId);

                    if ($result === 'deleted') {
                        $summary['deleted']++;
                    } else {
                        $summary['skipped']++;
                    }
                } catch (Throwable $exception) {
                    report($exception);

                    $summary['failed']++;
                }
            });

        return $summary;
    }

    /**
     * Delete one expired batch.
     */
    protected function deleteBatch(int $batchId): string
    {
        return DB::transaction(function () use ($batchId) {
            $batch = ImageProcessingBatch::query()
                ->whereKey($batchId)
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                return 'skipped';
            }

            if (
                !$batch->expires_at ||
                $batch->expires_at->isFuture()
            ) {
                return 'skipped';
            }

            /*
             * Never remove a batch while an image worker is actively
             * processing one of its items.
             *
             * The worker and cleanup service both lock the batch first,
             * preventing a race with claim processing.
             */
            if ($batch->status === 'processing') {
                return 'skipped';
            }

            $directory = "imagepilot/batches/{$batch->batch_reference}";

            $disk = Storage::disk('local');

            /*
             * Delete all batch files before removing the database record.
             * If deletion fails, the transaction is rolled back and the
             * batch remains available for another cleanup attempt.
             */
            if ($disk->exists($directory)) {
                $deleted = $disk->deleteDirectory($directory);

                if (!$deleted && $disk->exists($directory)) {
                    throw new \RuntimeException(
                        "Unable to delete batch directory: {$directory}"
                    );
                }
            }

            /*
             * image_processing_items are deleted automatically through
             * the batch_id foreign-key cascade.
             */
            $batch->delete();

            return 'deleted';
        }, 3);
    }
}