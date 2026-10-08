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
    /**
     * @param  list<string>|null  $requiredScopes
     */
    public function __construct(
        private DelegatedAccessTokenService $tokens,
        private Authenticatable $user,
        private ?array $requiredScopes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $additionalAuthenticationContext
     */
    public function authenticateRequest(RequestInformation $request, array $additionalAuthenticationContext = []): Promise
    {
        $token = $this->requiredScopes === null
            ? $this->tokens->accessToken($this->user)
            : $this->tokens->accessTokenForScopes($this->user, $this->requiredScopes);
        $request->addHeaders(['Authorization' => 'Bearer '.$token]);

        return new FulfilledPromise($request);
    }
}
