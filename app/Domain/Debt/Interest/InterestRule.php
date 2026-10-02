<?php

namespace App\Domain\Debt\Interest;

use App\Domain\Debt\Debt;
use App\Domain\Shared\Money;
use DateTimeImmutable;

interface InterestRule
{
    public function interest(Debt $debt, DateTimeImmutable $asOf): Money;
}
