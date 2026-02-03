<?php

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\OutOfOfficeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOutOfOfficeStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(OutOfOfficeService $outOfOfficeService): bool
    {
        try {
            $result = $outOfOfficeService->syncNextBatch(20);

            Log::info('OutOfOfficeStatusJob: Batch sync completed', [
                'success' => $result['success'],
                'failed' => $result['failed'],
                'total' => $result['total'],
            ]);

            return $result['total'] > 0;
        } catch (\Exception $e) {
            Log::error('OutOfOfficeStatusJob: Failed to sync batch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
