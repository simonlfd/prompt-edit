<?php

use App\Generations\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.images_disk' => 'local']);
});

function finishedGeneration(TestCase $test): Generation
{
    $generation = pendingGeneration();
    sendWebhook($test, webhookEvent($generation, 'succeeded', 'evt_'.$generation->id, [
        'image_url' => 'https://pixelforge.test/images/x.png?signature=abc',
        'image_url_expires_at' => now()->addHours(24)->toIso8601String(),
    ]))->assertOk();

    return $generation->fresh();
}

it('queues a copy of the image as soon as a generation succeeds', function () {
    $generation = finishedGeneration($this);

    Queue::assertPushed(StoreGenerationImage::class, fn ($job) => $job->generationId === $generation->id);
});

it('stores the PNG and serves our copy from then on', function () {
    $generation = finishedGeneration($this);
    Http::fake(['pixelforge.test/images/*' => Http::response(PNG)]);

    app()->call([new StoreGenerationImage($generation->id), 'handle']);

    $generation->refresh();
    expect($generation->image_path)->toBe("generations/{$generation->user_id}/{$generation->id}.png");
    Storage::disk('local')->assertExists($generation->image_path);

    $this->actingAs($generation->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.data.0.image_saved', true)
            ->where('generations.data.0.image_url', "/generations/{$generation->id}/image"));

    $this->actingAs($generation->user)->get(route('generations.image', $generation))->assertOk();
});

it('keeps serving the image after Pixelforge’s link expires', function () {
    $generation = finishedGeneration($this);
    Http::fake(['pixelforge.test/images/*' => Http::response(PNG)]);
    app()->call([new StoreGenerationImage($generation->id), 'handle']);

    $this->travel(2)->days();
    Http::fake(); // Pixelforge would 403 now; we must not even ask.

    $this->actingAs($generation->user)->get(route('generations.image', $generation))->assertOk();
    $download = $this->actingAs($generation->user)->get(route('generations.download', $generation));
    $download->assertOk()->assertDownload($generation->downloadFilename());
    Http::assertNothingSent();
});

it('refuses to store an error page as an image, and retries', function () {
    $generation = finishedGeneration($this);
    Http::fake(['pixelforge.test/images/*' => Http::response('<html>Forbidden</html>', 200)]);
    Http::fake([PF.'/generations/*' => Http::response(providerGeneration($generation, 'succeeded'))]);

    expect(fn () => app()->call([new StoreGenerationImage($generation->id), 'handle']))->toThrow(RuntimeException::class);
    expect($generation->fresh()->image_path)->toBeNull();
});

it('only shows images to their owner', function () {
    $generation = finishedGeneration($this);

    $this->actingAs(User::factory()->create())->get(route('generations.image', $generation))->assertNotFound();
});

it('backfills images the queue missed while Pixelforge’s link is still valid', function () {
    $generation = finishedGeneration($this);
    $this->travel(5)->minutes();
    Queue::fake();

    $this->artisan('generations:reconcile')->assertSuccessful();

    Queue::assertPushed(StoreGenerationImage::class, fn ($job) => $job->generationId === $generation->id);
    expect($generation->status)->toBe(GenerationStatus::Succeeded);
});
