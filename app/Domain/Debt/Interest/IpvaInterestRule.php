<?php

namespace App\Domain\Debt\Interest;

use App\Domain\Debt\Debt;
use App\Domain\Shared\Money;
use DateTimeImmutable;

final class IpvaInterestRule implements InterestRule
{
    public function interest(Debt $debt, DateTimeImmutable $asOf): Money
    {
        $days = max(0, (int) $debt->dueDate->diff($asOf)->format('%r%a'));
        $interest = $debt->originalAmount->multiplyRatio(33 * $days, 10000);
        $cap = $debt->originalAmount->multiplyRatio(20, 100);
        return Money::cents(min($interest->cents, $cap->cents));
    }
}
