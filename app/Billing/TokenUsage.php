<?php

namespace App\Billing;

/** Token counts for one generation, either estimated or as reported by Pixelforge. */
final readonly class TokenUsage
{
    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
    ) {}

    /** @param array<string, int> $usage The `usage` object from a Pixelforge generation. */
    public static function fromProvider(array $usage): self
    {
        return new self(
            (int) $usage['input_text_tokens'],
            (int) $usage['input_image_tokens'],
            (int) $usage['output_tokens'],
            (int) $usage['reasoning_tokens'],
        );
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
