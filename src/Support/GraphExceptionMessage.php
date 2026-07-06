<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Support;

use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Throwable;

class GraphExceptionMessage
{
    public static function resolve(Throwable $exception, string $fallback = 'Unbekannter Graph-Fehler.'): string
    {
        if ($exception instanceof ODataError) {
            $code = $exception->getError()?->getCode();
            $message = $exception->getPrimaryErrorMessage();

            if (filled($message)) {
                return filled($code) ? "[{$code}] {$message}" : $message;
            }
        }

        $message = $exception->getMessage();
        if (filled($message)) {
            return $message;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            $previousMessage = self::resolve($previous, '');
            if (filled($previousMessage)) {
                return $previousMessage;
            }
        }

        return $fallback;
    }
}
