<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Events;

use Hwkdo\MsGraphLaravel\Data\TeamsBotIncomingMessage;
use Illuminate\Foundation\Events\Dispatchable;

class TeamsBotMessageReceived
{
    use Dispatchable;

    public function __construct(
        public readonly TeamsBotIncomingMessage $message,
    ) {}
}
