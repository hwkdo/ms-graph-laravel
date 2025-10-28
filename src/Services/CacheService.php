<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    public static function delete($key, $cache = null)
    {
        $cache = $cache ? config('cache.default') : $cache;

        return Cache::store($cache)->delete($key);
    }

    public function refreshAktivUsersWithOoo()
    {
        // This method requires User model from main application
        // It should be called from the main application context
    }
}
