<?php

namespace LaraOTel\OpenTelemetryLaravel\Support;

use Monolog\LogRecord;
use Monolog\Handler\AbstractProcessingHandler;
use LaraOTel\OpenTelemetryLaravel\Facades\Log;

class OpenTelemetryMonologHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $level = $record->level->toPsrLogLevel();

        Log::log($level, $record->message, $record->context);
    }
}