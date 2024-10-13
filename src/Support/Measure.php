<?php

namespace LaraOTel\OpenTelemetryLaravel\Support;

use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;

class Measure
{
    /**
     * @var array<string, StartedSpan>
     */
    protected array $startedSpans = [];

    public function __construct(protected Application $app)
    {
        $app->terminating($this->flush(...));
    }

    public function span(string $name): SpanBuilder
    {
        return new SpanBuilder($this->tracer()->spanBuilder($name));
    }

    public function start(string|int $name, ?\Closure $callback = null): StartedSpan
    {
        $name = (string) $name;
        $spanBuilder = $this->span($name);

        if ($callback) {
            $callback($spanBuilder);
        }

        $span = $spanBuilder->start();
        $scope = $span->activate();

        $this->startedSpans[$name] = new StartedSpan($span, $scope);

        return $this->startedSpans[$name];
    }

    public function end(string|int|null $name = null): void
    {
        $name ??= array_key_last($this->startedSpans);

        $name = (string) $name;

        if (isset($this->startedSpans[$name])) {
            $startedSpan = $this->startedSpans[$name];

            $startedSpan->span->end();
            $startedSpan->scope->detach();

            unset($this->startedSpans[$name]);
        }
    }

    public function tracer()
    {
        return $this->app->get(TracerInterface::class);
    }

    public function activeSpan(): SpanInterface
    {
        return Span::getCurrent();
    }

    public function activeScope(): ?ScopeInterface
    {
        return Context::storage()->scope();
    }

    public function traceId(): ?string
    {
        $traceId = $this->activeSpan()->getContext()->getTraceId();

        return SpanContextValidator::isValidTraceId($traceId) ? $traceId : null;
    }

    public function propagator()
    {
        return $this->app->get(TextMapPropagatorInterface::class) ?? TraceContextPropagator::getInstance();
    }

    public function propagationHeaders(?ContextInterface $context = null): array
    {
        $headers = [];

        $this->propagator()->inject($headers, context: $context);

        return $headers;
    }

    public function extractContextFromPropagationHeaders(array $headers): ContextInterface
    {
        return $this->propagator()->extract($headers);
    }

    public function flush(): void
    {
        foreach ($this->startedSpans as $name => $span) {
            $this->end($name);
        }
    }
}