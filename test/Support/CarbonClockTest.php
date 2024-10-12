<?php

namespace LaraOTel\OpenTelemetryLaravel\Tests\Support;

use Carbon\Carbon;
use LaraOTel\OpenTelemetryLaravel\Support\CarbonClock;
use LaraOTel\OpenTelemetryLaravel\Tests\TestCase;
use Spatie\TestTime\TestTime;

class CarbonClockTest extends TestCase
{
    public function testGetNow()
    {
        $carbon = TestTime::freeze('Y-m-d H:i:s', '2024-01-01 00:00:00');
        $nano = Carbon::parse('2024-01-01 00:00:00')->getTimestampMs() * 1000000;

        $this->assertSame($nano, (new CarbonClock())->now());
    }

    public function testTransformCarbonInstanceToNanos()
    {
        $carbon = TestTime::freeze('Y-m-d H:i:s', '2024-01-01 00:00:00');
        $nano = Carbon::parse('2024-01-01 00:00:00')->getTimestampMs() * 1000000;

        $this->assertSame($nano, CarbonClock::carbonToNanos($carbon));
    }
}