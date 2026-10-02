<?php

namespace App\Domain\Debt;

use App\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class Debt
{
    public function __construct(public string $id, public DebtType $type, public Money $originalAmount, public DateTimeImmutable $dueDate) {}
}
