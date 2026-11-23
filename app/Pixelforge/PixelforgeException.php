<?php

namespace App\Pixelforge;

use RuntimeException;

/** A Pixelforge API error, or a network failure (status 0) where we don't know if the request landed. */
class PixelforgeException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Retrying is safe (we always send an Idempotency-Key) and may succeed: rate/concurrency limits,
     * overload, server errors and network failures. Daily quota is excluded: it won't clear for hours.
     */
    public function isRetryable(): bool
    {
        return match (true) {
            $this->errorCode === 'daily_limit_exceeded' => false,
            $this->status === 0, $this->status === 429, $this->status >= 500 => true,
            default => false,
        };
    }

    /** Pixelforge guarantees nothing was created for these, so giving up can't leave an orphan behind. */
    public function nothingWasCreated(): bool
    {
        return $this->status !== 0 && $this->status < 500 || $this->errorCode === 'overloaded';
    }
}
