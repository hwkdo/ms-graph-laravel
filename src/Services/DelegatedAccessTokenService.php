<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Exceptions\MicrosoftDelegatedTokenMissingException;
use Hwkdo\MsGraphLaravel\Interfaces\MicrosoftDelegatedTokenStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class DelegatedAccessTokenService
{
    public function __construct(
        protected MicrosoftDelegatedTokenStore $tokenStore,
    ) {}

    public function accessToken(Authenticatable $user): string
    {
        return $this->accessTokenForScopes($user, $this->requiredOnedriveScopes());
    }

    /**
     * @param  list<string>  $requiredScopes
     */
    public function accessTokenForScopes(Authenticatable $user, array $requiredScopes): string
    {
        $accessToken = $this->tokenStore->accessToken($user);
        $expiresAt = $this->tokenStore->expiresAt($user);
        $leeway = (int) config('ms-graph-laravel.delegated.refresh_leeway_seconds', 120);

        if (
            is_string($accessToken)
            && $accessToken !== ''
            && $expiresAt !== null
            && Carbon::parse($expiresAt)->isAfter(now()->addSeconds($leeway))
        ) {
            if ($this->hasAnyScope($user, $requiredScopes)) {
                return $accessToken;
            }

            throw MicrosoftDelegatedTokenMissingException::missingRequiredScopes();
        }

        $token = $this->refresh($user);
        $this->assertHasScopes($user, $requiredScopes);

        return $token;
    }

    /**
     * @return list<string>
     */
    private function requiredOnedriveScopes(): array
    {
        $required = config('ms-graph-laravel.delegated.required_onedrive_scopes', ['Files.ReadWrite']);

        if (! is_array($required) || $required === []) {
            return ['Files.ReadWrite'];
        }

        return array_values(array_filter($required, fn (mixed $scope): bool => is_string($scope) && $scope !== ''));
    }

    public function assertHasOnedriveScopes(Authenticatable $user): void
    {
        $this->assertHasScopes($user, $this->requiredOnedriveScopes());
    }

    /**
     * @param  list<string>  $requiredScopes
     */
    public function assertHasScopes(Authenticatable $user, array $requiredScopes): void
    {
        if ($this->hasAnyScope($user, $requiredScopes)) {
            return;
        }

        throw MicrosoftDelegatedTokenMissingException::missingRequiredScopes();
    }

    /**
     * @param  list<string>  $requiredScopes
     */
    private function hasAnyScope(Authenticatable $user, array $requiredScopes): bool
    {
        $scopes = $this->normalizedScopes($this->tokenStore->scopes($user));

        foreach ($requiredScopes as $scope) {
            if (! is_string($scope) || $scope === '') {
                continue;
            }

            if (in_array($this->normalizeScope($scope), $scopes, true)) {
                return true;
            }
        }

        return false;
    }

    protected function refresh(Authenticatable $user): string
    {
        $refreshToken = $this->tokenStore->refreshToken($user);

        if ($refreshToken === null) {
            throw MicrosoftDelegatedTokenMissingException::missingRefreshToken();
        }

        $tenant = (string) config('services.microsoft.tenant', 'common');
        $scopes = config('services.microsoft.scopes', []);
        $scopeString = is_array($scopes) ? implode(' ', $scopes) : (string) $scopes;

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->connectTimeout(3)
                ->retry([100, 500, 1000])
                ->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token', [
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'scope' => $scopeString,
                ])
                ->throw();
        } catch (RequestException|Throwable) {
            throw MicrosoftDelegatedTokenMissingException::refreshFailed();
        }

        /** @var array{access_token?: mixed, refresh_token?: mixed, expires_in?: mixed, scope?: mixed} $payload */
        $payload = $response->json() ?? [];
        $accessToken = $payload['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw MicrosoftDelegatedTokenMissingException::refreshFailed();
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 3600);
        $newRefreshToken = $payload['refresh_token'] ?? null;
        $persistedRefresh = is_string($newRefreshToken) && $newRefreshToken !== ''
            ? $newRefreshToken
            : $refreshToken;

        $grantedScopes = $payload['scope'] ?? $this->tokenStore->scopes($user);
        if (is_string($grantedScopes)) {
            $grantedScopes = preg_split('/\s+/', trim($grantedScopes)) ?: [];
        }

        if (! is_array($grantedScopes)) {
            $grantedScopes = [];
        }

        $this->tokenStore->persist(
            $user,
            $accessToken,
            $persistedRefresh,
            now()->addSeconds(max($expiresIn, 0)),
            array_values(array_filter($grantedScopes, fn (mixed $scope): bool => is_string($scope) && $scope !== '')),
        );

        return $accessToken;
    }

    /**
     * @param  list<string>  $scopes
     * @return list<string>
     */
    protected function normalizedScopes(array $scopes): array
    {
        return array_values(array_map(fn (string $scope): string => $this->normalizeScope($scope), $scopes));
    }

    protected function normalizeScope(string $scope): string
    {
        $scope = trim($scope);

        if (str_starts_with($scope, 'https://graph.microsoft.com/')) {
            return substr($scope, strlen('https://graph.microsoft.com/'));
        }

        return $scope;
    }
}
