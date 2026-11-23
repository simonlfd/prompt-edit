<?php

namespace App\Billing;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $requiredNanos, public readonly int $balanceNanos)
    {
        parent::__construct('Not enough credits.');
    }
}
