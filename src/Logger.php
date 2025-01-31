<?php

namespace LaraOTel\OpenTelemetryLaravel;

use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LogRecord;
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
            ->setSeverityNumber($this->mapSeverityNumber($level)) // Use manual mapping
            ->setSeverityText($level)
            ->setAttributes($context);

        $this->logger->emit($logRecord);
    }

    private function mapSeverityNumber(string $level): int {
        return match ($level) {
            LogLevel::EMERGENCY => 1,  // Most severe
            LogLevel::ALERT => 2,
            LogLevel::CRITICAL => 3,
            LogLevel::ERROR => 4,
            LogLevel::WARNING => 5,
            LogLevel::NOTICE => 6,
            LogLevel::INFO => 7,
            LogLevel::DEBUG => 8,  // Least severe

            default => 0, // Unknown level
        };
    }
}
