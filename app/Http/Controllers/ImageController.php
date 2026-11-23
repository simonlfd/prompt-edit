<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serves a generation's image to its owner: our stored copy if we have it, else Pixelforge's URL. */
class ImageController extends Controller
{
    public function __invoke(Request $request, Generation $generation): Response|RedirectResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        if ($generation->imageStored()) {
            return Storage::disk(config('filesystems.images_disk'))->response(
                $generation->image_path,
                headers: ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=31536000, immutable'],
            );
        }

        abort_unless($generation->providerImageAvailable(), 404);

        return redirect()->away($generation->image_url);
    }
}
