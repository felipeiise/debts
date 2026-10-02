<?php

namespace App\Domain\Payment\Strategies;

use App\Domain\Payment\PaymentOption;
use App\Domain\Shared\Money;

final class CreditCardPayment implements PaymentStrategy
{
    public function options(Money $amount): array
    {
        $options = [new PaymentOption('CREDIT_CARD', 1, $amount, $amount)];
        foreach ([6, 12] as $months) {
            // PMT = P * r / (1 - (1+r)^-n); calculate with fixed-point integer ratios.
            $factor = $this->powerRatio(1025, 1000, $months); // (1 + 2.5%)^n at 1e9 precision
            $numerator = 25 * $factor;
            $denominator = 1000 * ($factor - 1_000_000_000);
            $ratio = $this->ratioAtScale($numerator, $denominator, 1_000_000_000);
            $whole = intdiv($amount->cents, 1_000_000_000) * $ratio;
            $remainder = $amount->cents % 1_000_000_000;
            $paymentCents = $whole + intdiv($remainder * $ratio + 500_000_000, 1_000_000_000);
            $total = Money::cents($paymentCents * $months);
            $options[] = new PaymentOption('CREDIT_CARD', $months, $total, Money::cents($paymentCents));
        }
        return $options;
    }

    private function powerRatio(int $numerator, int $denominator, int $power): int
    {
        // Fixed-point exponentiation; rounding at the 9th decimal keeps the PMT stable without floats.
        $scale = 1_000_000_000;
        $result = $scale;
        $base = intdiv($numerator * $scale + intdiv($denominator, 2), $denominator);
        for ($i = 0; $i < $power; $i++) $result = intdiv($result * $base + intdiv($scale, 2), $scale);
        return $result;
    }

    private function ratioAtScale(int $numerator, int $denominator, int $scale): int
    {
        $whole = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;
        $fraction = 0;
        $digits = strlen((string) $scale) - 1;
        for ($i = 0; $i < $digits; $i++) {
            $remainder *= 10;
            $fraction = ($fraction * 10) + intdiv($remainder, $denominator);
            $remainder %= $denominator;
        }
        // Include a guard digit for HALF_UP rounding.
        $remainder *= 10;
        if (intdiv($remainder, $denominator) >= 5) $fraction++;
        return ($whole * $scale) + $fraction;
    }
}
