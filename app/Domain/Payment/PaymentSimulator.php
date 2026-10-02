<?php

namespace App\Domain\Payment;

use App\Domain\Payment\Strategies\CreditCardPayment;
use App\Domain\Payment\Strategies\PixPayment;
use App\Domain\Shared\Money;

final class PaymentSimulator
{
    public function simulate(Money $amount): array
    {
        return [...(new PixPayment)->options($amount), ...(new CreditCardPayment)->options($amount)];
    }
}
