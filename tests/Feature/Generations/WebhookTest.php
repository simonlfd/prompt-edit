<?php

use App\Generations\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\WebhookEvent;
use App\Pixelforge\WebhookSignature;

beforeEach(function () {
    $this->generation = pendingGeneration();
    $this->user = $this->generation->user;
    $this->startBalance = 10_000_000_000 - $this->generation->max_charge_nanos; // $10 minus the hold
});

it('rejects requests without a valid signature', function () {
    $event = webhookEvent($this->generation, 'succeeded', 'evt_1');

    sendWebhook($this, $event, 't='.time().',v1=deadbeef')->assertStatus(400);
    sendWebhook($this, $event, '')->assertStatus(400);

    expect(WebhookEvent::count())->toBe(0)
        ->and($this->generation->fresh()->status)->toBe(GenerationStatus::Pending);
});

it('rejects old signatures to stop replays', function () {
    $event = webhookEvent($this->generation, 'succeeded', 'evt_1');
    $payload = json_encode($event);
    $old = (new WebhookSignature(WEBHOOK_SECRET, 300))->header($payload, time() - 301);

    sendWebhook($this, $event, $old)->assertStatus(400);
});

it('rejects a body that was tampered with after signing', function () {
    $event = webhookEvent($this->generation, 'succeeded', 'evt_1');
    $header = (new WebhookSignature(WEBHOOK_SECRET, 300))->header(json_encode($event), time());
    $event['data']['object']['usage']['output_tokens'] = 1;

    sendWebhook($this, $event, $header)->assertStatus(400);
});

it('records the exact cost when a generation succeeds', function () {
    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_1'))->assertOk();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->actual_provider_nanos)->toBe(305_510_000)
        ->and($generation->actual_charge_nanos)->toBe(381_887_500)
        ->and($generation->image_url)->not->toBeNull()
        ->and($generation->completed_at->toIso8601String())->toBe('2026-11-04T14:03:29+00:00')
        ->and($this->user->fresh()->balance_nanos)->toBe(10_000_000_000 - 381_887_500);
});

it('never records the same event twice', function () {
    $event = webhookEvent($this->generation, 'succeeded', 'evt_dup');

    sendWebhook($this, $event)->assertOk()->assertJson(['outcome' => 'applied']);
    sendWebhook($this, $event)->assertOk()->assertJson(['outcome' => 'duplicate']);

    expect(WebhookEvent::count())->toBe(1)
        ->and(WebhookEvent::sole()->duplicate_deliveries)->toBe(1)
        ->and(CreditTransaction::where('type', 'charge')->count())->toBe(1)
        ->and($this->user->fresh()->balance_nanos)->toBe(10_000_000_000 - 381_887_500);
});

it('charges once even if two different succeeded events arrive', function () {
    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_a'))->assertOk();
    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_b'))->assertOk()->assertJson(['outcome' => 'ignored_stale']);

    expect(CreditTransaction::where('type', 'charge')->count())->toBe(1);
});

it('never moves a generation backwards when events arrive out of order', function () {
    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_2'))->assertOk();
    sendWebhook($this, webhookEvent($this->generation, 'processing', 'evt_1'))->assertOk()->assertJson(['outcome' => 'ignored_stale']);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('walks forward through processing to succeeded', function () {
    sendWebhook($this, webhookEvent($this->generation, 'processing', 'evt_1'))->assertOk();
    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Processing);

    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_2'))->assertOk();
    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('makes failed generations free and releases the hold', function () {
    sendWebhook($this, webhookEvent($this->generation, 'failed', 'evt_1'))->assertOk();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->actual_charge_nanos)->toBe(0)
        ->and($generation->actual_provider_nanos)->toBe(0)
        ->and($generation->error_code)->toBe('generation_failed')
        ->and($this->user->fresh()->balance_nanos)->toBe(10_000_000_000);
});

it('does not resurrect a failed generation from a late success for a different reason', function () {
    // Pixelforge confirmed it (submitted_at set), then it failed; a stray success must not bill it.
    $this->generation->forceFill(['submitted_at' => now()])->save();

    sendWebhook($this, webhookEvent($this->generation, 'failed', 'evt_1'))->assertOk();
    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_2'))->assertOk();

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($this->user->fresh()->balance_nanos)->toBe(10_000_000_000);
});

it('handles a webhook that arrives before the create request returns', function () {
    // Still pending, and we don't know Pixelforge's id yet: we match on our id in metadata.
    expect($this->generation->provider_id)->toBeNull();

    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_1', ['id' => 'gen_EARLY']))->assertOk();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_id)->toBe('gen_EARLY');
});

it('acknowledges ping events', function () {
    sendWebhook($this, ['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => ['object' => []]])
        ->assertOk()->assertJson(['outcome' => 'ping']);
});

it('acknowledges events for generations it does not know', function () {
    $event = webhookEvent($this->generation, 'succeeded', 'evt_x', ['id' => 'gen_OTHER', 'metadata' => []]);

    sendWebhook($this, $event)->assertOk()->assertJson(['outcome' => 'unmatched']);
});

it('keeps the balance equal to the sum of the ledger', function () {
    sendWebhook($this, webhookEvent($this->generation, 'succeeded', 'evt_1'))->assertOk();

    expect($this->user->fresh()->balance_nanos)->toBe(
        10_000_000_000 + (int) CreditTransaction::where('user_id', $this->user->id)->sum('amount_nanos')
    );
});

it('clears the waiting-for-capacity error once Pixelforge accepts the generation', function () {
    $this->generation->forceFill(['error_code' => 'concurrency_limit_exceeded', 'error_message' => 'Too many'])->save();

    sendWebhook($this, webhookEvent($this->generation, 'processing', 'evt_1'))->assertOk();

    expect($this->generation->fresh()->error_code)->toBeNull();
});
