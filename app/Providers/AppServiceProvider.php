<?php

namespace App\Providers;

use App\Application\Resilience\ProviderCircuitBreaker;
use App\Application\Vehicle\GetVehicleDebts;
use App\Domain\Debt\DebtCalculator;
use App\Domain\Payment\PaymentSimulator;
use App\Infrastructure\Providers\Contracts\DebtProvider;
use App\Infrastructure\Providers\ProviderA\ProviderAAdapter;
use App\Infrastructure\Providers\ProviderB\ProviderBAdapter;
use App\Infrastructure\Resilience\NullProviderCircuitBreaker;
use App\Infrastructure\Resilience\RedisProviderCircuitBreaker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProviderCircuitBreaker::class, function ($app) {
            if (! $app['config']->get('services.vehicle_debts.circuit_breaker.enabled')) {
                return new NullProviderCircuitBreaker;
            }

            return new RedisProviderCircuitBreaker;
        });

        $this->app->tag([
            ProviderAAdapter::class,
            ProviderBAdapter::class,
        ], DebtProvider::class);
        $this->app->bind(GetVehicleDebts::class, fn ($app) => new GetVehicleDebts(
            $app->tagged(DebtProvider::class),
            $app->make(DebtCalculator::class),
            $app->make(PaymentSimulator::class),
            $app->make(ProviderCircuitBreaker::class),
        ));
    }
}
