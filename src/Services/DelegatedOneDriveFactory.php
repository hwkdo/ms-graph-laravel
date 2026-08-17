<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Interfaces\MsGraphDelegatedGraphClientFactoryInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphDelegatedOneDriveFactoryInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneDriveServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class DelegatedOneDriveFactory implements MsGraphDelegatedOneDriveFactoryInterface
{
    public function __construct(
        protected MsGraphDelegatedGraphClientFactoryInterface $graphClientFactory,
        protected DelegatedAccessTokenService $tokens,
    ) {}

    public function forUser(Authenticatable $user): MsGraphOneDriveServiceInterface
    {
        $this->tokens->assertHasOnedriveScopes($user);

        return new OneDriveService($this->graphClientFactory->forUser($user));
    }
}
