<?php

namespace App\Application\Resilience;

interface ProviderCircuitBreaker
{
    public function allows(string $provider): bool;

    public function recordSuccess(string $provider): void;

    public function recordFailure(string $provider): void;
}
