<?php

namespace LaraOTel\OpenTelemetryLaravel\Tests\Support;

use Mockery;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\Span;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Support\SpanBuilder;
use LaraOTel\OpenTelemetryLaravel\Support\StartedSpan;
use LaraOTel\OpenTelemetryLaravel\Tests\TestCase;

class MeasureTest extends TestCase
{
    public function testNewSpanBuilder()
    {
        $measure = Mockery::mock(\LaraOTel\OpenTelemetryLaravel\Support\Measure::class)->makePartial();
        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);

        $measure->shouldReceive('tracer->spanBuilder')->withAnyArgs()->andReturn($mockBuilder)->once();

        Measure::swap($measure);

        $this->assertInstanceOf(
            SpanBuilder::class,
            Measure::span('test')
        );
    }

    public function testStartSpan()
    {
        $measure = Mockery::mock(\LaraOTel\OpenTelemetryLaravel\Support\Measure::class)->makePartial();
        $mockBuilder = Mockery::mock(SpanBuilder::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $mockSpan = Mockery::mock(SpanInterface::class);

        $measure->shouldReceive('span')->withAnyArgs()->andReturn($mockBuilder)->once();
        $mockBuilder->shouldReceive('start')->andReturn($mockSpan)->once();
        $mockSpan->shouldReceive('activate')->andReturn($mockScope)->once();

        Measure::swap($measure);

        $this->assertInstanceOf(
            StartedSpan::class,
            Measure::start('test')
        );
    }

    public function testEndSpan()
    {
        $measure = Mockery::mock(\LaraOTel\OpenTelemetryLaravel\Support\Measure::class)->makePartial();
        $mockBuilder = Mockery::mock(SpanBuilder::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $mockSpan = Mockery::mock(SpanInterface::class);

        $measure->shouldReceive('span')->andReturn($mockBuilder)->once();
        $mockBuilder->shouldReceive('start')->andReturn($mockSpan)->once();
        $mockSpan->shouldReceive('activate')->andReturn($mockScope)->once();

        $mockSpan->shouldReceive('end')->andReturn($mockScope)->once();
        $mockScope->shouldReceive('detach')->once();

        Measure::swap($measure);

        Measure::start('test');
        Measure::end();
    }

    public function testGetTracer()
    {
        $this->app->instance(TracerInterface::class, Mockery::mock(TracerInterface::class));

        $this->assertInstanceOf(
            TracerInterface::class,
            Measure::tracer()
        );
    }

    public function testGetCurrentSpan()
    {
        $this->assertSame(Span::getCurrent(), Measure::activeSpan());
    }

    public function testGetActiveScope()
    {
        $this->assertSame(Context::storage()->scope(), Measure::activeScope());
    }

    public function testGetTraceId()
    {
        $traceId = (new RandomIdGenerator())->generateTraceId();
        $measure = Mockery::mock(\LaraOTel\OpenTelemetryLaravel\Support\Measure::class)->makePartial();
        $measure->shouldReceive('activeSpan->getContext->getTraceId')->andReturn($traceId);

        Measure::swap($measure);

        $this->assertSame($traceId, Measure::traceId());
    }

    public function testGetPropagator()
    {
        $this->assertSame(Globals::propagator(), Measure::propagator());
    }

    public function testPropagationHeaders()
    {
        $context = Context::getCurrent();
        $measure = Mockery::mock(\LaraOTel\OpenTelemetryLaravel\Support\Measure::class)->makePartial();
        $measure->shouldReceive('propagator->inject')->once();

        Measure::swap($measure);

        $this->assertSame(
            [],
            Measure::propagationHeaders($context)
        );
    }

    public function testExtractContextFromPropagationHeaders()
    {
        $headers = [
            'traceparent' => '00-1234567890abcdef1234567890abcdef-1234567890abcdef-01',
            'tracestate' => 'key1=value1,key2=value2',
        ];
        $context = Context::getCurrent();
        $measure = Mockery::mock(\LaraOTel\OpenTelemetryLaravel\Support\Measure::class)->makePartial();
        $measure->shouldReceive('propagator->extract')->with($headers)->andReturn($context)->once();

        Measure::swap($measure);

        $this->assertSame(
            $context,
            Measure::extractContextFromPropagationHeaders($headers)
        );
    }
}