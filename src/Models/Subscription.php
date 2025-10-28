<?php

namespace Hwkdo\MsGraphLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = [];
    protected $table = 'ms_graph_subscriptions';
    protected $casts = [
        'expiration' => 'datetime',
    ];

    public function getConfigAttribute(): array|null
    {
        $collection = collect(config('ms-graph-laravel.subscriptions'));
        foreach ($collection as $name => $data) {
            if ($data['resource'] == $this->resource && $data['notificationUrl'] == $this->notificationUrl) {
                return $data;
            }
        }

        return null;
    }
}
