<?php

namespace App\Domain\Payment;

use App\Domain\Shared\Money;

final readonly class PaymentOption
{
    public function __construct(public string $method, public int $installments, public Money $total, public Money $installmentAmount) {}

    public function toArray(): array
    {
        return ['method' => $this->method, 'installments' => $this->installments, 'total' => $this->total->format(), 'installment_amount' => $this->installmentAmount->format()];
    }
}
