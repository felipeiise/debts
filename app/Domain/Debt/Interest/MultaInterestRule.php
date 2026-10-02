<?php

namespace App\Domain\Debt\Interest;

use App\Domain\Debt\Debt;
use App\Domain\Shared\Money;
use DateTimeImmutable;

final class MultaInterestRule implements InterestRule
{
    public function interest(Debt $debt, DateTimeImmutable $asOf): Money
    {
        $days = max(0, (int) $debt->dueDate->diff($asOf)->format('%r%a'));

        return $debt->originalAmount->multiplyRatio($days, 100);
    }
}
