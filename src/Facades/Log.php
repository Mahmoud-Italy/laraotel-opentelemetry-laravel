<?php

namespace LaraOTel\OpenTelemetryLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use LaraOTel\OpenTelemetryLaravel\Logger;

/**
 * @method static void log(string $level, string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 */
class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Logger::class;
    }
}