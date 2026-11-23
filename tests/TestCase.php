<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never talk to the real Pixelforge from tests; serve the documented price list by default.
        configurePixelforge();
        config(['inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
        Http::fake([PF.'/pricing' => Http::response(pricingResponse())]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
