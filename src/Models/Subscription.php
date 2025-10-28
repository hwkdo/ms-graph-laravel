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

    public function getConfigAttribute(): ?array
    {
        $mapping = GraphWebhookJobMapping::findByResourceAndNotificationUrl(
            $this->resource,
            $this->notificationUrl
        );

        if ($mapping) {
            return $mapping->getSubscriptionData();
        }

        return null;
    }

    /**
     * Get the webhook job mapping for this subscription.
     */
    public function webhookMapping(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GraphWebhookJobMapping::class, 'resource', 'resource')
            ->where('notification_url', $this->notificationUrl);
    }
}
