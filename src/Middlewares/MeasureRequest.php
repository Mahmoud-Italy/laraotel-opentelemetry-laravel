<?php

namespace LaraOTel\OpenTelemetryLaravel\Middlewares;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator;
use OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator;
use OpenTelemetry\SemConv\TraceAttributes;
use LaraOTel\OpenTelemetryLaravel\Facades\Measure;
use LaraOTel\OpenTelemetryLaravel\Support\ResponsePropagationSetter;
use LaraOTel\OpenTelemetryLaravel\TracerManager;
use LaraOTel\OpenTelemetryLaravel\Traits\InteractWithHttpHeaders;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MeasureRequest
{
    use InteractWithHttpHeaders;

    /**
     * @throws \Throwable
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function handle(Request $request, Closure $next, ?string $name = null)
    {
        $name = $name ?? config('otel.default');

        static::$allowedHeaders = $this->normalizeHeaders(config('otel.allowed_headers', []));

        static::$sensitiveHeaders = array_merge(
            $this->normalizeHeaders(config('otel.sensitive_headers', [])),
            $this->defaultSensitiveHeaders
        );

        /** @var \LaraOTel\OpenTelemetryLaravel\Tracer $tracer */
        $tracer = app(TracerManager::class)->driver($name);
        $tracer->register(app());

        $span = Measure::span(sprintf('%s:%s', $request?->method() ?? 'unknown', $request->url()))
            ->setAttributes($this->getRequestSpanAttributes($request))
            ->start();
        $context = $span->storeInContext(Context::getCurrent());
        $scope = $context->activate();

        try {
            $response = $next($request);

            $this->recordHeaders($span, $request);

            if ($response instanceof Response) {
                $this->recordHttpResponseToSpan($span, $response);
                $this->propagateHeaderToResponse($context, $response);
            }

            // Add trace id to response header if configured.
            if ($traceIdHeaderName = config('otel.response_trace_header_name')) {
                $response->headers->set($traceIdHeaderName, Measure::traceId());
            }

            return $response;
        } catch (Throwable $exception) {
            $span->recordException($exception)
                ->setStatus(StatusCode::STATUS_ERROR);

            throw $exception;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    protected function recordHttpResponseToSpan(SpanInterface $span, Response $response): void
    {
        $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());

        if (($content = $response->getContent()) !== false) {
            $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, strlen($content));
        }

        $this->recordHeaders($span, $response);

        if ($response->isSuccessful()) {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        if ($response->isServerError() || $response->isClientError()) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }
    }

    protected function propagateHeaderToResponse($context, Response $response): void
    {
        // Propagate `server-timing` header to response, if ServerTimingPropagator is present
        if (class_exists('OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator')) {
            $prop = new ServerTimingPropagator();
            $prop->inject($response, ResponsePropagationSetter::instance(), $context);
        }

        // Propagate `traceresponse` header to response, if TraceResponsePropagator is present
        if (class_exists('OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator')) {
            $prop = new TraceResponsePropagator();
            $prop->inject($response, ResponsePropagationSetter::instance(), $context);
        }
    }

    protected function recordHeaders(SpanInterface $span, Request|Response $http): SpanInterface
    {
        $prefix = match (true) {
            $http instanceof Request => 'http.request.header.',
            $http instanceof Response => 'http.response.header.',
        };

        foreach ($http->headers->all() as $key => $value) {
            $key = strtolower($key);

            if (! static::headerIsAllowed($key)) {
                continue;
            }

            $value = static::headerIsSensitive($key) ? ['*****'] : $value;

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $span->setAttribute($prefix.$key, $value);
        }

        return $span;
    }

    protected static function httpHostName(Request $request): string
    {
        if (method_exists($request, 'host')) {
            return $request->host();
        }
        if (method_exists($request, 'getHost')) {
            return $request->getHost();
        }

        return '';
    }

    public function getRequestSpanAttributes(Request $request): array
    {
        return [
            TraceAttributes::URL_FULL => $request->fullUrl(),
            TraceAttributes::HTTP_REQUEST_METHOD => $request->method(),
            TraceAttributes::HTTP_REQUEST_BODY_SIZE => $request->header('Content-Length'),
            TraceAttributes::URL_SCHEME => $request->getScheme(),
            TraceAttributes::NETWORK_PROTOCOL_VERSION => $request->getProtocolVersion(),
            TraceAttributes::NETWORK_PEER_ADDRESS => $request->ip(),
            TraceAttributes::URL_PATH => $request->path(),
            TraceAttributes::HTTP_ROUTE => $request->getUri(),
            TraceAttributes::SERVER_ADDRESS => self::httpHostName($request),
            TraceAttributes::SERVER_PORT => $request->getPort(),
            TraceAttributes::CLIENT_PORT => $request->server('REMOTE_PORT'),
            TraceAttributes::USER_AGENT_ORIGINAL => $request->userAgent(),
            TraceAttributes::NETWORK_PROTOCOL_NAME => $request->server('SERVER_PROTOCOL'),
        ];
    }
}