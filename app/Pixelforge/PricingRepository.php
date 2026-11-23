<?php

namespace App\Pixelforge;

use App\Billing\Pricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pixelforge says to read rates from GET /v1/pricing rather than hardcode them. We cache the price list
 * and keep the last good copy, so a Pixelforge outage doesn't stop us showing estimates.
 * Estimates only: actual costs always use the rates on the generation itself.
 */
class PricingRepository
{
    private const FRESH_KEY = 'pixelforge.pricing.fresh';

    private const LAST_GOOD_KEY = 'pixelforge.pricing.last_good';

    public function __construct(private readonly PixelforgeClient $client) {}

    public function current(): Pricing
    {
        $response = Cache::get(self::FRESH_KEY);

        if ($response === null) {
            try {
                $response = $this->client->pricing();
                Cache::put(self::FRESH_KEY, $response, now()->addMinutes(10));
                Cache::forever(self::LAST_GOOD_KEY, $response);
            } catch (PixelforgeException $e) {
                $response = Cache::get(self::LAST_GOOD_KEY);
                if ($response === null) {
                    throw $e;
                }
                Log::warning('Pixelforge pricing unavailable, using last known price list.', ['error' => $e->getMessage()]);
            }
        }

        return Pricing::fromApi($response);
    }
}
