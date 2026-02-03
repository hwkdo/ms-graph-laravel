<?php

namespace Hwkdo\MsGraphLaravel\Traits;

use Exception;
use GuzzleHttp\Exception\ClientException;
use Hwkdo\MsGraphLaravel\Services\MailboxService;
use Hwkdo\MsGraphLaravel\Services\UserService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait hasAzureMailbox
{
    public function getHasMailbox()
    {
        $upn = $this->upn;
        return Cache::remember('getHasMailbox-'.$this->id, config('ms-graph-laravel.cache_seconds', 300), function () use ($upn) {
            $graph_mailbox_service = new MailboxService;
            try {
                $graph_mailbox_service->getSettings($upn);

                return true;
            } catch (ClientException $e) {
                return false;
            }
        });

    }

    public function getOutOfOffice()
    {
        $upn = $this->upn;
        $data = Cache::remember('getOutOfOffice-'.$this->id, config('ms-graph-laravel.cache_seconds', 300), function () use ($upn) {
            $mbs = new MailboxService;

            try {
                $result = $mbs->getAutoReplySettings($upn);
            } catch (Exception $e) {
                Log::error('getOutOfOffice: '.$e->getMessage());
                $result = false;
            }

            if ($result) {
                $status = $result->getStatus()->value();
                $start_dt = $result->getScheduledStartDateTime()->getDateTime();
                $start_tz = $result->getScheduledStartDateTime()->getTimezone();
                $start = new \Carbon\Carbon($start_dt, $start_tz);
                $start->setTimezone(config('app.timezone'));
                $end_dt = $result->getScheduledEndDateTime()->getDateTime();
                $end_tz = $result->getScheduledEndDateTime()->getTimezone();
                $end = new \Carbon\Carbon($end_dt, $end_tz);
                $end->setTimezone(config('app.timezone'));

                return [
                    'status' => $status,
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                ];
            }

            return [
                'status' => null,
                'start' => null,
                'end' => null,
            ];
        });

        if ($data['status']) {
            $start = $data['start'] ? \Carbon\Carbon::parse($data['start']) : null;
            $end = $data['end'] ? \Carbon\Carbon::parse($data['end']) : null;

            $isOutOfOffice = match ($data['status']) {
                'alwaysEnabled' => true,
                'scheduled' => $start && $end ? now()->between($start, $end) : false,
                default => false
            };

            return [
                'isOutOfOffice' => $isOutOfOffice,
                'status' => $data['status'],
                'start_d' => $start ? $start->format('d.m.Y') : null,
                'start_dt' => $start ? $start->format('d.m.Y H:i').' Uhr' : null,
                'end_d' => $end ? $end->format('d.m.Y') : null,
                'end_dt' => $end ? $end->format('d.m.Y H:i').' Uhr' : null,
            ];
        } else {
            return [
                'isOutOfOffice' => null,
                'status' => null,
                'start_d' => null,
                'start_dt' => null,
                'end_d' => null,
                'end_dt' => null,
            ];
        }

    }

    public function getAzurePresence()
    {
        $us = new UserService;

        return $us->getUserPresence($this->upn);
    }

    /**
     * Get cached out-of-office status from database.
     * This method uses the synced status from the database instead of making a live API call.
     */
    public function getCachedOutOfOffice()
    {
        $status = $this->outOfOfficeStatus;

        if (! $status) {
            return [
                'isOutOfOffice' => null,
                'status' => null,
                'start_d' => null,
                'start_dt' => null,
                'end_d' => null,
                'end_dt' => null,
            ];
        }

        return $status->getFormattedStatus();
    }
}
