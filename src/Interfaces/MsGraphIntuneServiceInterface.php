<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphIntuneServiceInterface
{
    /**
     * Alle verwalteten Geräte (Intune Managed Devices) auflisten.
     *
     * @return array<int, array{id: string, deviceName: string|null, userDisplayName: string|null, serialNumber: string|null, imei: string|null, operatingSystem: string|null, osVersion: string|null, model: string|null, phoneNumber: string|null, wiFiMacAddress: string|null, complianceState: string|null}>
     */
    public function listManagedDevices(): array;

    /**
     * Ein verwaltetes Gerät anhand der Seriennummer finden.
     *
     * @return array{id: string, deviceName: string|null, userDisplayName: string|null, serialNumber: string|null, imei: string|null, operatingSystem: string|null, osVersion: string|null, model: string|null, phoneNumber: string|null, wiFiMacAddress: string|null, complianceState: string|null}|null
     */
    public function findManagedDeviceBySerialNumber(string $serialNumber): ?array;
}
