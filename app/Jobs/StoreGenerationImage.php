<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Pixelforge\ImageFetcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copies a finished image into our own storage, so it keeps working after Pixelforge's signed URL
 * expires (24 hours). Retried with backoff; generations:reconcile re-dispatches any that slip through
 * while the Pixelforge URL is still valid.
 */
class StoreGenerationImage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 300];

    public function __construct(public string $generationId) {}

    public function uniqueId(): string
    {
        return $this->generationId;
    }

    public function handle(ImageFetcher $fetcher): void
    {
        $generation = Generation::find($this->generationId);

        if (! $generation || $generation->imageStored() || ! $generation->providerImageAvailable()) {
            return;
        }

        $png = $fetcher->fetch($generation)
            ?? throw new RuntimeException("Couldn't fetch the image for generation {$generation->id}.");

        Storage::disk(config('filesystems.images_disk'))->put($generation->storagePath(), $png);

        Generation::whereKey($generation->id)->whereNull('image_path')->update(['image_path' => $generation->storagePath()]);
    }
}
