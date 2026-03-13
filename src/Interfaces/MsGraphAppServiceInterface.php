<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphAppServiceInterface
{
    /**
     * @return array<int, array{id: string, appId: string, displayName: string, secrets: array<int, array{keyId: string, displayName: string, startDateTime: \Carbon\Carbon|null, endDateTime: \Carbon\Carbon|null, daysUntilExpiry: int|null, status: string, credentialType: string}>}>
     */
    public function getApplicationsWithSecrets(): array;
}
