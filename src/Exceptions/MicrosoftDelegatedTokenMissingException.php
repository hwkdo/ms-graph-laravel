<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class MicrosoftDelegatedTokenMissingException extends Exception implements ShouldntReport
{
    public static function missingRefreshToken(): self
    {
        return new self('Kein Microsoft-Refresh-Token vorhanden. Bitte mit Microsoft anmelden.');
    }

    public static function missingRequiredScopes(): self
    {
        return new self('Dem Microsoft-Token fehlen OneDrive-Berechtigungen. Bitte erneut mit Microsoft anmelden.');
    }

    public static function refreshFailed(): self
    {
        return new self('Das Microsoft-Token konnte nicht erneuert werden. Bitte erneut mit Microsoft anmelden.');
    }
}
