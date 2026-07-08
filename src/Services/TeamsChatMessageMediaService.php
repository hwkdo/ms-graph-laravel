<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Microsoft\Graph\GraphServiceClient;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

class TeamsChatMessageMediaService
{
    protected GraphServiceClient $graph;

    public function __construct(?GraphServiceClient $graph = null)
    {
        if ($graph instanceof GraphServiceClient) {
            $this->graph = $graph;

            return;
        }

        $registration = (string) config('ms-graph-laravel.teams_bot.graph_registration', 'teams_bot');
        $client = new Client;
        $this->graph = $client($registration);
    }

    /**
     * Lädt eingebettete Medien (z. B. Bilder) aus einer Teams-Chatnachricht.
     *
     * @return list<array{filename: string, mimeType: string, contents: string}>
     */
    public function fetchHostedContents(string $chatId, string $messageId): array
    {
        try {
            $response = $this->graph
                ->chats()
                ->byChatId($chatId)
                ->messages()
                ->byChatMessageId($messageId)
                ->hostedContents()
                ->get()
                ->wait();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                GraphExceptionMessage::resolve($exception, 'Teams-Nachrichtenmedien konnten nicht geladen werden.'),
                0,
                $exception,
            );
        }

        $files = [];
        $index = 0;

        foreach ($response?->getValue() ?? [] as $hostedContent) {
            $hostedContentId = $hostedContent->getId();

            if (! is_string($hostedContentId) || $hostedContentId === '') {
                continue;
            }

            $contents = $this->downloadHostedContent($chatId, $messageId, $hostedContentId);

            if ($contents === '') {
                continue;
            }

            $mimeType = $this->resolveMimeType($hostedContent->getContentType(), $contents);
            $index++;
            $files[] = [
                'filename' => $this->buildFilename($index, $mimeType),
                'mimeType' => $mimeType,
                'contents' => $contents,
            ];
        }

        return $files;
    }

    private function downloadHostedContent(string $chatId, string $messageId, string $hostedContentId): string
    {
        try {
            $stream = $this->graph
                ->chats()
                ->byChatId($chatId)
                ->messages()
                ->byChatMessageId($messageId)
                ->hostedContents()
                ->byChatMessageHostedContentId($hostedContentId)
                ->content()
                ->get()
                ->wait();
        } catch (Throwable) {
            return '';
        }

        if (! $stream instanceof StreamInterface) {
            return '';
        }

        return (string) $stream->getContents();
    }

    private function resolveMimeType(?string $declared, string $contents): string
    {
        if (is_string($declared) && trim($declared) !== '' && str_contains($declared, '/')) {
            return trim($declared);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $detected = finfo_buffer($finfo, $contents);
        finfo_close($finfo);

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }

    private function buildFilename(int $index, string $mimeType): string
    {
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'bin',
        };

        return sprintf('teams-bild-%d.%s', $index, $extension);
    }
}
