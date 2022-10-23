<?php

namespace Srustamov\Azericard\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Srustamov\Azericard\AzericardServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AzericardServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}