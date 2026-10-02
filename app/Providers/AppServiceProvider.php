<?php

namespace App\Providers;

use App\Application\Vehicle\GetVehicleDebts;
use App\Domain\Debt\DebtCalculator;
use App\Domain\Payment\PaymentSimulator;
use App\Infrastructure\Providers\Contracts\DebtProvider;
use App\Infrastructure\Providers\ProviderA\ProviderAAdapter;
use App\Infrastructure\Providers\ProviderB\ProviderBAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            ProviderAAdapter::class,
            ProviderBAdapter::class,
        ], DebtProvider::class);
        $this->app->bind(GetVehicleDebts::class, fn ($app) => new GetVehicleDebts(
            $app->tagged(DebtProvider::class),
            $app->make(DebtCalculator::class),
            $app->make(PaymentSimulator::class),
        ));
    }
}
