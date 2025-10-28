<?php

namespace Hwkdo\MsGraphLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\MsGraphLaravel\MsGraphLaravel
 */
class MsGraphLaravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\MsGraphLaravel\MsGraphLaravel::class;
    }
}
