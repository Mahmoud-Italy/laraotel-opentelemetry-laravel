<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SDK\Trace\ImmutableSpan;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('admin')->default(false);
    });
});

it('can watch a query', function () {
    DB::table('users')->get();

    $span = getRecordedSpans()->last();
    assert($span instanceof ImmutableSpan);

    expect($span)
        ->getName()->toBe('sql SELECT')
        ->getKind()->toBe(SpanKind::KIND_CLIENT)
        ->getAttributes()->toArray()->toBe([
            'db.system' => 'sqlite',
            'db.namespace' => ':memory:',
            'db.operation.name' => 'SELECT',
            'db.query.text' => 'select * from "users"',
        ])
        ->hasEnded()->toBeTrue()
        ->getEndEpochNanos()->toBeLessThan(Clock::getDefault()->now());

    expect($span->getEndEpochNanos() - $span->getStartEpochNanos())
        ->toBeGreaterThan(0);
});

it('can watch a query with bindings', function () {
    DB::table('users')
        ->where('id', 1)
        ->where('name', 'like', 'John%')
        ->get();

    $span = getRecordedSpans()->last();
    assert($span instanceof ImmutableSpan);

    expect($span)
        ->getName()->toBe('sql SELECT')
        ->getKind()->toBe(SpanKind::KIND_CLIENT)
        ->getAttributes()->toArray()->toBe([
            'db.system' => 'sqlite',
            'db.namespace' => ':memory:',
            'db.operation.name' => 'SELECT',
            'db.query.text' => 'select * from "users" where "id" = ? and "name" like ?',
        ]);
});