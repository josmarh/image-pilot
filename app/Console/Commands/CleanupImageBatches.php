<?php

namespace App\Console\Commands;

use App\Services\ImageProcessing\ImageBatchCleanupService;
use Illuminate\Console\Command;
use Throwable;

class CleanupImageBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imagepilot:cleanup-batches
                            {--limit=100 : Maximum number of batches to clean}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired ImagePilot batches and files';

    public function __construct(
        protected ImageBatchCleanupService $cleanupService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1) {
            $this->error(
                'The cleanup limit must be at least 1.'
            );

            return self::FAILURE;
        }

        $this->info(
            "Starting ImagePilot batch cleanup (limit: {$limit})..."
        );

        try {
            $result = $this->cleanupService->cleanup($limit);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Processed', $result['processed']],
                    ['Deleted', $result['deleted']],
                    ['Skipped', $result['skipped']],
                    ['Failed', $result['failed']],
                ]
            );

            return $result['failed'] > 0
                ? self::FAILURE
                : self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'ImagePilot batch cleanup failed: '
                . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
