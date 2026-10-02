<?php

namespace App\Domain\Debt;

use App\Domain\Debt\Interest\IpvaInterestRule;
use App\Domain\Debt\Interest\MultaInterestRule;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use LogicException;

final class DebtCalculator
{
    public function interest(Debt $debt, DateTimeImmutable $asOf): Money
    {
        $rule = match ($debt->type) {
            DebtType::IPVA => new IpvaInterestRule(),
            DebtType::MULTA => new MultaInterestRule(),
        };
        return $rule->interest($debt, $asOf);
    }

    public function total(Debt $debt, DateTimeImmutable $asOf): Money
    {
        return $debt->originalAmount->plus($this->interest($debt, $asOf));
    }
}
