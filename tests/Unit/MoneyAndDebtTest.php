<?php

namespace Tests\Unit;

use App\Domain\Debt\Debt;
use App\Domain\Debt\DebtCalculator;
use App\Domain\Debt\DebtType;
use App\Domain\Payment\PaymentSimulator;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MoneyAndDebtTest extends TestCase
{
    public function test_money_rounds_half_up_without_floats(): void
    {
        self::assertSame('1.01', Money::fromDecimal('1.005')->format());
        self::assertSame('1.00', Money::fromDecimal('1.004')->format());
    }

    public function test_ipva_simple_interest_is_capped_at_twenty_percent(): void
    {
        $debt = new Debt('ipva', DebtType::IPVA, Money::fromDecimal('1000.00'), new DateTimeImmutable('2025-01-01'));
        $rule = new DebtCalculator;
        self::assertSame('200.00', $rule->interest($debt, new DateTimeImmutable('2025-03-15'))->format());
    }

    public function test_ipva_uses_simple_daily_interest_before_the_cap(): void
    {
        $debt = new Debt('ipva', DebtType::IPVA, Money::fromDecimal('100.00'), new DateTimeImmutable('2025-01-01'));
        $calculator = new DebtCalculator;

        self::assertSame('3.30', $calculator->interest($debt, new DateTimeImmutable('2025-01-11'))->format());
        self::assertSame('103.30', $calculator->total($debt, new DateTimeImmutable('2025-01-11'))->format());
    }

    public function test_multa_interest_is_one_percent_per_day_and_not_capped(): void
    {
        $debt = new Debt('multa', DebtType::MULTA, Money::fromDecimal('100.00'), new DateTimeImmutable('2025-01-01'));
        self::assertSame('40.00', (new DebtCalculator)->interest($debt, new DateTimeImmutable('2025-02-10'))->format());
    }

    public function test_non_overdue_debt_has_no_interest(): void
    {
        $debt = new Debt('future', DebtType::MULTA, Money::fromDecimal('100'), new DateTimeImmutable('2025-02-01'));
        self::assertSame('0.00', (new DebtCalculator)->interest($debt, new DateTimeImmutable('2025-01-31'))->format());
    }

    public function test_payment_options_include_pix_and_card_terms(): void
    {
        $options = (new PaymentSimulator)->simulate(Money::fromDecimal('1000'));
        self::assertSame(['950.00', '1000.00', '181.56', '97.49'], array_map(fn ($option) => $option->installmentAmount->format(), $options));
        self::assertSame(['950.00', '1000.00', '1089.36', '1169.88'], array_map(fn ($option) => $option->total->format(), $options));
    }
}
