<?php

namespace App\Pixelforge;

use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Downloads a generation's PNG from Pixelforge, re-signing the URL once if the stored one is rejected. */
class ImageFetcher
{
    public function __construct(private readonly PixelforgeClient $client) {}

    public function fetch(Generation $generation): ?string
    {
        if (! $generation->providerImageAvailable()) {
            return null;
        }

        $png = $this->get($generation->image_url);

        if ($png === null && $generation->provider_id) {
            try {
                $png = $this->get($this->client->getGeneration($generation->provider_id)['image_url'] ?? null);
            } catch (PixelforgeException) {
                return null;
            }
        }

        return $png;
    }

    private function get(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(20)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        // Only accept an actual PNG, never an error page saved as an image.
        return $response->successful() && str_starts_with($response->body(), "\x89PNG") ? $response->body() : null;
    }
}
