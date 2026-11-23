<?php

namespace App\Jobs;

use App\Generations\ApplyProviderState;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a pending generation to Pixelforge. Retries limits and outages with capped exponential backoff,
 * always with the same Idempotency-Key (our generation id), so a retry can never create a second generation.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Retries we schedule ourselves (rate limits, overload…). Roughly 10 minutes of waiting in total. */
    public const MAX_ATTEMPTS = 12;

    public const MAX_BACKOFF_SECONDS = 60;

    /** Attempts are counted on the generation row; the queue's own counter only guards against crashes. */
    public int $tries = self::MAX_ATTEMPTS + 3;

    public int $maxExceptions = 3;

    public function __construct(public string $generationId) {}

    public function handle(PixelforgeClient $client, ApplyProviderState $state): void
    {
        $generation = Generation::find($this->generationId);

        if (! $generation || $generation->status !== GenerationStatus::Pending) {
            return; // Already accepted (maybe via an early webhook) or already final.
        }

        $generation->increment('submit_attempts');

        try {
            $object = $client->createGeneration($generation->toProviderRequest(), $generation->id);
        } catch (PixelforgeException $e) {
            $this->handleError($generation, $e, $state);

            return;
        }

        Generation::whereKey($generation->id)->whereNull('submitted_at')->update(['submitted_at' => now()]);
        $state->apply($generation->id, $object);
    }

    private function handleError(Generation $generation, PixelforgeException $e, ApplyProviderState $state): void
    {
        if ($e->isRetryable() && $generation->submit_attempts < self::MAX_ATTEMPTS) {
            $delay = $this->backoff($generation->submit_attempts, $e->retryAfter);

            $generation->forceFill([
                'error_code' => $e->errorCode,
                'error_message' => $e->getMessage(),
                'next_attempt_at' => now()->addSeconds($delay),
            ])->save();

            $this->release($delay);

            return;
        }

        $code = $e->isRetryable() ? 'submission_abandoned' : $e->errorCode;
        $state->failLocally($generation->id, $code, $e->getMessage());
    }

    /** Respect Retry-After, but back off further each attempt: 2s, 4s, 8s… capped at 60s. */
    private function backoff(int $attempt, ?int $retryAfter): int
    {
        $exponential = min(self::MAX_BACKOFF_SECONDS, 2 ** $attempt);

        return max($retryAfter ?? 1, $exponential) + random_int(0, 2);
    }

    public function failed(?Throwable $exception): void
    {
        app(ApplyProviderState::class)->failLocally($this->generationId, 'submission_abandoned', $exception?->getMessage() ?? 'Submission failed.');
    }
}
