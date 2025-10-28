<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Carbon\Carbon;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphDateTimeServiceInterface;
use Microsoft\Graph\Generated\Models\DateTimeTimeZone;

class DateTimeService implements MsGraphDateTimeServiceInterface
{
    public function toCarbon(DateTimeTimeZone $dateTimeTimeZone): Carbon
    {
        return Carbon::parse(
            $dateTimeTimeZone->getDateTime(),
            $dateTimeTimeZone->getTimeZone()
        );
    }
}
