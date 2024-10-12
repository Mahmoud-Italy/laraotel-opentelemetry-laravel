<?php

namespace LaraOTel\OpenTelemetryLaravel\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait InteractWithHttpHeaders
{
    /**
     * @var array<string>
     */
    protected array $defaultSensitiveHeaders = [
        'authorization',
        'cookie',
        'set-cookie',
    ];

    /**
     * @var array<string>
     */
    protected static array $allowedHeaders = [];

    /**
     * @var array<string>
     */
    protected static array $sensitiveHeaders = [];

    /**
     * @return array<string>
     */
    public static function getAllowedHeaders(): array
    {
        return static::$allowedHeaders;
    }

    public static function headerIsAllowed(string $header): bool
    {
        return Str::is(static::getAllowedHeaders(), $header);
    }

    /**
     * @return array<string>
     */
    public static function getSensitiveHeaders(): array
    {
        return static::$sensitiveHeaders;
    }

    public static function headerIsSensitive(string $header): bool
    {
        return Str::is(static::getSensitiveHeaders(), $header);
    }

    protected function normalizeHeaders(array $headers): array
    {
        return Arr::map(
            $headers,
            fn (string $header) => strtolower(trim($header)),
        );
    }
}