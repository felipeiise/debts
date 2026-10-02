<?php

namespace App\Infrastructure\Resilience;

use App\Application\Resilience\ProviderCircuitBreaker;

final class NullProviderCircuitBreaker implements ProviderCircuitBreaker
{
    public function allows(string $provider): bool
    {
        return true;
    }

    public function recordSuccess(string $provider): void
    {
    }

    public function recordFailure(string $provider): void
    {
    }
}
