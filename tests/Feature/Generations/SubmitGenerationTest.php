<?php

use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function runSubmitJob(Generation $generation): SubmitGeneration
{
    $job = (new SubmitGeneration($generation->id))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

it('submits with our id as the Idempotency-Key and metadata', function () {
    $generation = pendingGeneration();
    Http::fake([PF.'/generations' => Http::response(providerGeneration($generation, 'queued', ['id' => 'gen_1']), 202)]);

    runSubmitJob($generation);

    Http::assertSent(fn (Request $request) => $request->url() === PF.'/generations'
        && $request->header('Idempotency-Key')[0] === $generation->id
        && $request['metadata']['generation_id'] === $generation->id
        && $request['reference_images'] === ['https://example.com/style.png']
        && $request->hasHeader('Authorization', 'Bearer pf_test'));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->provider_id)->toBe('gen_1')
        ->and($generation->submitted_at)->not->toBeNull();
});

it('omits reference_images when there are none, since unknown or empty params may be rejected', function () {
    $generation = pendingGeneration(attributes: ['reference_images' => []]);
    Http::fake([PF.'/generations' => Http::response(providerGeneration($generation, 'queued'), 202)]);

    runSubmitJob($generation);

    Http::assertSent(fn (Request $request) => ! array_key_exists('reference_images', $request->data()));
});

it('backs off and retries on rate limits, respecting Retry-After', function () {
    $generation = pendingGeneration();
    Http::fake([PF.'/generations' => Http::response(
        ['error' => ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Too many']],
        429,
        ['Retry-After' => '5'],
    )]);

    $job = runSubmitJob($generation);

    $job->assertReleased();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->error_code)->toBe('concurrency_limit_exceeded')
        ->and($generation->next_attempt_at)->not->toBeNull();
});

it('retries when Pixelforge is overloaded', function () {
    $generation = pendingGeneration();
    Http::fake([PF.'/generations' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'Busy']], 503, ['Retry-After' => '2'])]);

    runSubmitJob($generation)->assertReleased();
});

it('gives up after the attempt cap and makes it free', function () {
    $generation = pendingGeneration();
    $generation->forceFill(['submit_attempts' => SubmitGeneration::MAX_ATTEMPTS - 1])->save();
    Http::fake([PF.'/generations' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'Busy']], 503)]);

    runSubmitJob($generation)->assertNotReleased();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('submission_abandoned')
        ->and($generation->actual_charge_nanos)->toBe(0)
        ->and($generation->user->balance_nanos)->toBe(10_000_000_000);
});

it('fails immediately and free when the prompt is blocked', function () {
    $generation = pendingGeneration(attributes: ['prompt' => 'something [blocked]']);
    Http::fake([PF.'/generations' => Http::response(['error' => ['code' => 'content_policy_violation', 'message' => 'Rejected']], 400)]);

    runSubmitJob($generation)->assertNotReleased();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('content_policy_violation')
        ->and($generation->user->balance_nanos)->toBe(10_000_000_000);
});

it('does not retry the daily quota', function () {
    $generation = pendingGeneration();
    Http::fake([PF.'/generations' => Http::response(['error' => ['code' => 'daily_limit_exceeded', 'message' => 'Quota']], 429)]);

    runSubmitJob($generation)->assertNotReleased();
    expect($generation->fresh()->status)->toBe(GenerationStatus::Failed);
});

it('retries network failures with the same idempotency key', function () {
    $generation = pendingGeneration();
    Http::fake([PF.'/generations' => Http::failedConnection()]);

    runSubmitJob($generation)->assertReleased();
    expect($generation->fresh()->error_code)->toBe('connection_error');
});

it('does nothing if a webhook already moved the generation on', function () {
    $generation = pendingGeneration();
    $generation->forceFill(['status' => GenerationStatus::Processing])->save();
    Http::fake();

    runSubmitJob($generation);

    Http::assertNothingSent();
});

it('accepts a late success after we gave up, so our records match what Pixelforge bills', function () {
    $generation = pendingGeneration();
    $generation->forceFill(['submit_attempts' => SubmitGeneration::MAX_ATTEMPTS - 1])->save();
    Http::fake([PF.'/generations' => Http::failedConnection()]);
    runSubmitJob($generation);
    expect($generation->fresh()->status)->toBe(GenerationStatus::Failed);

    // But the request had actually reached Pixelforge.
    sendWebhook($this, webhookEvent($generation, 'succeeded', 'evt_late'))->assertOk();

    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->fresh()->actual_provider_nanos)->toBe(305_510_000);
});

it('applies the create response even when it is an idempotent replay of a finished generation', function () {
    $generation = pendingGeneration();
    Http::fake([PF.'/generations' => Http::response(providerGeneration($generation, 'succeeded'), 202, ['Idempotent-Replayed' => 'true'])]);

    runSubmitJob($generation);

    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('is dispatched when a user creates a generation', function () {
    Queue::fake();
    $generation = pendingGeneration();

    Queue::assertPushed(SubmitGeneration::class, fn ($job) => $job->generationId === $generation->id);
});

it('can be pushed onto a real queue driver', function () {
    // Regression: the sync driver and Queue::fake() skip the payload building that broke this job.
    config(['queue.default' => 'database']);
    $user = userWithCredits();

    $this->actingAs($user)->post(route('generations.store'), [
        'prompt' => 'A red fox', 'size' => '1024x1024', 'quality' => 'low',
    ])->assertSessionHasNoErrors();

    expect(DB::table('jobs')->count())->toBe(1);
});
