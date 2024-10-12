<?php

declare(strict_types=1);

namespace LaraOTel\OpenTelemetryLaravel\Watchers\Contracts;

use Illuminate\Contracts\Foundation\Application;

interface WatcherInterface
{
    public function register(Application $app);
}