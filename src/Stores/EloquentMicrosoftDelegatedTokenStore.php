<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Stores;

use DateTimeInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MicrosoftDelegatedTokenStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class EloquentMicrosoftDelegatedTokenStore implements MicrosoftDelegatedTokenStore
{
    public function accessToken(Authenticatable $user): ?string
    {
        $value = $this->attribute($user, 'access_token');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function refreshToken(Authenticatable $user): ?string
    {
        $value = $this->attribute($user, 'refresh_token');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function expiresAt(Authenticatable $user): ?DateTimeInterface
    {
        $value = $this->attribute($user, 'expires_at');

        return $value instanceof DateTimeInterface ? $value : null;
    }

    public function scopes(Authenticatable $user): array
    {
        $value = $this->attribute($user, 'scopes');

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(preg_split('/\s+/', $value) ?: []));
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn (mixed $scope): bool => is_string($scope) && $scope !== ''));
    }

    public function persist(
        Authenticatable $user,
        string $accessToken,
        ?string $refreshToken,
        DateTimeInterface $expiresAt,
        array $scopes,
    ): void {
        if (! $user instanceof Model) {
            return;
        }

        $attributes = [
            $this->attributeName('access_token') => $accessToken,
            $this->attributeName('expires_at') => $expiresAt,
            $this->attributeName('scopes') => $scopes,
        ];

        if ($refreshToken !== null && $refreshToken !== '') {
            $attributes[$this->attributeName('refresh_token')] = $refreshToken;
        }

        $user->forceFill($attributes)->save();
    }

    protected function attribute(Authenticatable $user, string $key): mixed
    {
        return $user->{$this->attributeName($key)} ?? null;
    }

    protected function attributeName(string $key): string
    {
        $name = config('ms-graph-laravel.delegated.token_attributes.'.$key);

        return is_string($name) && $name !== '' ? $name : $key;
    }
}
