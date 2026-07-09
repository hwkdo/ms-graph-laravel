<?php

use Hwkdo\MsGraphLaravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/Feature');
uses(TestCase::class)->in(__DIR__.'/Unit');
