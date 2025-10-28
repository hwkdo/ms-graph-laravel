<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Microsoft\Graph\Generated\Models\DateTimeTimeZone;

interface MsGraphDateTimeServiceInterface
{
    public function toCarbon(DateTimeTimeZone $dateTimeTimeZone);
}
