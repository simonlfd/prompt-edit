<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Pixelforge\ImageFetcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads go through us: browsers ignore the `download` attribute on cross-origin links, so a plain
 * link to Pixelforge would just open the PNG. This also names the file after the prompt.
 */
class DownloadController extends Controller
{
    public function __invoke(Request $request, Generation $generation, ImageFetcher $fetcher): Response|RedirectResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        if ($generation->imageStored()) {
            $disk = Storage::disk(config('filesystems.images_disk'));

            // Private bucket: a short-lived signed URL that tells the browser to save it under our filename.
            if (ImageController::usesSignedUrls()) {
                return redirect()->away($disk->temporaryUrl(
                    $generation->image_path,
                    now()->addMinutes(ImageController::SIGNED_URL_MINUTES),
                    [
                        'ResponseContentType' => 'image/png',
                        'ResponseContentDisposition' => 'attachment; filename="'.$generation->downloadFilename().'"',
                    ],
                ));
            }

            return $disk->download($generation->image_path, $generation->downloadFilename(), ['Content-Type' => 'image/png']);
        }

        if (! $generation->providerImageAvailable()) {
            return $this->fail('This image has expired. Pixelforge keeps images for 24 hours.');
        }

        $png = $fetcher->fetch($generation);

        if ($png === null) {
            return $this->fail('We couldn’t fetch this image from Pixelforge. Try again in a moment.');
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$generation->downloadFilename().'"',
        ]);
    }

    private function fail(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
