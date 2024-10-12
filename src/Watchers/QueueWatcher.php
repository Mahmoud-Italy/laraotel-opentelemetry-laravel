<?php

namespace LaraOTel\OpenTelemetryLaravel\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SemConv\TraceAttributes;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Traits\InteractWithEventTimestamp;
use LaraOTel\OpenTelemetryLaravel\Watchers\Contracts\WatcherInterface;

class QueueWatcher implements WatcherInterface
{
    use InteractWithEventTimestamp;

    /**
     * @var array<string, \OpenTelemetry\API\Trace\SpanInterface>
     */
    protected array $activeSpans = [];

    public function register(Application $app): void
    {
        $this->recordJobQueueing($app);
        $this->recordJobProcessing($app);
    }

    protected function recordJobQueueing($app): void
    {
        if ($app->resolved('queue')) {
            $this->registerQueueInterceptor($app['queue']);
        } else {
            $app->afterResolving('queue', fn ($queue) => $this->registerQueueInterceptor($queue));
        }

        $app['events']->listen(JobQueued::class, function (JobQueued $event) {
            $uuid = $event->payload()['uuid'] ?? null;

            if (! is_string($uuid)) {
                return;
            }

            $span = $this->activeSpans[$uuid] ?? null;

            $span?->end();

            unset($this->activeSpans[$uuid]);
        });
    }

    protected function registerQueueInterceptor(QueueManager $queue): void
    {
        $queue->createPayloadUsing(function (string $connection, ?string $queue, array $payload) {
            $uuid = $payload['uuid'];

            if (! is_string($uuid)) {
                return $payload;
            }

            $jobName = Arr::get($payload, 'displayName', 'unknown');
            $queueName = Str::after($queue ?? 'default', 'queues:');

            $span = Measure::span(sprintf('%s enqueue', $jobName))
                ->setSpanKind(SpanKind::KIND_PRODUCER)
                ->setAttribute(TraceAttributes::MESSAGING_SYSTEM, $this->connectionDriver($connection))
                ->setAttribute(TraceAttributes::MESSAGING_OPERATIONTYPE, 'enqueue')
                ->setAttribute(TraceAttributes::MESSAGEID, $uuid)
                ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_NAME, $queueName)
                ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_TEMPLATE, $jobName)
                ->start();

            $context = $span->storeInContext(Context::getCurrent());

            $this->activeSpans[$uuid] = $span;

            return Measure::propagationHeaders($context);
        });
    }

    protected function recordJobProcessing(): void
    {
        app('events')->listen(JobProcessing::class, function (JobProcessing $event) {
            $context = Measure::extractContextFromPropagationHeaders($event->job->payload());

            $span = Measure::span(sprintf('%s process', $event->job->resolveName()))
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->setParent($context)
                ->setAttribute(TraceAttributes::MESSAGING_SYSTEM, $this->connectionDriver($event->connectionName))
                ->setAttribute(TraceAttributes::MESSAGING_OPERATIONTYPE, 'process')
                ->setAttribute(TraceAttributes::MESSAGEID, $event->job->uuid())
                ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_NAME, $event->job->getQueue())
                ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_TEMPLATE, $event->job->resolveName())
                ->start();
        });

        app('events')->listen(JobProcessed::class, function (JobProcessed $event) {
            $context = Measure::extractContextFromPropagationHeaders($event->job->payload());
            $span = Span::fromContext($context);
            $span->end();
        });

        app('events')->listen(JobFailed::class, function (JobFailed $event) {
            $context = Measure::extractContextFromPropagationHeaders($event->job->payload());
            $span = Span::fromContext($context);

            $span->recordException($event->exception)
                ->setStatus(StatusCode::STATUS_ERROR);

            $span->end();
        });
    }

    protected function connectionDriver(string $connection): string
    {
        return config(sprintf('queue.connections.%s.driver', $connection), 'unknown');
    }
}
