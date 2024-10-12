<?php

namespace LaraOTel\OpenTelemetryLaravel\Traits;

use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;

trait InteractWithEventTimestamp
{
    protected function getEventStartTimestampNs(float $timeMs): int
    {
        $nowNs = Clock::getDefault()->now();
        $durationNs = (int) ($timeMs * ClockInterface::NANOS_PER_MILLISECOND);

        return $nowNs - $durationNs;
    }
}
