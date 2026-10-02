<?php

namespace App\Domain\Payment\Strategies;

use App\Domain\Payment\PaymentOption;
use App\Domain\Shared\Money;

final class PixPayment implements PaymentStrategy
{
    public function options(Money $amount): array
    {
        $discounted = $amount->multiplyRatio(95, 100);
        return [new PaymentOption('PIX', 1, $discounted, $discounted)];
    }
}
