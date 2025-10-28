<?php

namespace Hwkdo\MsGraphLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class GraphWebhookJobMapping extends Model
{
    protected $guarded = [];

    protected $table = 'ms_graph_laravel_webhook_job_mappings';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the job mapping for a specific webhook type.
     */
    public static function getJobClassForType(string $type): ?string
    {
        return static::query()
            ->where('webhook_type', $type)
            ->where('is_active', true)
            ->value('job_class');
    }

    /**
     * Check if a job class exists and is valid.
     */
    public function isValidJobClass(): bool
    {
        return class_exists($this->job_class);
    }

    /**
     * Get all active subscriptions that should be monitored.
     */
    public static function getActiveSubscriptions(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->where('is_active', true)
            ->whereNotNull('resource')
            ->whereNotNull('notification_url')
            ->get();
    }

    /**
     * Get a subscription by resource and notification URL.
     */
    public static function findByResourceAndNotificationUrl(string $resource, string $notificationUrl): ?self
    {
        return static::query()
            ->where('resource', $resource)
            ->where('notification_url', $notificationUrl)
            ->first();
    }

    /**
     * Check if this mapping has subscription data configured.
     */
    public function hasSubscriptionData(): bool
    {
        return ! empty($this->resource)
            && ! empty($this->notification_url)
            && ! empty($this->change_type);
    }

    /**
     * Get the subscription data as an array for the Microsoft Graph API.
     */
    public function getSubscriptionData(): array
    {
        return [
            'name' => $this->name,
            'filepath' => $this->filepath,
            'upn' => $this->upn,
            'resource' => $this->resource,
            'notificationUrl' => $this->notification_url,
            'changeType' => $this->change_type,
        ];
    }

    /**
     * Generate notification URL for a webhook type.
     */
    public static function generateNotificationUrl(string $webhookType): string
    {
        $portalUrl = rtrim(env('PORTAL_URL', 'https://portal.hwkdo.com'), '/');

        return $portalUrl.'/api/kunden/ms-graph-subscription/'.$webhookType;
    }
}

