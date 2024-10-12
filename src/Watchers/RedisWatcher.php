<?php

namespace LaraOTel\OpenTelemetryLaravel\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Redis\RedisManager;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Traits\InteractWithEventTimestamp;
use LaraOTel\OpenTelemetryLaravel\Watchers\Contracts\WatcherInterface;

class RedisWatcher implements WatcherInterface
{
    use InteractWithEventTimestamp;

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register(Application $app): void
    {
        $app['events']->listen(CommandExecuted::class, $this->recordCommand(...));

        if ($app->resolved('redis')) {
            $this->registerRedisEvents($app->make('redis'));
        } else {
            $app->afterResolving('redis', fn ($redis) => $this->registerRedisEvents($redis));
        }
    }

    public function recordCommand(CommandExecuted $event): void
    {
        $name = sprintf('redis %s %s', $event->connection->getName(), $event->command);

        $span = Measure::span($name)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->getEventStartTimestampNs($event->time))
            ->start();

        if ($span->isRecording()) {
            $span->setAttribute(TraceAttributes::DB_SYSTEM, 'redis')
                ->setAttribute(TraceAttributes::DB_QUERYTEXT, $this->formatCommand($event->command, $event->parameters))
                ->setAttribute(TraceAttributes::SERVER_ADDRESS, $event->connection->client()->getHost());
        }

        $span->end();
    }

    protected function formatCommand(string $command, array $parameters): string
    {
        $parameters = collect($parameters)->map(function ($parameter) {
            if (is_array($parameter)) {
                return collect($parameter)->map(function ($value, $key) {
                    if (is_array($value)) {
                        return json_encode($value);
                    }

                    return is_int($key) ? $value : sprintf('%s %s', $key, $value);
                })->implode(' ');
            }

            return $parameter;
        })->implode(' ');

        return sprintf('%s %s', $command, $parameters);
    }

    protected function registerRedisEvents(mixed $redis): void
    {
        if ($redis instanceof RedisManager) {
            foreach ($redis->connections() as $connection) {
                $connection->setEventDispatcher(app('events'));
            }

            $redis->enableEvents();
        }
    }
}
