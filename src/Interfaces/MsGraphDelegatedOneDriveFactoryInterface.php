<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Illuminate\Contracts\Auth\Authenticatable;

interface MsGraphDelegatedOneDriveFactoryInterface
{
    public function forUser(Authenticatable $user): MsGraphOneDriveServiceInterface;
}
