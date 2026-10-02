<?php

namespace App\Domain\Shared;

use InvalidArgumentException;

/** Exact monetary values represented as integer cents. */
final readonly class Money
{
    private function __construct(public int $cents) {}

    public static function fromDecimal(string|int $amount): self
    {
        $value = trim((string) $amount);
        if (! preg_match('/^-?\d+(?:\.\d{1,})?$/', $value)) {
            throw new InvalidArgumentException('Invalid decimal amount.');
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }
        return new self(($negative ? -1 : 1) * $cents);
    }

    public static function cents(int $cents): self { return new self($cents); }

    /** Multiply cents by a rational factor and round HALF_UP to a cent. */
    public function multiplyRatio(int $numerator, int $denominator): self
    {
        if ($denominator <= 0) throw new InvalidArgumentException('Denominator must be positive.');
        $product = $this->cents * $numerator;
        $sign = $product < 0 ? -1 : 1;
        $absolute = abs($product);
        return new self($sign * intdiv($absolute + intdiv($denominator, 2), $denominator));
    }

    public function plus(self $other): self { return new self($this->cents + $other->cents); }
    public function minus(self $other): self { return new self($this->cents - $other->cents); }
    public function format(): string { return sprintf('%s%d.%02d', $this->cents < 0 ? '-' : '', intdiv(abs($this->cents), 100), abs($this->cents) % 100); }
}
