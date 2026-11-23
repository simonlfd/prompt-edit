<?php

use App\Console\Commands\CheckUsage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

function fakeUsage(string $date, int $generations, string $cost): void
{
    Http::fake([PF.'/usage*' => Http::response([
        'object' => 'usage',
        'data' => [['date' => $date, 'generations' => $generations, 'cost_usd' => $cost]],
        'total' => ['generations' => $generations, 'cost_usd' => $cost],
    ])]);
}

it('is only for admins', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.operations'))->assertForbidden();
});

it('shows webhook handling, duplicates and failures', function () {
    $generation = pendingGeneration();
    $event = webhookEvent($generation, 'succeeded', 'evt_1');
    sendWebhook($this, $event)->assertOk();
    sendWebhook($this, $event)->assertOk();
    sendWebhook($this, webhookEvent($generation, 'processing', 'evt_0'))->assertOk();
    $failed = pendingGeneration($generation->user);
    sendWebhook($this, webhookEvent($failed, 'failed', 'evt_2', ['completed_at' => now()->toIso8601String()]))->assertOk();
    fakeUsage(now()->toDateString(), 1, '0.305510000');

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('admin.operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Operations')
            ->where('webhooks.received', 3)
            ->where('webhooks.duplicates_ignored', 1)
            ->where('webhooks.stale_ignored', 1)
            ->where('generations.failures.0.code', 'generation_failed')
            ->has('events.data', 3));
});

it('logs days where our costs drift from Pixelforge', function () {
    Log::spy();
    fakeUsage(now()->toDateString(), 1, '0.100000000'); // We have recorded nothing.

    $this->artisan('usage:check')->expectsOutputToContain('do not match')->assertSuccessful();

    Log::shouldHaveReceived('warning')->once();
    expect(Cache::get(CheckUsage::CACHE_KEY)['mismatches'][0]['pixelforge'])->toBe('0.100000000');
});

it('is quiet when everything matches', function () {
    Log::spy();
    fakeUsage(now()->toDateString(), 0, '0.000000000');

    $this->artisan('usage:check')->expectsOutputToContain('All days match')->assertSuccessful();

    Log::shouldNotHaveReceived('warning');
});
