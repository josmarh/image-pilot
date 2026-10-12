<?php

namespace App\Console\Commands;

use App\Services\ImageProcessing\ImageBatchWorkerService;
use Illuminate\Console\Command;
use Throwable;

class ProcessImageBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imagepilot:process-batches
                            {--limit=5 : Maximum number of items to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending ImagePilot image batches';

    public function __construct(
        protected ImageBatchWorkerService $worker,
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
            $this->error('The processing limit must be at least 1.');

            return self::FAILURE;
        }

        $this->info(
            "Starting ImagePilot batch worker (limit: {$limit})..."
        );

        try {
            $result = $this->worker->processPendingItems($limit);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Processed', $result['processed']],
                    ['Successful', $result['successful']],
                    ['Retried', $result['retried']],
                    ['Failed', $result['failed']],
                    ['Lost', $result['lost']],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'ImagePilot batch worker failed: '
                . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
