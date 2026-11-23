<?php

namespace App\Billing;

/**
 * Turns a generation request (before) or Pixelforge's token usage (after) into provider cost and user charge.
 *
 * Text and reasoning tokens are only known once a generation completes, so an estimate is a range:
 *  - text tokens: ceil(characters / 4), and the real count is within ±15% of that;
 *  - reasoning tokens: anywhere in a per-quality [min, max] range;
 *  - image and output tokens: exact.
 * "Expected" uses the text estimate and the middle of the reasoning range. "Max" is the worst case and is
 * what we hold from a user's balance, so the actual charge can never exceed what we reserved.
 */
final readonly class PricingCalculator
{
    public function __construct(private int $markupPercent) {}

    public function estimate(Pricing $pricing, string $prompt, string $size, string $quality, int $referenceImages): CostEstimate
    {
        $textEstimate = (int) ceil(mb_strlen($prompt) / $pricing->charactersPerTextToken);
        $variance = $pricing->textTokenVariance;
        $imageTokens = $referenceImages * $pricing->tokensPerReferenceImage;
        $outputTokens = $pricing->outputTokens[$quality][$size];
        $reasoning = $pricing->reasoningTokens[$quality];

        $min = new TokenUsage((int) floor($textEstimate * (1 - $variance)), $imageTokens, $outputTokens, $reasoning['min']);
        $expected = new TokenUsage($textEstimate, $imageTokens, $outputTokens, intdiv($reasoning['min'] + $reasoning['max'], 2));
        $max = new TokenUsage((int) ceil($textEstimate * (1 + $variance)), $imageTokens, $outputTokens, $reasoning['max']);

        $rates = $pricing->rates;

        return new CostEstimate(
            expectedUsage: $expected,
            minProviderNanos: $rates->costNanos($min),
            expectedProviderNanos: $rates->costNanos($expected),
            maxProviderNanos: $rates->costNanos($max),
            minChargeNanos: $this->chargeFor($rates->costNanos($min)),
            expectedChargeNanos: $this->chargeFor($rates->costNanos($expected)),
            maxChargeNanos: $this->chargeFor($rates->costNanos($max)),
        );
    }

    /** What Pixelforge charges us for a finished generation, at the rates fixed when it was created. */
    public function providerCost(Rates $rates, TokenUsage $usage): int
    {
        return $rates->costNanos($usage);
    }

    /** What we charge the user: provider cost plus markup, rounded up to the next whole nanodollar. */
    public function chargeFor(int $providerNanos): int
    {
        return Money::ceilDiv($providerNanos * (100 + $this->markupPercent), 100);
    }
}
