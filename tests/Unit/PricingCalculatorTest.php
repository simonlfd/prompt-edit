<?php

use App\Billing\Pricing;
use App\Billing\PricingCalculator;
use App\Billing\TokenUsage;

beforeEach(function () {
    $this->pricing = Pricing::fromApi(pricingResponse());
    $this->calculator = new PricingCalculator(25);
});

it('matches the worked "most a generation can cost" example from the docs', function () {
    // High, 1536x1024, 400-character prompt, 2 reference images: max total $0.373875.
    $estimate = $this->calculator->estimate($this->pricing, str_repeat('a', 400), '1536x1024', 'high', 2);

    expect($estimate->maxProviderNanos)->toBe(373_875_000);
});

it('charges the provider cost plus 25%', function () {
    expect($this->calculator->chargeFor(373_875_000))->toBe(467_343_750)
        ->and($this->calculator->chargeFor(0))->toBe(0);
});

it('rounds the markup up to the next nanodollar, never down', function () {
    expect($this->calculator->chargeFor(1))->toBe(2)    // 1.25 → 2
        ->and($this->calculator->chargeFor(4))->toBe(5); // exactly 5
});

it('computes the exact provider cost of the documented succeeded generation', function () {
    $usage = new TokenUsage(12, 765, 6450, 3184);

    // 12×5 + 765×10 + 6450×40 + 3184×12.5 = 305,510 µ$ per 1M → $0.305510
    expect($this->calculator->providerCost($this->pricing->rates, $usage))->toBe(305_510_000);
});

it('matches the usage endpoint example: 41 text, 3300 output, 2950 reasoning tokens = $0.169080', function () {
    $usage = new TokenUsage(41, 0, 3300, 2950);

    expect($this->calculator->providerCost($this->pricing->rates, $usage))->toBe(169_080_000);
});

it('counts prompt characters, not bytes', function () {
    $ascii = $this->calculator->estimate($this->pricing, 'abcd', '1024x1024', 'low', 0);
    $emoji = $this->calculator->estimate($this->pricing, '🎨🎨🎨🎨', '1024x1024', 'low', 0);

    expect($emoji->expectedUsage->inputTextTokens)->toBe(1)
        ->and($ascii->expectedUsage->inputTextTokens)->toBe(1);
});

it('brackets every possible actual cost between min and max', function () {
    $estimate = $this->calculator->estimate($this->pricing, str_repeat('x', 103), '1024x1024', 'medium', 1);
    $textEstimate = 26; // ceil(103 / 4)

    foreach ([(int) floor($textEstimate * 0.85), (int) ceil($textEstimate * 1.15)] as $text) {
        foreach ([200, 2000] as $reasoning) {
            $cost = $this->pricing->rates->costNanos(new TokenUsage($text, 765, 1100, $reasoning));
            expect($cost)->toBeGreaterThanOrEqual($estimate->minProviderNanos)
                ->toBeLessThanOrEqual($estimate->maxProviderNanos);
        }
    }

    expect($estimate->expectedProviderNanos)->toBeGreaterThan($estimate->minProviderNanos)
        ->toBeLessThan($estimate->maxProviderNanos);
});

it('estimates output tokens exactly from size and quality', function (string $quality, string $size, int $tokens) {
    $estimate = $this->calculator->estimate($this->pricing, 'x', $size, $quality, 0);

    expect($estimate->expectedUsage->outputTokens)->toBe($tokens);
})->with([
    ['low', '1024x1024', 280],
    ['medium', '1536x1024', 1650],
    ['high', '1024x1536', 6450],
]);

it('breaks the cost down per token type, summing to the total', function () {
    $usage = new TokenUsage(12, 765, 6450, 3184);
    $lines = $this->pricing->rates->lineCostsNanos($usage);

    expect($lines)->toBe([
        'input_text_tokens' => 60_000,
        'input_image_tokens' => 7_650_000,
        'output_tokens' => 258_000_000,
        'reasoning_tokens' => 39_800_000,
    ])->and(array_sum($lines))->toBe($this->pricing->rates->costNanos($usage));
});
