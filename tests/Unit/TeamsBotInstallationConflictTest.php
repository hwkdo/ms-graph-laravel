<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Services\TeamsBotInstallationService;
use Microsoft\Graph\Generated\Models\ODataErrors\MainError;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;

it('treats graph conflict as already installed', function (): void {
    $error = new MainError;
    $error->setCode('Conflict');
    $error->setMessage("AppEntitlement already exists");

    $odataError = new ODataError;
    $odataError->setError($error);

    $method = new ReflectionMethod(TeamsBotInstallationService::class, 'isAlreadyInstalledError');
    $method->setAccessible(true);

    expect($method->invoke(null, $odataError))->toBeTrue();
});

it('does not treat other graph errors as already installed', function (): void {
    $error = new MainError;
    $error->setCode('Forbidden');
    $error->setMessage('Caller is not authorized.');

    $odataError = new ODataError;
    $odataError->setError($error);

    $method = new ReflectionMethod(TeamsBotInstallationService::class, 'isAlreadyInstalledError');
    $method->setAccessible(true);

    expect($method->invoke(null, $odataError))->toBeFalse();
});
