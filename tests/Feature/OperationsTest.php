<?php

use App\Console\Commands\CheckUsage;
use App\Models\User;
use App\Reports\OperationsTimeline;
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

it('buckets the last 24 hours of outcomes and webhooks by hour', function () {
    $this->travelTo('2026-11-23 15:40:00');
    $ok = pendingGeneration();
    $event = webhookEvent($ok, 'succeeded', 'evt_ok', ['completed_at' => '2026-11-23T15:10:00Z']);
    sendWebhook($this, $event)->assertOk();
    sendWebhook($this, $event)->assertOk(); // duplicate
    $bad = pendingGeneration($ok->user);
    sendWebhook($this, webhookEvent($bad, 'failed', 'evt_bad', ['completed_at' => '2026-11-23T13:05:00Z']))->assertOk();

    $timeline = app(OperationsTimeline::class)->build();

    expect($timeline['hours'])->toHaveCount(24)
        ->and($timeline['hours'][23])->toBe('2026-11-23T15:00:00+00:00')
        ->and($timeline['generations']['succeeded'][23])->toBe(1)
        ->and($timeline['generations']['failed'][21])->toBe(1)
        ->and(array_sum($timeline['generations']['failed']))->toBe(1)
        ->and($timeline['webhooks']['applied'][23])->toBe(2)
        ->and($timeline['webhooks']['duplicates'][23])->toBe(1);
});

it('warns when production keeps images on a non-bucket disk', function () {
    Http::fake([PF.'/usage*' => Http::response(['object' => 'usage', 'data' => [], 'total' => []])]);
    $this->app['env'] = 'production';
    config(['filesystems.images_disk' => 'local']);

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.operations'))
        ->assertInertia(fn (Assert $page) => $page->where('storage.ephemeral_risk', true)->where('storage.driver', 'local'));
});
