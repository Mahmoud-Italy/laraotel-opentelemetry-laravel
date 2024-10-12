<?php

use LaraOTel\OpenTelemetryLaravel\Watchers;

return [
    /**
     * Enable or disable the OpenTelemetry Laravel Extension.
     */
    'enabled' => env('OTEL_ENABLED', true),

    /**
     * Default tracer name, you can switch it in runtime.
     * all tracers should be defined in `tracers` section.
     */
    'default' => env('OTEL_DEFAULT_TRACER', 'log'),

    /**
     * Auto Register the MeasureRequest middleware.
     */
    'automatically_trace_requests' => env('OTEL_AUTO_TRACE_REQUESTS', true),

    /**
     * Auto start console tracer
     */
    'automatically_trace_cli' => env('OTEL_AUTO_TRACE_CLI', true),

    /**
     * Allow to trace requests with specific headers. You can use `*` as wildcard.
     */
    'allowed_headers' => [
        'referer',
        'x-*',
        'accept',
        'request-id',
    ],

    /**
     * Sensitive headers will be marked as *** from the span attributes. You can use `*` as wildcard.
     */
    'sensitive_headers' => [
        // 'cookie',
        // 'authorization',
        // ...
    ],

    /**
     * The name of the header that will be used to pass the trace id in the response.
     * if set to `null`, the header will not be added to the response.
     */
    'response_trace_header_name' => env('OTEL_RESPONSE_TRACE_HEADER_NAME', 'X-Trace-Id'),

    /**
     * Will be applied to all channels. you can override it in the channel config.
     */
    'global' => [
        /**
         * Service name.
         */
        'service_name' => env('OTEL_SERVICE_NAME', env('APP_NAME', 'laravel')),

        /**
         * Tracer name.
         */
        'name' => env('OTEL_TRACER_NAME', 'app'),

        /**
         * Sampler is used to determine if a span should be recorded.
         */
        'sampler' => \OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler::class,

        'resource' => [
            'attributes' => [
                // ResourceAttributes::SERVICE_VERSION => env('APP_VERSION', '1.0.0'),
                // 'token' => env('OTEL_RESOURCE_TOKEN', 'token'),
            ],
        ],

        /**
         * Watchers to be registered.
         */
        'watchers' => [
            Watchers\CacheWatcher::class,
            Watchers\ExceptionWatcher::class,
            Watchers\LogWatcher::class,
            Watchers\DatabaseQueryWatcher::class,
            Watchers\AuthenticateWatcher::class,
            Watchers\HttpClientRequestWatcher::class,
        ],

        /**
         * Transport, you can use pre-defined transports: `stream`, `http`, `grpc`.
         * or your custom transport class by implementing `OpenTelemetry\SDK\Trace\TransportInterface` interface:
         * for example: [`App\Trace\Transports\YourCustomTransport::class`, 'arg1', 'arg2']
         */
        'transport' => env('OTEL_DEFAULT_TRANSPORT', 'http'),

        /**
         * Span exporter, you can use pre-defined exporters: `memory`, `console`, `otlp`.
         * or your custom span exporter by implementing `OpenTelemetry\SDK\Trace\SpanExporterInterface` interface.
         * for example: [`App\Trace\Exporters\YourCustomExporter::class`, 'arg1', 'arg2']
         */
        'span_exporter' => env('OTEL_DEFAULT_SPAN_EXPORTER', 'otlp'),

        /**
         * Span processor, you can use your custom span processor by implementing `OpenTelemetry\SDK\Trace\SpanProcessorInterface` interface.
         * for example: [`App\Trace\SpanProcessors\YourCustomSpanProcessor::class`, 'arg1', 'arg2']
         */
        'span_processor' => \OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor::class,

        /**
         * Id generator, you can use your custom id generator by implementing `OpenTelemetry\SDK\Trace\IdGeneratorInterface` interface.
         * for example: [`App\Trace\IdGenerators\YourCustomIdGenerator::class`, 'arg1', 'arg2']
         */
        'id_generator' => \OpenTelemetry\SDK\Trace\RandomIdGenerator::class,

        /**
         * Log exporter, you can use pre-defined exporters: `memory`, `console`, `otlp`.
         */
        'log_exporter' => env('OTEL_DEFAULT_LOG_EXPORTER', 'otlp'),
    ],

    /**
     * Tracers configurations. you can add more tracers here.
     * and all the configurations should overwrite the global configurations.
     * available drivers: `console`, `log`, `text`, `zipkin`, `http-json`, `http-binary`, `grpc`.
     */
    'tracers' => [
        'console' => [
            'driver' => 'console',
            'transport' => 'stream',
            'span_exporter' => 'console',
        ],

        'log' => [
            'driver' => 'log',
            'transport' => 'stream',
            'span_exporter' => 'console',
            'endpoint' => storage_path('logs/otel.log'),
        ],

        'text' => [
            'driver' => 'text',
            'transport' => 'stream',
            'endpoint' => storage_path('logs/otel.text'),
        ],

        'zipkin' => [
            'driver' => 'zipkin',
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => env('OTEL_EXPORTER_ZIPKIN_ENDPOINT', 'http://zipkin:9411/api/v2/spans'),
            'content_type' => 'application/json',
        ],

        'http-json' => [
            'driver' => 'http-json',
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => env('OTEL_HTTP_JSON_ENDPOINT', 'http://localhost:9411/v1/traces'),
            'content_type' => 'application/json',
        ],

        'http-binary' => [
            'driver' => 'http-binary',
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => env('OTEL_HTTP_BINARY_ENDPOINT', 'http://localhost:4318/v1/traces'),
            'content_type' => 'application/x-protobuf',
        ],

        // You should install php extension `ext-grpc` to use this driver.
        //        'grpc' => [
        //            'driver' => 'grpc',
        //            'transport' => 'grpc',
        //            'span_exporter' => 'otlp',
        //            'endpoint' => env('OTEL_GRPC_ENDPOINT', 'http://localhost:4317/v1/traces'),
        //            'content_type' => 'application/x-protobuf',
        //        ],
    ],
];
