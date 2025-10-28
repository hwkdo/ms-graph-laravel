<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphLicenseServiceInterface
{
    public function getLicenseDetails(string $upn);
}
