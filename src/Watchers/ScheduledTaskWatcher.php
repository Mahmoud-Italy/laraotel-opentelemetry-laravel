<?php

namespace LaraOTel\OpenTelemetryLaravel\Watchers;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\SpanInterface;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Watchers\Contracts\WatcherInterface;

class ScheduledTaskWatcher implements WatcherInterface
{
    protected SpanInterface $span;

    public function register(Application $app): void
    {
        $app['events']->listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event) {
            $this->span = Measure::span('[Schedule] '.$event->task->getSummaryForDisplay())
                ->setAttributes([
                    'task.command' => $event->task->command,
                    'task.description' => $event->task->description,
                    'task.expression' => $event->task->expression,
                    'task.exitCode' => $event->task->exitCode,
                ])
                ->start();
            $this->span->storeInContext(Context::getCurrent());
        });

        $app['events']->listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            $this->span->end();
        });

        $app['events']->listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) {
            $this->span->setAttribute('task.error', $event->exception->getMessage());
            $this->span->setStatus(StatusCode::STATUS_ERROR);
            $this->span->recordException($event->exception);
            $this->span->end();
        });
    }
}
