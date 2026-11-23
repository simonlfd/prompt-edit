<?php

use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('polls generations whose webhooks are overdue and applies the result', function () {
    $generation = pendingGeneration();
    $generation->forceFill(['status' => GenerationStatus::Queued, 'provider_id' => 'gen_1', 'submitted_at' => now()])->save();
    $this->travel(2)->minutes();
    Http::fake([PF.'/generations/gen_1' => Http::response(providerGeneration($generation, 'succeeded'))]);

    $this->artisan('generations:reconcile')->assertSuccessful();

    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->fresh()->actual_charge_nanos)->toBe(381_887_500);
});

it('leaves recently updated generations to their webhooks', function () {
    $generation = pendingGeneration();
    $generation->forceFill(['status' => GenerationStatus::Queued, 'provider_id' => 'gen_1'])->save();
    Http::fake();

    $this->artisan('generations:reconcile')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'gen_1'));
});

it('re-dispatches pending generations whose submit job was lost', function () {
    $generation = pendingGeneration();
    $this->travel(3)->minutes();
    Queue::fake();

    $this->artisan('generations:reconcile')->assertSuccessful();

    Queue::assertPushed(SubmitGeneration::class, fn ($job) => $job->generationId === $generation->id);
});
