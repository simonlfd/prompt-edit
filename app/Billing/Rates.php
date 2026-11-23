<?php

namespace App\Billing;

/**
 * Pixelforge rates in USD per 1M tokens, kept as the exact decimal strings the API returns.
 */
final readonly class Rates
{
    public const KEYS = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    /** @param array<string, string> $rates */
    public function __construct(public array $rates)
    {
        foreach (self::KEYS as $key) {
            if (! isset($rates[$key])) {
                throw new \InvalidArgumentException("Missing rate [{$key}].");
            }
        }
    }

    /** Exact cost in nanodollars. Pixelforge never rounds, so we only round up if a rate ever has >3 decimals. */
    public function costNanos(TokenUsage $usage): int
    {
        // nanos per 1M tokens × tokens, summed, then divided once by 1M: exact for any rate with ≤ 9 decimals.
        $numerator = 0;
        foreach ($usage->toArray() as $key => $tokens) {
            $numerator += $tokens * Money::nanosFromDecimal($this->rates[$key]);
        }

        return Money::ceilDiv($numerator, 1_000_000);
    }

    /**
     * Cost of each token type on its own, for showing a breakdown. Lines are rounded up individually,
     * so in theory they can sum to a nanodollar more than costNanos(); with today's rates they're exact.
     *
     * @return array<string, int>
     */
    public function lineCostsNanos(TokenUsage $usage): array
    {
        $lines = [];
        foreach ($usage->toArray() as $key => $tokens) {
            $lines[$key] = Money::ceilDiv($tokens * Money::nanosFromDecimal($this->rates[$key]), 1_000_000);
        }

        return $lines;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_intersect_key($this->rates, array_flip(self::KEYS));
    }
}
