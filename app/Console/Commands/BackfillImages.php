<?php

namespace App\Console\Commands;

use App\Generations\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Re-copies images that are recorded as stored but aren't on the current images disk, e.g. after
 * switching IMAGES_DISK from an ephemeral local disk to a bucket. Only possible while Pixelforge's
 * signed URL is still valid (24 hours after completion).
 */
#[AsCommand(name: 'images:backfill', description: 'Re-store images missing from the current images disk')]
class BackfillImages extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.images_disk'));
        $this->info('Images disk: '.config('filesystems.images_disk').' ('.config('filesystems.disks.'.config('filesystems.images_disk').'.driver').')');

        $requeued = $lost = $present = 0;

        Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereNotNull('image_path')
            ->each(function (Generation $generation) use ($disk, &$requeued, &$lost, &$present) {
                if ($disk->exists($generation->image_path)) {
                    $present++;

                    return;
                }

                $generation->forceFill(['image_path' => null])->save();

                if ($generation->providerImageAvailable()) {
                    StoreGenerationImage::dispatch($generation->id);
                    $requeued++;
                } else {
                    $lost++;
                }
            });

        $this->line("Already on disk: {$present}. Re-queued from Pixelforge: {$requeued}. Expired at Pixelforge, can't recover: {$lost}.");

        return $lost > 0 ? self::FAILURE : self::SUCCESS;
    }
}
