<?php

namespace App\Console\Commands;

use App\Generations\ApplyProviderState;
use App\Generations\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Safety net for missed webhooks (delivery isn't guaranteed) and lost submit jobs. Runs every minute.
 * Polled results go through the same ApplyProviderState as webhooks, so the two can't disagree.
 */
#[AsCommand(name: 'generations:reconcile', description: 'Poll Pixelforge for generations whose webhooks are overdue')]
class ReconcileGenerations extends Command
{
    /** A normal generation finishes within ~40s, so anything quieter than this is worth a look. */
    private const STALE_AFTER_SECONDS = 90;

    /** Stay well inside Pixelforge's 60 requests/minute. */
    private const MAX_POLLS_PER_RUN = 15;

    public function handle(PixelforgeClient $client, ApplyProviderState $state): int
    {
        $stale = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('provider_id')
            ->where('updated_at', '<', now()->subSeconds(self::STALE_AFTER_SECONDS))
            ->oldest('updated_at')
            ->limit(self::MAX_POLLS_PER_RUN)
            ->get();

        foreach ($stale as $generation) {
            try {
                $outcome = $state->apply($generation->id, $client->getGeneration($generation->provider_id));
                $generation->touch(); // Don't poll it again next minute if nothing changed.
                $this->line("{$generation->id}: {$outcome}");
            } catch (PixelforgeException $e) {
                $this->warn("{$generation->id}: {$e->errorCode} {$e->getMessage()}");
                if ($e->status === 429) {
                    break;
                }
            }
        }

        // Pending generations whose submit job vanished (e.g. a worker crash). Re-submitting is safe:
        // the Idempotency-Key returns the existing generation if the first attempt got through.
        Generation::query()
            ->where('status', GenerationStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(2))
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<', now()->subMinutes(2)))
            ->pluck('id')
            ->each(fn (string $id) => SubmitGeneration::dispatch($id));

        // Finished images we haven't copied yet, while Pixelforge's URL still works.
        Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereNull('image_path')
            ->where('completed_at', '<', now()->subMinutes(2))
            ->where('image_url_expires_at', '>', now())
            ->pluck('id')
            ->each(fn (string $id) => StoreGenerationImage::dispatch($id));

        return self::SUCCESS;
    }
}
