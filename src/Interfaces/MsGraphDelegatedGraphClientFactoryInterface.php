<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Illuminate\Contracts\Auth\Authenticatable;
use Microsoft\Graph\GraphServiceClient;

interface MsGraphDelegatedGraphClientFactoryInterface
{
    public function forUser(Authenticatable $user): GraphServiceClient;
}
