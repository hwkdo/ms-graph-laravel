<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Exception;
use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphIntuneServiceInterface;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\DeviceManagement\ManagedDevices\ManagedDevicesRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\DeviceManagement\ManagedDevices\ManagedDevicesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Models\ManagedDevice;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\GraphServiceClient;

class IntuneService implements MsGraphIntuneServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $client = new Client;
        self::$graph = $client();
    }

    /**
     * @return array<int, array{id: string, deviceName: string|null, userDisplayName: string|null, serialNumber: string|null, imei: string|null, operatingSystem: string|null, osVersion: string|null, model: string|null, phoneNumber: string|null, wiFiMacAddress: string|null, complianceState: string|null}>
     */
    public function listManagedDevices(): array
    {
        try {
            $config = new ManagedDevicesRequestBuilderGetRequestConfiguration;
            $config->queryParameters = new ManagedDevicesRequestBuilderGetQueryParameters;
            $config->queryParameters->select = ['id', 'deviceName', 'userDisplayName', 'serialNumber', 'imei', 'operatingSystem', 'osVersion', 'model', 'phoneNumber', 'wiFiMacAddress', 'complianceState'];
            $config->queryParameters->top = 999;

            $response = self::$graph->deviceManagement()->managedDevices()->get($config)->wait();
            $devices = $response->getValue() ?? [];

            return array_values(array_map([$this, 'mapManagedDevice'], $devices));
        } catch (Exception $e) {
            $this->logException('listManagedDevices', $e);

            return [];
        }
    }

    /**
     * @return array{id: string, deviceName: string|null, userDisplayName: string|null, serialNumber: string|null, imei: string|null, operatingSystem: string|null, osVersion: string|null, model: string|null, phoneNumber: string|null, wiFiMacAddress: string|null, complianceState: string|null}|null
     */
    public function findManagedDeviceBySerialNumber(string $serialNumber): ?array
    {
        try {
            $filter = "serialNumber eq '".str_replace("'", "''", $serialNumber)."'";

            $config = new ManagedDevicesRequestBuilderGetRequestConfiguration;
            $config->queryParameters = new ManagedDevicesRequestBuilderGetQueryParameters;
            $config->queryParameters->filter = $filter;
            $config->queryParameters->select = ['id', 'deviceName', 'userDisplayName', 'serialNumber', 'imei', 'operatingSystem', 'osVersion', 'model', 'phoneNumber', 'wiFiMacAddress', 'complianceState'];
            $config->queryParameters->top = 1;

            $response = self::$graph->deviceManagement()->managedDevices()->get($config)->wait();
            $devices = $response->getValue() ?? [];
            $device = $devices[0] ?? null;

            return $device !== null ? $this->mapManagedDevice($device) : null;
        } catch (Exception $e) {
            $this->logException('findManagedDeviceBySerialNumber', $e);

            return null;
        }
    }

    /**
     * @return array{id: string, deviceName: string|null, userDisplayName: string|null, serialNumber: string|null, imei: string|null, operatingSystem: string|null, osVersion: string|null, model: string|null, phoneNumber: string|null, wiFiMacAddress: string|null, complianceState: string|null}
     */
    private function mapManagedDevice(ManagedDevice $device): array
    {
        $complianceState = $device->getComplianceState();
        $complianceStateValue = $complianceState !== null
            ? (method_exists($complianceState, 'value') ? $complianceState->value() : (string) $complianceState)
            : null;

        return [
            'id' => $device->getId() ?? '',
            'deviceName' => $device->getDeviceName(),
            'userDisplayName' => $device->getUserDisplayName(),
            'serialNumber' => $device->getSerialNumber(),
            'imei' => $device->getImei(),
            'operatingSystem' => $device->getOperatingSystem(),
            'osVersion' => $device->getOsVersion(),
            'model' => $device->getModel(),
            'phoneNumber' => $device->getPhoneNumber(),
            'wiFiMacAddress' => $device->getWiFiMacAddress(),
            'complianceState' => $complianceStateValue,
        ];
    }

    private function logException(string $method, Exception $e): void
    {
        $context = [
            'method' => $method,
            'exception' => $e::class,
            'message' => $e->getMessage() ?: null,
        ];

        if ($e instanceof ODataError) {
            $context['graph_error_code'] = $e->getError()?->getCode();
            $context['graph_error_message'] = $e->getPrimaryErrorMessage();
            $details = $e->getError()?->getDetails();
            if ($details !== null && $details !== []) {
                $context['graph_error_details'] = array_map(
                    fn ($d) => ['code' => $d->getCode(), 'message' => $d->getMessage()],
                    $details
                );
            }
        } else {
            $context['file'] = $e->getFile();
            $context['line'] = $e->getLine();
            if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $context['http_status'] = $response->getStatusCode();
                $context['response_body'] = $response->getBody()->getContents();
            }
            if ($e->getPrevious() !== null) {
                $prev = $e->getPrevious();
                $context['previous_exception'] = $prev::class;
                $context['previous_message'] = $prev->getMessage();
            }
        }

        Log::warning('MsGraph IntuneService: '.$method.' failed', $context);
    }
}
