<?php

declare(strict_types=1);

namespace LaraOTel\OpenTelemetryLaravel\Watchers;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Contracts\Foundation\Application;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Watchers\Contracts\WatcherInterface;

class LogWatcher implements WatcherInterface
{
    public function register(Application $app): void
    {
        $app['events']->listen(MessageLogged::class, $this->recordLog(...));
    }

    public function recordLog(MessageLogged $log): void
    {
        $attributes = [
            'level' => $log->level,
        ];

        $attributes['context'] = json_encode(array_filter($log->context));

        $message = $log->message;

        Measure::activeSpan()->addEvent($message, $attributes);
    }
}
