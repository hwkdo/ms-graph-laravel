<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphShareServiceInterface
{
    /**
     * Gibt alle Dateien eines per Share-Link geteilten OneDrive-Ordners zurück.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \Exception
     */
    public function getSharedFolderContents(string $shareUrl): array;

    /**
     * Lädt Dateiinhalt über eine @microsoft.graph.downloadUrl herunter.
     * Diese URL benötigt keine Authentifizierung.
     *
     * @throws \Exception
     */
    public function downloadDriveItemContent(string $downloadUrl): string;
}
