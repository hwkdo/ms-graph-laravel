<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Authentication;

use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Hwkdo\MsGraphLaravel\Services\DelegatedAccessTokenService;
use Illuminate\Contracts\Auth\Authenticatable;
use Microsoft\Kiota\Abstractions\Authentication\AuthenticationProvider;
use Microsoft\Kiota\Abstractions\RequestInformation;

class DelegatedBearerTokenAuthenticationProvider implements AuthenticationProvider
{
    public function __construct(
        private DelegatedAccessTokenService $tokens,
        private Authenticatable $user,
    ) {}

    /**
     * @param  array<string, mixed>  $additionalAuthenticationContext
     */
    public function authenticateRequest(RequestInformation $request, array $additionalAuthenticationContext = []): Promise
    {
        $token = $this->tokens->accessToken($this->user);
        $request->addHeaders(['Authorization' => 'Bearer '.$token]);

        return new FulfilledPromise($request);
    }
}
