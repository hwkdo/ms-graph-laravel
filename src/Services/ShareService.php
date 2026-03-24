<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Interfaces\MsGraphShareServiceInterface;
use Hwkdo\MsGraphLaravel\Models\Token;
use Illuminate\Support\Facades\Http;

class ShareService implements MsGraphShareServiceInterface
{
    /**
     * Gibt alle Dateien eines per Share-Link geteilten OneDrive-Ordners zurück.
     *
     * Nutzt die Microsoft Graph Shares API:
     * https://learn.microsoft.com/en-us/graph/api/shares-get?view=graph-rest-1.0
     *
     * Der Share-URL wird URL-safe base64-encodiert und mit „u!" prefixiert.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \Exception
     */
    public function getSharedFolderContents(string $shareUrl): array
    {
        $encodedUrl = 'u!'.rtrim(strtr(base64_encode($shareUrl), '+/', '-_'), '=');

        $token = Token::getToken('onedrive');

        $response = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/shares/{$encodedUrl}/driveItem/children");

        if (! $response->successful()) {
            throw new \Exception(
                "Share-Ordnerinhalt konnte nicht abgerufen werden: {$response->status()} – {$response->body()}"
            );
        }

        return $response->json()['value'] ?? [];
    }

    /**
     * Lädt den Dateiinhalt über eine @microsoft.graph.downloadUrl herunter.
     * Diese URL ist in den DriveItem-Metadaten enthalten und benötigt keine Authentifizierung.
     *
     * @throws \Exception
     */
    public function downloadDriveItemContent(string $downloadUrl): string
    {
        $response = Http::withOptions(['allow_redirects' => true])->get($downloadUrl);

        if (! $response->successful()) {
            throw new \Exception(
                "Datei konnte nicht heruntergeladen werden: {$response->status()}"
            );
        }

        return $response->body();
    }
}
