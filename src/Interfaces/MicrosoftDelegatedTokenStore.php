<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;

interface MicrosoftDelegatedTokenStore
{
    public function accessToken(Authenticatable $user): ?string;

    public function refreshToken(Authenticatable $user): ?string;

    public function expiresAt(Authenticatable $user): ?DateTimeInterface;

    /**
     * @return list<string>
     */
    public function scopes(Authenticatable $user): array;

    /**
     * @param  list<string>  $scopes
     */
    public function persist(
        Authenticatable $user,
        string $accessToken,
        ?string $refreshToken,
        DateTimeInterface $expiresAt,
        array $scopes,
    ): void;
}
