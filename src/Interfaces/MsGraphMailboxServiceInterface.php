<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Illuminate\Support\Carbon;

interface MsGraphMailboxServiceInterface
{
    public function getAutoReplySettings($username);

    public function getSettings($username);

    public function setOutOfOffice($upn, $message, ?Carbon $von = null, ?Carbon $bis = null);

    public function removeOutOfOffice($username);
}
