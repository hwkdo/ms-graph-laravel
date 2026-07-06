<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Http\Controllers;

use App\Http\Controllers\Controller;
use Hwkdo\MsGraphLaravel\Services\TeamsWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class TeamsWebhookController extends Controller
{
    public function __invoke(Request $request, TeamsWebhookHandler $handler): Response
    {
        if (! config('ms-graph-laravel.teams_bot.enabled')) {
            return response('', 404);
        }

        try {
            $this->verifySignature($request);
        } catch (AccessDeniedHttpException $exception) {
            Log::warning('Teams Webhook Signatur ungültig');

            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();
        $event = $request->header('X-Teams-Event');

        if (! is_string($event) || $event === '') {
            $event = is_string($payload['event'] ?? null) ? $payload['event'] : '';
        }

        if ($event === '') {
            return response('Bad Request', 400);
        }

        try {
            $handler->handle($event, $payload);
        } catch (Throwable $exception) {
            Log::error('Teams Webhook Verarbeitung fehlgeschlagen', [
                'event' => $event,
                'message' => $exception->getMessage(),
            ]);

            return response('', 500);
        }

        if (config('ms-graph-laravel.teams_sdk_rest.log_webhook_requests', true)) {
            Log::info('Teams Webhook verarbeitet', [
                'event' => $event,
                'conversation_id' => $payload['conversationRef']['conversationId'] ?? null,
                'user_aad_id' => $payload['conversationRef']['userAadId'] ?? null,
            ]);
        }

        return response()->noContent();
    }

    private function verifySignature(Request $request): void
    {
        $secret = config('ms-graph-laravel.teams_sdk_rest.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return;
        }

        $signature = $request->header('X-Teams-Signature');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! is_string($signature) || ! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature');
        }
    }
}
