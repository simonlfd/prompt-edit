<?php

namespace App\Billing;

/**
 * The full Pixelforge price list (GET /v1/pricing): rates plus the token tables needed to estimate.
 */
final readonly class Pricing
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  quality => range
     */
    public function __construct(
        public Rates $rates,
        public int $charactersPerTextToken,
        public float $textTokenVariance,
        public int $tokensPerReferenceImage,
        public array $outputTokens,
        public array $reasoningTokens,
    ) {}

    /** @param array<string, mixed> $response */
    public static function fromApi(array $response): self
    {
        $tokens = $response['tokens'];

        return new self(
            rates: new Rates($response['rates']),
            charactersPerTextToken: (int) $tokens['characters_per_text_token'],
            textTokenVariance: (float) $tokens['text_token_variance'],
            tokensPerReferenceImage: (int) $tokens['per_reference_image'],
            outputTokens: $tokens['output'],
            reasoningTokens: $tokens['reasoning'],
        );
    }

    /** @return list<string> */
    public function sizes(): array
    {
        return array_keys($this->outputTokens[array_key_first($this->outputTokens)] ?? []);
    }

    /** @return list<string> */
    public function qualities(): array
    {
        return array_keys($this->outputTokens);
    }
}
