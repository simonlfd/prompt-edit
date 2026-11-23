<?php

namespace App\Billing;

final readonly class CostEstimate
{
    public function __construct(
        public TokenUsage $expectedUsage,
        public int $minProviderNanos,
        public int $expectedProviderNanos,
        public int $maxProviderNanos,
        public int $minChargeNanos,
        public int $expectedChargeNanos,
        public int $maxChargeNanos,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tokens' => $this->expectedUsage->toArray(),
            'min_charge' => Money::toDecimal($this->minChargeNanos),
            'expected_charge' => Money::toDecimal($this->expectedChargeNanos),
            'max_charge' => Money::toDecimal($this->maxChargeNanos),
        ];
    }
}
