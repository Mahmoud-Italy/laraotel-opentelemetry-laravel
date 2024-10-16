<?php

use Illuminate\Support\Facades\Log;
use LaraOTel\OpenTelemetryLaravel\Facades\Tracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\Span;
use Spatie\TestTime\TestTime;

beforeEach(function () {
    TestTime::freeze('Y-m-d H:i:s', '2022-01-01 00:00:00');
});

it('can resolve laravel tracer', function () {
    /** @var \LaraOTel\OpenTelemetryLaravel\Tracer $tracer */
    $tracer = app(\LaraOTel\OpenTelemetryLaravel\Tracer::class);

    expect($tracer)
        ->toBeInstanceOf(\LaraOTel\OpenTelemetryLaravel\Tracer::class)
        ->traceId()->toBeNull()
        ->activeSpan()->toBeInstanceOf(\OpenTelemetry\API\Trace\NonRecordingSpan::class);
});

it('can measure a span', function () {
    $span = Tracer::newSpan('test span')->start();

    expect(Tracer::activeSpan())->not->toBe($span);

    assert($span instanceof Span);
    expect($span)
        ->getName()->toBe('test span')
        ->isRecording()->toBeTrue()
        ->hasEnded()->toBeFalse()
        ->getKind()->toBe(SpanKind::KIND_INTERNAL);

    TestTime::addSecond();

    $span->end();

    expect($span)
        ->isRecording()->toBeFalse()
        ->hasEnded()->toBeTrue()
        ->getDuration()->toBe(1_000_000_000);
});

it('can measure a callback', function () {
    /** @var Span $span */
    $span = Tracer::newSpan('test span')->measure(function (SpanInterface $span) {
        TestTime::addSecond();

        expect($span)
            ->getName()->toBe('test span')
            ->getKind()->toBe(SpanKind::KIND_INTERNAL);

        expect(Tracer::activeSpan())->toBe($span);

        return $span;
    });

    expect($span)
        ->hasEnded()->toBeTrue()
        ->getDuration()->toBe(1_000_000_000);
});

it('can record exceptions thrown in the callback', function () {
    $callbackSpan = null;

    try {
        Tracer::newSpan('test span')->measure(function (SpanInterface $span) use (&$callbackSpan) {
            $callbackSpan = $span;

            throw new Exception('test exception');
        });
    } catch (Exception) {
    }

    expect($callbackSpan->toSpanData())
        ->hasEnded()->toBeTrue()
        ->getStatus()->getCode()->toBe(StatusCode::STATUS_UNSET)
        ->getEvents()->toHaveCount(1);

    expect($callbackSpan->toSpanData()->getEvents()[0])
        ->getName()->toBe('exception')
        ->getAttributes()->toMatchArray([
            'exception.type' => 'Exception',
            'exception.message' => 'test exception',
        ]);
});

it('set traceId to log context', function () {
    $span = Tracer::newSpan('test span')->start();
    $scope = $span->activate();

    expect(Log::sharedContext())->toBe([]);

    Tracer::updateLogContext();

    expect(Log::sharedContext())
        ->toMatchArray([
            'traceid' => $span->getContext()->getTraceId(),
        ]);

    $scope->detach();
    $span->end();
});