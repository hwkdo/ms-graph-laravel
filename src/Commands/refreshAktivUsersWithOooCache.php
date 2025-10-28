<?php

namespace Hwkdo\MsGraphLaravel\Commands;

use Hwkdo\MsGraphLaravel\Services\CacheService;
use Illuminate\Console\Command;

class refreshAktivUsersWithOooCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'msgraph:refreshAktivUsersWithOooCache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Cache';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return $this->cacheService->refreshAktivUsersWithOoo();
    }
}
