<?php

namespace LaraOTel\OpenTelemetryLaravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SDK\Logs\Exporter\ConsoleExporterFactory;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporterFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;
use RuntimeException;

class TracerFactory
{
    protected Repository $config;

    public function __construct(protected Application $app)
    {
        $this->config = $this->app['config'];
    }

    public function create(Repository $config): Tracer
    {
        $exporter = $this->createSpanExporter($config);
        $processor = $this->createSpanProcessor($exporter, $config);
        $resource = $this->createResourceInfo($config);
        $sampler = $this->createSampler($config);

        $logExporter = $this->createLogsExporter($config);
        $logProcessor = new BatchLogRecordProcessor(
            exporter: $logExporter,
            clock: Clock::getDefault()
        );

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($logProcessor)
            ->build();

        $traceProvider = TracerProvider::builder()
            ->setResource($resource)
            ->addSpanProcessor($processor)
            ->setSampler($sampler)
            ->build();

        $watchers = $config->get('watchers', $this->config->get('otel.global.watchers', []));

        $tracerName = $config->get('name', 'app');

        return (new Tracer($tracerName, $traceProvider, $loggerProvider))->setWatchers($watchers);
    }

    public function getTracerConfig($name): Repository
    {
        $default = $this->config->get('otel.global');
        $driver = $this->config->get("otel.tracers.{$name}");

        return new Repository(array_merge($default, $driver));
    }

    public function createResourceInfo(Repository $config): ResourceInfo
    {
        $attributes = array_merge([
            ResourceAttributes::SERVICE_NAME => $config->get('service_name'),
        ], $config->get('resource.attributes', []));

        return ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create($attributes)));
    }

    public function createTransport(Repository $config): TransportInterface
    {
        $transport = $config->get('transport');

        // eg. ['App\Trace\CustomTransport::class', ['arg1', 'arg2', ...]]
        if (is_array($transport)) {
            return $this->createFromCustomClass($transport, TransportInterface::class);
        } elseif (is_string($transport)) {
            $factory = Registry::transportFactory($transport);
        } else {
            throw new RuntimeException('Invalid transport type');
        }

        return $factory->create(
            endpoint: $config->get('endpoint', 'php://stdout'),
            contentType: $config->get('content_type', 'application/json'),
            headers: $config->get('headers', []),
            compression: $config->get('compression', null),
            timeout: $config->get('timeout', 10),
            retryDelay: $config->get('retry_delay', 100),
            maxRetries: $config->get('max_retries', 3),
            cacert: $config->get('cacert'),
            cert: $config->get('cert'),
            key: $config->get('key')
        );
    }

    public function createSpanExporter(Repository $config): SpanExporterInterface
    {
        $exporter = $config->get('span_exporter');

        // eg. ['App\Trace\CustomerExporter::class', ['arg1', 'arg2', ...]]
        if (is_array($exporter)) {
            $exporter = $this->createFromCustomClass($exporter, SpanExporterInterface::class);
        }

        return match ($exporter) {
            'otlp' => new SpanExporter($this->createTransport($config)),
            'memory' => new InMemoryExporter(),
            'console' => new ConsoleSpanExporter($this->createTransport($config)),
            default => throw new RuntimeException('Unsupported span exporter.'),
        };
    }

    public function createSpanProcessor(SpanExporterInterface $exporter, Repository $config): SpanProcessorInterface
    {
        $processor = $config->get('span_processor');

        // eg. ['App\Trace\CustomerProcessor::class', ['arg1', 'arg2', ...]]
        if (is_array($processor)) {
            $processor = $this->createFromCustomClass($processor, SpanProcessorInterface::class);
        }

        if (! is_subclass_of($processor, SpanProcessorInterface::class)) {
            throw new RuntimeException(sprintf('The span processor must be an instance of %s.', SpanProcessorInterface::class));
        }

        return match (true) {
            is_subclass_of($processor, BatchSpanProcessor::class),
            $processor == BatchSpanProcessor::class => (new BatchSpanProcessorBuilder($exporter))->build(),
            default => new $processor($exporter),
        };
    }

    public function createSampler(Repository $config): SamplerInterface
    {
        $sampler = $config->get('sampler', AlwaysOnSampler::class);

        // eg. ['App\Trace\CustomerSampler::class', ['arg1', 'arg2', ...]]
        if (is_array($sampler)) {
            $sampler = $this->createFromCustomClass($sampler, SamplerInterface::class);
        }

        return new ParentBased(new $sampler);
    }

    protected function createLogsExporter(Repository $config): LogRecordExporterInterface
    {
        $logsExporter = $config->get('log_exporter', 'memory');

        return match ($logsExporter) {
            'otlp' => new LogsExporter($this->createTransport(new Repository([
                'transport' => 'stream',
                'endpoint' => storage_path('logs/otel.log'),
            ]))),
            'console' => (new ConsoleExporterFactory())->create(),
            default => (new InMemoryExporterFactory())->create()
        };
    }

    public function createFromCustomClass(array $config, string $interface): object
    {
        // eg. ['App\Trace\CustomerSampler::class', ['arg1', 'arg2', ...]]
        if (empty($config) || ! is_string($config[0]) || ! class_exists($config[0]) || ! is_subclass_of($config[0], $interface)) {
            throw new RuntimeException(sprintf('The custom class must be a implementing %s.', $interface));
        }

        $className = array_shift($config);

        return new $className(...$config);
    }
}
