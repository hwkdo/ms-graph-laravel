<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Support;

final class OnedriveFilename
{
    /**
     * Behält den Originalnamen (inkl. Punkt und Groß-/Kleinschreibung),
     * entfernt aber Pfadanteile und für OneDrive ungültige Zeichen.
     */
    public static function sanitize(string $filename): string
    {
        $filename = str_replace('\\', '/', $filename);
        $filename = basename($filename);
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? $filename;
        $filename = str_replace(['"', '*', ':', '<', '>', '?', '|', '#', '%'], '', $filename);
        $filename = trim($filename);
        $filename = rtrim($filename, '.');

        if ($filename === '') {
            return 'upload';
        }

        return $filename;
    }
}
