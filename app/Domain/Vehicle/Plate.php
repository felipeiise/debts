<?php

namespace App\Domain\Vehicle;

use InvalidArgumentException;

final readonly class Plate
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        $plate = strtoupper(preg_replace('/[ -]/', '', trim($value)) ?? '');
        if (! preg_match('/^(?:[A-Z]{3}[0-9]{4}|[A-Z]{3}[0-9][A-Z][0-9]{2})$/', $plate)) {
            throw new InvalidArgumentException('Plate must use the Brazilian old or Mercosur format.');
        }
        return new self($plate);
    }

    public function masked(): string { return substr($this->value, 0, 3).'****'; }
}
