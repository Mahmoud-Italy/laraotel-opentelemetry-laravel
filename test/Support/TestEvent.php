<?php

namespace LaraOTel\OpenTelemetryLaravel\Tests\Support;

class TestEvent
{
    public function __construct(public string $value) {}
}