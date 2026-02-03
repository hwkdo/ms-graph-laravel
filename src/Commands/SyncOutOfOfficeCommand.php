<?php

namespace Hwkdo\MsGraphLaravel\Commands;

use Hwkdo\MsGraphLaravel\Services\OutOfOfficeService;
use Illuminate\Console\Command;

class SyncOutOfOfficeCommand extends Command
{
    public $signature = 'ms-graph:sync-out-of-office {--all : Sync all active users in batches}';

    public $description = 'Synchronize out-of-office status for active users';

    public function handle(OutOfOfficeService $outOfOfficeService): int
    {
        if ($this->option('all')) {
            $this->info('Syncing all active users...');
            $totalSynced = 0;
            $totalFailed = 0;
            $batchCount = 0;

            do {
                $result = $outOfOfficeService->syncNextBatch(20);
                $totalSynced += $result['success'];
                $totalFailed += $result['failed'];
                $batchCount++;

                $this->line("Batch {$batchCount}: {$result['success']} successful, {$result['failed']} failed");

                if ($result['total'] === 0) {
                    break;
                }

                // Small delay between batches to avoid rate limiting
                if ($result['total'] > 0) {
                    sleep(1);
                }
            } while ($result['total'] > 0);

            $this->info("Completed: {$totalSynced} users synced, {$totalFailed} failed in {$batchCount} batches");

            return self::SUCCESS;
        }

        $this->info('Syncing next batch of users...');
        $result = $outOfOfficeService->syncNextBatch(20);

        $this->info("Batch completed: {$result['success']} successful, {$result['failed']} failed, {$result['total']} total");

        return self::SUCCESS;
    }
}
