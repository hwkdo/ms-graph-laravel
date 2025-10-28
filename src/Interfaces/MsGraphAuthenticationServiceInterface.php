<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphAuthenticationServiceInterface
{
    public function getMethods(string $upn);

    public function getMethod(string $upn, string $type, string $methodId);

    public function deleteMethod(string $upn, string $type, string $methodId);
}
