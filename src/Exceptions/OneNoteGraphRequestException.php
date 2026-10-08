<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Exceptions;

use RuntimeException;
use Throwable;

class OneNoteGraphRequestException extends RuntimeException
{
    public function __construct(
        public readonly string $requestUrl,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
