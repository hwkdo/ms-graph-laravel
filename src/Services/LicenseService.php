<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphLicenseServiceInterface;
use Microsoft\Graph\GraphServiceClient;

class LicenseService implements MsGraphLicenseServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    public function getLicenseDetails(string $upn): array
    {
        $response = self::$graph->users()
            ->byUserId($upn)
            ->licenseDetails()
            ->get()
            ->wait();

        return $response->getValue();
    }
}
