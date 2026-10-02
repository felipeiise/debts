<?php

namespace App\Infrastructure\Providers\Contracts;

use App\Domain\Vehicle\Plate;

interface DebtProvider
{
    public function name(): string;

    /** @return list<array<string, mixed>> */
    public function debts(Plate $plate): array;
}
