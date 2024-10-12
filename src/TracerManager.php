<?php

declare(strict_types=1);

namespace LaraOTel\OpenTelemetryLaravel;

use Illuminate\Config\Repository;
use Illuminate\Support\Manager;

class TracerManager extends Manager
{
    public function get(string $name)
    {
        $config = $this->configurationFor($name);

        return $this->driver($config['driver'] ?? null);
    }

    public function getDefaultDriver(): string
    {
        return 'console';
    }

    public function createConsoleDriver(): Tracer
    {
        return $this->container->make(TracerFactory::class)
            ->create($this->configurationFor('console'));
    }

    public function createLogDriver(): Tracer
    {
        return $this->container->make(TracerFactory::class)
            ->create($this->configurationFor('log'));
    }

    public function createTextDriver(): Tracer
    {
        return $this->container->make(TracerFactory::class)
            ->create($this->configurationFor('text'));
    }

    public function createZipkinDriver(): Tracer
    {
        return $this->container->make(TracerFactory::class)
            ->create($this->configurationFor('zipkin'));
    }

    public function createHttpJsonDriver(): Tracer
    {
        return $this->container->make(TracerFactory::class)
            ->create($this->configurationFor('http-json'));
    }

    public function createHttpBinaryDriver(): Tracer
    {
        return $this->container->make(TracerFactory::class)
            ->create($this->configurationFor('http-binary'));
    }

    public function createGrpcDriver(): Tracer
    {
        return $this->container->make(TracerFactory::class)
            ->create($this->configurationFor('grpc'));
    }

    public function configurationFor(string $name): Repository
    {
        return new Repository(array_merge(
            $this->config->get('otel.global', []),
            $this->config->get("otel.tracers.{$name}", [])
        ));
    }
}
