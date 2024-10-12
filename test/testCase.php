<?php

namespace LaraOTel\OpenTelemetryLaravel\Tests;

use LaraOTel\OpenTelemetryLaravel\LaravelOpenTelemetryServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelOpenTelemetryServiceProvider::class,
        ];
    }
}