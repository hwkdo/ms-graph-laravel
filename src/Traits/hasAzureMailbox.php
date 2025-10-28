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
        return Cache::remember('getHasMailbox-'.$this->id, config('ms-graph-laravel.cache_seconds', 300), function () {
            $graph_mailbox_service = new MailboxService;
            try {
                $graph_mailbox_service->getSettings($this->azure_upn);

                return true;
            } catch (ClientException $e) {
                return false;
            }
        });

    }

    public function getOutOfOffice()
    {
        $data = Cache::remember('getOutOfOffice-'.$this->id, config('ms-graph-laravel.cache_seconds', 300), function () {
            $mbs = new MailboxService;

            try {
                $result = $mbs->getAutoReplySettings($this->azure_upn);
            } catch (Exception $e) {
                Log::error('getOutOfOffice: '.$e->getMessage());
                $result = false;
            }

            if ($result) {
                $start_dt = $result->getScheduledStartDateTime()->getDateTime();
                $start_tz = $result->getScheduledStartDateTime()->getTimezone();
                $start = new \Carbon\Carbon($start_dt, $start_tz);
                $start->setTimezone(config('app.timezone'));
                $end_dt = $result->getScheduledEndDateTime()->getDateTime();
                $end_tz = $result->getScheduledEndDateTime()->getTimezone();
                $end = new \Carbon\Carbon($end_dt, $end_tz);
                $end->setTimezone(config('app.timezone'));

                return [
                    'result' => $result,
                    'start' => $start,
                    'end' => $end,
                ];
            }

            return [
                'result' => null,
                'start' => null,
                'end' => null,
            ];
        });

        if ($data['result']) {
            $isOutOfOffice = match ($data['result']->getStatus()->value()) {
                'alwaysEnabled' => true,
                'scheduled' => now()->between($data['start'], $data['end']),
                default => false
            };

            return [
                'isOutOfOffice' => $isOutOfOffice,
                'status' => $data['result']->getStatus()->value(),
                'start_d' => $data['start']->format('d.m.Y'),
                'start_dt' => $data['start']->format('d.m.Y H:i').' Uhr',
                'end_d' => $data['end']->format('d.m.Y'),
                'end_dt' => $data['end']->format('d.m.Y H:i').' Uhr',
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

        return $us->getUserPresence($this->azure_upn);
    }
}
