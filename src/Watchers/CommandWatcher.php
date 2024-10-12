<?php

namespace LaraOTel\OpenTelemetryLaravel\Watchers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\SpanInterface;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Watchers\Contracts\WatcherInterface;

class CommandWatcher implements WatcherInterface
{
    protected SpanInterface $span;

    public function register(Application $app): void
    {
        $app['events']->listen(CommandStarting::class, function (CommandStarting $event) {
            $this->span = Measure::span('[Command] '.$event->command)
                ->setAttributes([
                    'command' => $event->command,
                    'arguments' => $event->input->getArguments(),
                    'options' => $event->input->getOptions(),
                ])
                ->start();
            $this->span->storeInContext(Context::getCurrent());
        });

        $app['events']->listen(CommandFinished::class, function (CommandFinished $event) {
            $this->span->end();
        });
    }
}
