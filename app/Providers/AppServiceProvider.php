<?php

namespace App\Providers;

use App\Infrastructure\Providers\Contracts\DebtProvider;
use App\Infrastructure\Providers\ProviderA\ProviderAAdapter;
use App\Infrastructure\Providers\ProviderB\ProviderBAdapter;
use App\Application\Vehicle\GetVehicleDebts;
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
            $app->make(\App\Domain\Debt\DebtCalculator::class),
            $app->make(\App\Domain\Payment\PaymentSimulator::class),
        ));
    }
}
