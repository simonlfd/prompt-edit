<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a generation's image to its owner: our stored copy if we have it, else Pixelforge's URL.
 *
 * The bucket is private. On S3-style disks we check ownership here and then redirect to a short-lived
 * signed URL, so the browser fetches from storage directly instead of tying up a PHP worker per image.
 * On a local disk we stream the file.
 */
class ImageController extends Controller
{
    public const SIGNED_URL_MINUTES = 5;

    public function __invoke(Request $request, Generation $generation): Response|RedirectResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        if ($generation->imageStored()) {
            $disk = Storage::disk(config('filesystems.images_disk'));

            if (self::usesSignedUrls()) {
                return redirect()->away($disk->temporaryUrl($generation->image_path, now()->addMinutes(self::SIGNED_URL_MINUTES)));
            }

            // A local copy can vanish (e.g. an ephemeral disk was wiped); then fall back to Pixelforge below.
            if ($disk->exists($generation->image_path)) {
                return $disk->response(
                    $generation->image_path,
                    headers: ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=31536000, immutable'],
                );
            }
        }

        abort_unless($generation->providerImageAvailable(), 404);

        return redirect()->away($generation->image_url);
    }

    /** True for object storage (S3, R2, Laravel Cloud buckets), where the client can fetch directly. */
    public static function usesSignedUrls(): bool
    {
        return config('filesystems.disks.'.config('filesystems.images_disk').'.driver') === 's3';
    }
}
