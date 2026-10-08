<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface OnenoteDelegatedTokenInterface
{
    public function connectedUpn(): ?string;

    public function accessToken(): string;
}
