<?php

namespace LaraOTel\OpenTelemetryLaravel;

use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Map\Psr3;
use OpenTelemetry\API\Common\Time\Clock;
use Psr\Log\LogLevel;

class Logger
{
    public function __construct(
        protected LoggerInterface $logger
    ) {
        //
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $logRecord = (new LogRecord($message))
            ->setTimestamp(Clock::getDefault()->now())
            ->setSeverityNumber(Psr3::fromPsr3($level))
            ->setSeverityText($level)
            ->setAttributes($context);

        $this->logger->emit($logRecord);
    }
}
