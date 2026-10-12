<?php

return [

    'worker' => [
        'limit' => (int) env('IMAGEPILOT_WORKER_LIMIT', 5),
    ],
    'batch' => [
        'retention_days' => (int) env(
            'IMAGEPILOT_BATCH_RETENTION_DAYS',
            1
        ),
    ],
];