<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Authentication\DelegatedBearerTokenAuthenticationProvider;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphDelegatedGraphClientFactoryInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Microsoft\Graph\GraphRequestAdapter;
use Microsoft\Graph\GraphServiceClient;

class DelegatedGraphClientFactory implements MsGraphDelegatedGraphClientFactoryInterface
{
    public function __construct(
        protected DelegatedAccessTokenService $tokens,
    ) {}

    public function forUser(Authenticatable $user): GraphServiceClient
    {
        $authenticationProvider = new DelegatedBearerTokenAuthenticationProvider($this->tokens, $user);
        $requestAdapter = new GraphRequestAdapter($authenticationProvider);

        return GraphServiceClient::createWithRequestAdapter($requestAdapter);
    }
}
