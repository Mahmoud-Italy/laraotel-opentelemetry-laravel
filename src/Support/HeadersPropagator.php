<?php

declare(strict_types=1);

namespace LaraOTel\OpenTelemetryLaravel\Support;

use Illuminate\Http\Request;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;

use function assert;

/**
 * @internal
 */
class HeadersPropagator implements PropagationGetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    public function keys($carrier): array
    {
        assert($carrier instanceof Request);

        return $carrier->headers->keys();
    }

    public function get($carrier, string $key): ?string
    {
        assert($carrier instanceof Request);

        return $carrier->headers->get($key);
    }
}
