<?php

declare(strict_types=1);

namespace LaraOTel\OpenTelemetryLaravel\Support;

use Illuminate\Http\Response;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;

use function assert;

/**
 * @internal
 */
class ResponsePropagationSetter implements PropagationSetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    public function set(&$carrier, string $key, string $value): void
    {
        assert($carrier instanceof Response);

        $carrier->headers->set($key, $value);
    }
}
