<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads go through us: the image lives on Pixelforge's domain, and browsers ignore the `download`
 * attribute on cross-origin links, so a plain link would just open the PNG. Proxying also lets us name the
 * file after the prompt and explain expiry instead of showing a broken link.
 */
class DownloadController extends Controller
{
    public function __invoke(Request $request, Generation $generation, PixelforgeClient $client): Response|RedirectResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        if (! $generation->imageAvailable()) {
            return $this->fail('This image has expired. Pixelforge keeps images for 24 hours.');
        }

        $png = $this->fetch($generation->image_url);

        // The stored signed URL may have been rejected; ask Pixelforge for a freshly signed one (same expiry).
        if ($png === null && $generation->provider_id) {
            try {
                $png = $this->fetch($client->getGeneration($generation->provider_id)['image_url'] ?? null);
            } catch (PixelforgeException) {
                $png = null;
            }
        }

        if ($png === null) {
            return $this->fail('We couldn’t fetch this image from Pixelforge. Try again in a moment.');
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$generation->downloadFilename().'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function fetch(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(20)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    private function fail(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
