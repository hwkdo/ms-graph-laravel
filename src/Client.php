<?php

namespace Hwkdo\MsGraphLaravel;

use Hwkdo\MsGraphLaravel\Models\Token;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

class Client
{
    public function __invoke($type = 'default'): GraphServiceClient
    {
        $token = Token::getToken($type);

        $tokenRequestContext = new ClientCredentialContext(
            config('ms-graph-laravel.tenant_id'),
            config('ms-graph-laravel.azure_app_registrations.'.$type.'.client_id'),
            config('ms-graph-laravel.azure_app_registrations.'.$type.'.client_secret')
        );

        $graphServiceClient = new GraphServiceClient($tokenRequestContext);

        return $graphServiceClient;
    }
}
