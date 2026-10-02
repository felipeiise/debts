<?php

namespace App\Domain\Payment\Strategies;

use App\Domain\Payment\PaymentOption;
use App\Domain\Shared\Money;

interface PaymentStrategy
{
    /** @return list<PaymentOption> */
    public function options(Money $amount): array;
}
