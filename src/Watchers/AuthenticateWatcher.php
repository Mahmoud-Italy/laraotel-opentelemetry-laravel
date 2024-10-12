<?php

declare(strict_types=1);

namespace LaraOTel\OpenTelemetryLaravel\Watchers;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Trace\Span;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Watchers\Contracts\WatcherInterface;

class AuthenticateWatcher implements WatcherInterface
{
    public function register(Application $app): void
    {
        $app['events']->listen(Login::class, function (Login $event) {
            $span = Measure::activeSpan();

            if ($span instanceof Span) {
                $span->setAttribute('user.id', $event->user->getKey());
            }
        });
    }
}
