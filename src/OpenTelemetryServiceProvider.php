<?php

declare(strict_types=1);

namespace LaraOTel\OpenTelemetryLaravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Common\Time\Clock;
use LaraOTel\OpenTelemetryLaravel\Middlewares\MeasureRequest;
use LaraOTel\OpenTelemetryLaravel\Support\CarbonClock;
use LaraOTel\OpenTelemetryLaravel\Support\Measure;
use LaraOTel\OpenTelemetryLaravel\Support\OpenTelemetryMonologHandler;
use LaraOTel\OpenTelemetryLaravel\Watchers\CommandWatcher;
use LaraOTel\OpenTelemetryLaravel\Watchers\ScheduledTaskWatcher;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/otel.php' => $this->app->configPath('otel.php'),
        ], 'config');

        if (config('otel.enabled') === false) {
            return;
        }

        $this->injectLogConfig();

        if (config('otel.automatically_trace_requests')) {
            $this->injectHttpMiddleware(app(Kernel::class));
        }

        if ($this->app->runningInConsole() && config('otel.automatically_trace_cli')) {
            $tracer = $this->app->make(TracerManager::class)->driver(config('otel.default'));
            $tracer->register($this->app);

            foreach ([
                CommandWatcher::class,
                ScheduledTaskWatcher::class,
            ] as $watcher) {
                $this->app->make($watcher)->register($this->app);
            }

            $span = Facades\Measure::span('artisan')
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->start();
            $scope = $span->activate();

            $this->app->terminating(function () use ($span, $scope) {
                $span->end();
                $scope->detach();
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otel.php', 'otel',
        );

        if (config('otel.enabled') === false) {
            return;
        }

        Clock::setDefault(new CarbonClock());

        $this->app->singleton(TracerFactory::class, function ($app) {
            return new TracerFactory($app);
        });

        $this->app->singleton(Measure::class, function ($app) {
            return new Measure($app);
        });

        $this->app->singleton(TracerManager::class, function ($app) {
            return new TracerManager($app);
        });
    }

    protected function injectLogConfig(): void
    {
        $this->callAfterResolving(Repository::class, function (Repository $config) {
            if ($config->has('logging.channels.otlp')) {
                return;
            }

            $config->set('logging.channels.otlp', [
                'driver' => 'monolog',
                'handler' => OpenTelemetryMonologHandler::class,
                'level' => 'debug',
            ]);
        });
    }

    protected function injectHttpMiddleware(Kernel $kernel): void
    {
        if (! $kernel instanceof \Illuminate\Foundation\Http\Kernel) {
            return;
        }

        if (! $kernel->hasMiddleware(MeasureRequest::class)) {
            $kernel->prependMiddleware(MeasureRequest::class);
        }
    }
}
