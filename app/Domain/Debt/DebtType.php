<?php

namespace App\Domain\Debt;

enum DebtType: string
{
    case IPVA = 'IPVA';
    case MULTA = 'MULTA';
}
