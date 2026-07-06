<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TeamsSdkRestClient
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function registerConversation(array $payload): void
    {
        $payload = array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $response = $this->request()->post($this->url('/v1/conversations'), $payload);

        if ($response->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($response->json(), $response->status()));
        }

        $json = $response->json();

        if (! is_array($json) || ($json['success'] ?? false) !== true) {
            throw new RuntimeException($this->resolveErrorMessage($json, $response->status()));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{messageId: string, conversationId: string}
     */
    public function sendMessage(array $payload): array
    {
        $response = $this->request()->post($this->url('/v1/messages'), $payload);

        if ($response->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($response->json(), $response->status()));
        }

        return $this->parseMessageResponse($response->json());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{messageId: string, conversationId: string}
     */
    public function reply(array $payload): array
    {
        $response = $this->request()->post($this->url('/v1/messages/reply'), $payload);

        if ($response->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($response->json(), $response->status()));
        }

        return $this->parseMessageResponse($response->json());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConversationForUser(string $azureUserId): ?array
    {
        $response = $this->request()->get($this->url('/v1/conversations/users/'.$azureUserId));

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($response->json(), $response->status()));
        }

        $json = $response->json();

        if (! is_array($json) || ($json['success'] ?? false) !== true) {
            return null;
        }

        $conversation = $json['conversation'] ?? null;

        return is_array($conversation) ? $conversation : null;
    }

    public function isHealthy(): bool
    {
        return $this->getHealthStatus()['healthy'];
    }

    /**
     * @return array{healthy: bool, service: string|null, base_url: string}
     */
    public function getHealthStatus(): array
    {
        $baseUrl = rtrim((string) config('ms-graph-laravel.teams_sdk_rest.base_url', ''), '/');

        if ($baseUrl === '') {
            return [
                'healthy' => false,
                'service' => null,
                'base_url' => '',
            ];
        }

        try {
            $response = Http::timeout(5)->get($baseUrl.'/health');

            return [
                'healthy' => $response->successful()
                    && $response->json('status') === 'ok',
                'service' => is_string($response->json('service')) ? $response->json('service') : null,
                'base_url' => $baseUrl,
            ];
        } catch (ConnectionException) {
            return [
                'healthy' => false,
                'service' => null,
                'base_url' => $baseUrl,
            ];
        }
    }

    private function request(): PendingRequest
    {
        $apiKey = config('ms-graph-laravel.teams_sdk_rest.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('TEAMS_API_KEY ist nicht konfiguriert.');
        }

        return Http::withToken($apiKey)
            ->timeout((int) config('ms-graph-laravel.teams_sdk_rest.timeout', 30))
            ->acceptJson();
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('ms-graph-laravel.teams_sdk_rest.base_url', ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('TEAMS_SDK_REST_URL ist nicht konfiguriert.');
        }

        return $baseUrl.$path;
    }

    /**
     * @param  mixed  $json
     * @return array{messageId: string, conversationId: string}
     */
    private function parseMessageResponse(mixed $json): array
    {
        if (! is_array($json) || ($json['success'] ?? false) !== true) {
            throw new RuntimeException($this->resolveErrorMessage($json, null));
        }

        $conversationId = $json['conversationId'] ?? null;

        if (! is_string($conversationId) || $conversationId === '') {
            throw new RuntimeException('Teams SDK REST hat keine conversationId zurückgegeben.');
        }

        return [
            'messageId' => is_string($json['messageId'] ?? null) ? $json['messageId'] : '',
            'conversationId' => $conversationId,
        ];
    }

    /**
     * @param  mixed  $json
     */
    private function resolveErrorMessage(mixed $json, ?int $status): string
    {
        if (is_array($json) && is_string($json['error'] ?? null) && $json['error'] !== '') {
            return $json['error'];
        }

        return $status !== null
            ? "Teams SDK REST Anfrage fehlgeschlagen (HTTP {$status})."
            : 'Teams SDK REST Anfrage fehlgeschlagen.';
    }
}
