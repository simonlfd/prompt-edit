<?php

namespace App\Generations;

/** Plain-language explanations of Pixelforge error codes, for users. */
final class ErrorMessages
{
    public static function for(?string $code): ?string
    {
        return match ($code) {
            null => null,
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy.',
            'generation_failed' => 'The model couldn’t produce an image for this prompt.',
            'internal_error' => 'Something went wrong at Pixelforge while generating. Trying again usually works.',
            'overloaded' => 'Pixelforge is overloaded.',
            'rate_limit_exceeded' => 'Too many requests to Pixelforge right now.',
            'concurrency_limit_exceeded' => 'Pixelforge is already working on the maximum number of images.',
            'daily_limit_exceeded' => 'The daily Pixelforge quota is used up. It resets at midnight UTC.',
            'validation_failed' => 'Pixelforge didn’t accept these settings.',
            'connection_error' => 'We couldn’t reach Pixelforge.',
            'invalid_api_key' => 'Pixelforge didn’t accept our API key. That’s on us, not you.',
            'submission_abandoned' => 'Pixelforge stayed busy for too long, so we stopped trying.',
            default => 'Something went wrong at Pixelforge.',
        };
    }

    /** Codes that mean "we're still trying", shown while the generation is pending. */
    public static function isWaitingForCapacity(?string $code): bool
    {
        return in_array($code, ['overloaded', 'rate_limit_exceeded', 'concurrency_limit_exceeded', 'connection_error'], true)
            || str_starts_with((string) $code, 'http_5');
    }
}
