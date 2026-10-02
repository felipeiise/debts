<?php

namespace Tests\Fakes;

use App\Application\Resilience\ProviderCircuitBreaker;

final class ProviderCircuitBreakerFake implements ProviderCircuitBreaker
{
    /** @var list<string> */
    public array $successfulProviders = [];

    /** @var list<string> */
    public array $failedProviders = [];

    /** @param list<string> $blockedProviders */
    public function __construct(private array $blockedProviders = []) {}

    public function allows(string $provider): bool
    {
        return ! in_array($provider, $this->blockedProviders, true);
    }

    public function recordSuccess(string $provider): void
    {
        $this->successfulProviders[] = $provider;
    }

    public function recordFailure(string $provider): void
    {
        $this->failedProviders[] = $provider;
    }
}
