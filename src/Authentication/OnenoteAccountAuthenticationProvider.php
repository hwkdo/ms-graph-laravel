<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Authentication;

use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Hwkdo\MsGraphLaravel\Interfaces\OnenoteDelegatedTokenInterface;
use Microsoft\Kiota\Abstractions\Authentication\AuthenticationProvider;
use Microsoft\Kiota\Abstractions\RequestInformation;

class OnenoteAccountAuthenticationProvider implements AuthenticationProvider
{
    public function __construct(
        private OnenoteDelegatedTokenInterface $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $additionalAuthenticationContext
     */
    public function authenticateRequest(RequestInformation $request, array $additionalAuthenticationContext = []): Promise
    {
        $request->addHeaders(['Authorization' => 'Bearer '.$this->tokens->accessToken()]);

        return new FulfilledPromise($request);
    }
}
