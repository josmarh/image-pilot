<?php

namespace App\Services\ImageProcessing;

use App\Models\ImageProcessingBatch;
use App\Models\ImageProcessingItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImageBatchWorkerService
{
    private const MAX_ATTEMPTS = 3;
    private const LEASE_MINUTES = 10;

    public function __construct(
        protected ImageOptimizationService $optimizationService,
    ) {}

    /**
     * Process a bounded number of pending items.
     */
    public function processPendingItems(int $limit = 5): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'The processing limit must be at least 1.'
            );
        }

        $summary = [
            'processed' => 0,
            'successful' => 0,
            'retried' => 0,
            'failed' => 0,
            'lost' => 0,
        ];

        $this->recoverStaleItems();

        for ($i = 0; $i < $limit; $i++) {
            $claim = $this->claimNextItem();

            if ($claim === null) {
                break;
            }

            $summary['processed']++;

            $result = $this->processClaimedItem($claim);

            match ($result) {
                'completed' => $summary['successful']++,
                'retried' => $summary['retried']++,
                'failed' => $summary['failed']++,
                default => $summary['lost']++,
            };
        }

        return $summary;
    }

    /**
     * Claim one item using a short transaction.
     */
    protected function claimNextItem(): ?array
    {
        return DB::transaction(function () {
            $item = ImageProcessingItem::query()
                ->where('status', 'pending')
                ->where(function ($query) {
                    $query->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                })
                ->whereHas('batch', function ($query) {
                    $query->whereIn('status', [
                        'pending',
                        'processing',
                    ]);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$item) {
                return null;
            }

            $batch = ImageProcessingBatch::query()
                ->whereKey($item->batch_id)
                ->lockForUpdate()
                ->first();

            if (
                !$batch ||
                !in_array($batch->status, ['pending', 'processing'], true)
            ) {
                return null;
            }

            $claimToken = (string) Str::uuid();

            $item->update([
                'status' => 'processing',
                'claim_token' => $claimToken,
                'claimed_at' => now(),
                'attempts' => $item->attempts + 1,
                'available_at' => null,
                'error_message' => null,
            ]);

            if ($batch->status === 'pending') {
                $batch->update([
                    'status' => 'processing',
                ]);
            }

            return [
                'batch_id' => $batch->id,
                'item_id' => $item->id,
                'claim_token' => $claimToken,
            ];
        }, 3);
    }

    /**
     * Process an item outside a database transaction.
     */
    protected function processClaimedItem(array $claim): string
    {
        $disk = Storage::disk('local');
        $outputRelativePath = null;

        try {
            $batch = ImageProcessingBatch::findOrFail(
                $claim['batch_id']
            );

            $item = ImageProcessingItem::query()
                ->where('batch_id', $batch->id)
                ->findOrFail($claim['item_id']);

            if (!$this->ownsClaim($item, $claim['claim_token'])) {
                return 'lost';
            }

            $originalRelativePath = $item->original_path;

            if (
                !$originalRelativePath ||
                !$disk->exists($originalRelativePath)
            ) {
                throw new RuntimeException(
                    'The original image file could not be found.'
                );
            }

            $sourcePath = $disk->path($originalRelativePath);
            $settings = $batch->settings ?? [];

            $outputFormat = strtolower(
                $settings['output_format']
                    ?? $item->original_format
            );

            if ($outputFormat === 'jpeg') {
                $outputFormat = 'jpg';
            }

            if (!in_array(
                $outputFormat,
                ['jpg', 'png', 'webp'],
                true
            )) {
                throw new InvalidArgumentException(
                    'Unsupported output format.'
                );
            }

            $baseName = Str::slug(
                pathinfo(
                    $item->original_filename,
                    PATHINFO_FILENAME
                )
            );

            if ($baseName === '') {
                $baseName = 'image';
            }

            // A unique output path for each attempt prevents an older
            // worker from overwriting a newer attempt's output.
            $outputFilename = sprintf(
                '%s_%s.%s',
                $baseName,
                Str::uuid(),
                $outputFormat
            );

            $outputRelativePath = sprintf(
                'imagepilot/batches/%s/outputs/%s',
                $batch->batch_reference,
                $outputFilename
            );

            $outputPath = $disk->path($outputRelativePath);

            $result = $this->optimizationService->optimize(
                sourcePath: $sourcePath,
                outputPath: $outputPath,
                settings: $settings,
            );

            // Save the result only if this worker still owns the claim.
            $saved = DB::transaction(function () use (
                $claim,
                $result,
                $outputFilename,
                $outputRelativePath
            ) {
                $item = ImageProcessingItem::query()
                    ->whereKey($claim['item_id'])
                    ->lockForUpdate()
                    ->first();

                if (
                    !$item ||
                    !$this->ownsClaim(
                        $item,
                        $claim['claim_token']
                    )
                ) {
                    return false;
                }

                $item->update([
                    'output_filename' => $outputFilename,
                    'output_path' => $outputRelativePath,
                    'output_size_bytes' =>
                        $result['output']['size_bytes'],
                    'output_width' => $result['output']['width'],
                    'output_height' => $result['output']['height'],
                    'output_format' => $result['output']['format'],

                    'status' => 'completed',
                    'claim_token' => null,
                    'claimed_at' => null,
                    'available_at' => null,
                    'error_message' => null,
                ]);

                $this->refreshBatchStatus(
                    (int) $item->batch_id
                );

                return true;
            }, 3);

            if (!$saved) {
                // The lease was reclaimed by another worker.
                $disk->delete($outputRelativePath);

                return 'lost';
            }
            return 'completed';
        } catch (Throwable $exception) {
            if (
                $outputRelativePath !== null &&
                $disk->exists($outputRelativePath)
            ) {
                $disk->delete($outputRelativePath);
            }

            return $this->recordFailure($claim, $exception);
        }
    }

    /**
     * Check whether the worker still owns this item.
     */
    protected function ownsClaim(
        ImageProcessingItem $item,
        string $claimToken
    ): bool {
        return $item->status === 'processing'
            && $item->claim_token === $claimToken;
    }

    /**
     * Retry a retryable failure or mark the item permanently failed.
     */
    protected function recordFailure(
        array $claim,
        Throwable $exception
    ): string {
        return DB::transaction(function () use (
            $claim,
            $exception
        ) {
            // Lock batch FIRST.
            $batch = ImageProcessingBatch::query()
                ->whereKey($claim['batch_id'])
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                return 'lost';
            }

            // Lock item SECOND.
            $item = ImageProcessingItem::query()
                ->whereKey($claim['item_id'])
                ->lockForUpdate()
                ->first();

            if (
                !$item ||
                (int) $item->batch_id !== (int) $batch->id ||
                !$this->ownsClaim(
                    $item,
                    $claim['claim_token']
                )
            ) {
                return 'lost';
            }

            $permanentFailure =
                $exception instanceof InvalidArgumentException;

            $canRetry = !$permanentFailure
                && $item->attempts < self::MAX_ATTEMPTS;

            if ($canRetry) {
                $delaySeconds = 30 * (2 ** ($item->attempts - 1));

                $item->update([
                    'status' => 'pending',
                    'claim_token' => null,
                    'claimed_at' => null,
                    'available_at' => now()->addSeconds(
                        $delaySeconds
                    ),
                    'error_message' => Str::limit(
                        $exception->getMessage(),
                        5000
                    ),
                ]);

                $this->refreshBatchStatus(
                    (int) $batch->id
                );

                return 'retried';
            }

            $item->update([
                'status' => 'failed',
                'claim_token' => null,
                'claimed_at' => null,
                'available_at' => null,
                'error_message' => Str::limit(
                    $exception->getMessage(),
                    5000
                ),
            ]);

            $this->refreshBatchStatus(
                (int) $batch->id
            );

            return 'failed';
        }, 3);
    }

    /**
     * Requeue stale items or mark exhausted items as failed.
     */
    protected function recoverStaleItems(): void
    {
        $cutoff = now()->subMinutes(self::LEASE_MINUTES);

        ImageProcessingItem::query()
            ->where('status', 'processing')
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('claimed_at')
                    ->orWhere('claimed_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->limit(100)
            ->pluck('id')
            ->each(function ($itemId) use ($cutoff) {
                $batchId = DB::transaction(function () use (
                    $itemId,
                    $cutoff
                ) {
                    $itemLookup = ImageProcessingItem::query()
                        ->whereKey($itemId)
                        ->first();

                    if (!$itemLookup || $itemLookup->status !== 'processing') {
                        return null;
                    }

                    $batch = ImageProcessingBatch::query()
                        ->whereKey($itemLookup->batch_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$batch) {
                        return null;
                    }

                    $item = ImageProcessingItem::query()
                        ->whereKey($itemId)
                        ->lockForUpdate()
                        ->first();

                    if (!$item || $item->status !== 'processing') {
                        return null;
                    }

                    // Recheck after obtaining the locks.
                    if (
                        $item->claimed_at !== null &&
                        $item->claimed_at->gt($cutoff)
                    ) {
                        return null;
                    }

                    if ($item->attempts >= self::MAX_ATTEMPTS) {
                        $item->update([
                            'status' => 'failed',
                            'claim_token' => null,
                            'claimed_at' => null,
                            'available_at' => null,
                            'error_message' =>
                                'Processing lease expired after the maximum number of attempts.',
                        ]);
                    } else {
                        $item->update([
                            'status' => 'pending',
                            'claim_token' => null,
                            'claimed_at' => null,
                            'available_at' => now(),
                            'error_message' =>
                                'Previous processing attempt expired; item queued for retry.',
                        ]);
                    }

                    $this->refreshBatchStatus(
                        (int) $batch->id
                    );

                    return (int) $batch->id;
                }, 3);
            });
    }

    /**
     * Recalculate batch counters from persisted item records.
     */
    protected function refreshBatchStatus(int $batchId): void
    {
        $batch = ImageProcessingBatch::query()
            ->whereKey($batchId)
            ->lockForUpdate()
            ->first();

        if (!$batch) {
            return;
        }

        $items = ImageProcessingItem::query()
            ->where('batch_id', $batchId);

        $successfulCount = (clone $items)
            ->where('status', 'completed')
            ->count();

        $failedCount = (clone $items)
            ->where('status', 'failed')
            ->count();

        $pendingCount = (clone $items)
            ->where('status', 'pending')
            ->count();

        $processingCount = (clone $items)
            ->where('status', 'processing')
            ->count();

        $optimizedSizeBytes = (int) (
            (clone $items)
                ->where('status', 'completed')
                ->sum('output_size_bytes')
        );

        if ($pendingCount > 0 || $processingCount > 0) {
            $status = 'processing';
        } elseif ($failedCount === 0) {
            $status = 'completed';
        } elseif ($successfulCount > 0) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        $batch->update([
            'successful_count' => $successfulCount,
            'failed_count' => $failedCount,
            'optimized_size_bytes' => $optimizedSizeBytes,
            'status' => $status,
        ]);
    }
}
