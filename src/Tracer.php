<?php

namespace LaraOTel\OpenTelemetryLaravel;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\TraceAttributes;

class Tracer
{
    public function __construct(
        protected string $name,
        protected TracerProviderInterface $tracerProvider,
        protected LoggerProviderInterface $loggerProvider,
        protected ?TextMapPropagatorInterface $textMapPropagator = null,
        protected array $watchers = [],
    ) {
    }

    public function setWatchers(array $watchers): static
    {
        $this->watchers = $watchers;

        return $this;
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register(Application $app): void
    {
        $textMapPropagator = $this->textMapPropagator ?? TraceContextPropagator::getInstance();

        Sdk::builder()
            ->setTracerProvider($this->tracerProvider)
            ->setLoggerProvider($this->loggerProvider)
            ->setPropagator($textMapPropagator)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        $instrumentation = new CachedInstrumentation(
            name: 'opentelemetry-laravel',
            version: class_exists(InstalledVersions::class) ? InstalledVersions::getPrettyVersion('laraotel/opentelemetry-laravel') : null,
            schemaUrl: TraceAttributes::SCHEMA_URL,
        );

        $app->bind(TextMapPropagatorInterface::class, fn () => $this->textMapPropagator);
        $app->bind(TracerInterface::class, fn () => $instrumentation->tracer());
        $app->bind(LoggerInterface::class, fn () => $instrumentation->logger());

        // Register watchers.
        foreach ($this->watchers as $watcher) {
            $app->make($watcher)->register($app);
        }

        $app->terminating(function () {
            $this->tracerProvider->forceFlush();
            $this->loggerProvider->forceFlush();
        });
    }
}
