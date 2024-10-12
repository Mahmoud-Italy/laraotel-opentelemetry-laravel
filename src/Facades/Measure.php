<?php

namespace LaraOTel\OpenTelemetryLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;
use LaraOTel\OpenTelemetryLaravel\Support\SpanBuilder;
use LaraOTel\OpenTelemetryLaravel\Support\StartedSpan;
use LaraOTel\OpenTelemetryLaravel\Tracer;

/**
 * @method static SpanBuilder span(string $name)
 * @method static StartedSpan start(int|string $name, ?callable $callback = null)
 * @method static void end(?string $name = null)
 * @method static SpanInterface activeSpan()
 * @method static ScopeInterface|null activeScope()
 * @method static string traceId()
 * @method static Tracer tracer()
 * @method static TextMapPropagatorInterface propagator()
 * @method static array propagationHeaders(?ContextInterface $context = null)
 * @method static ContextInterface extractContextFromPropagationHeaders(array $headers)
 */
class Measure extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LaraOTel\OpenTelemetryLaravel\Support\Measure::class;
    }
}