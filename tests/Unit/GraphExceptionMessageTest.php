<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Microsoft\Graph\Generated\Models\ODataErrors\MainError;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;

it('resolves graph odata error messages with code', function (): void {
    $error = new MainError;
    $error->setCode('Forbidden');
    $error->setMessage('Missing role permissions on the request.');

    $odataError = new ODataError;
    $odataError->setError($error);

    expect(GraphExceptionMessage::resolve($odataError))
        ->toBe('[Forbidden] Missing role permissions on the request.');
});

it('falls back when exception has no message', function (): void {
    expect(GraphExceptionMessage::resolve(new \RuntimeException, 'Fallback'))
        ->toBe('Fallback');
});
