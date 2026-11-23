<?php

use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Queue::fake());

function succeededGeneration(?User $user = null, array $attributes = []): Generation
{
    $generation = pendingGeneration($user, $attributes);
    $generation->forceFill([
        'status' => GenerationStatus::Succeeded,
        'actual_provider_nanos' => 1000,
        'actual_charge_nanos' => 1250,
        'image_url' => 'https://pixelforge.test/images/x.png?signature=abc',
        'image_url_expires_at' => now()->addHours(20),
        'completed_at' => now(),
    ])->save();

    return $generation;
}

it('lets owners favourite and unfavourite generations', function () {
    $generation = succeededGeneration();

    $this->actingAs($generation->user)->put(route('generations.favourite', $generation), ['favourite' => true])->assertRedirect();
    expect($generation->fresh()->is_favourite)->toBeTrue();

    $this->actingAs($generation->user)->put(route('generations.favourite', $generation), ['favourite' => false]);
    expect($generation->fresh()->is_favourite)->toBeFalse();
});

it('hides other people’s generations', function () {
    $generation = succeededGeneration();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->put(route('generations.favourite', $generation), ['favourite' => true])->assertNotFound();
    $this->actingAs($stranger)->get(route('generations.download', $generation))->assertNotFound();
});

it('downloads the PNG with a filename based on the prompt', function () {
    $generation = succeededGeneration(attributes: ['prompt' => 'A lighthouse at dawn [succeed]']);
    Http::fake(['pixelforge.test/images/*' => Http::response(PNG, 200, ['Content-Type' => 'image/png'])]);

    $response = $this->actingAs($generation->user)->get(route('generations.download', $generation));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->getContent())->toBe(PNG)
        ->and($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="a-lighthouse-at-dawn-'.strtolower(substr($generation->id, -4)).'.png"');
});

it('re-signs the image URL when the stored one is rejected', function () {
    $generation = succeededGeneration();
    $generation->forceFill(['provider_id' => 'gen_1'])->save();
    Http::fake([
        'pixelforge.test/images/x.png*' => Http::response('', 403),
        PF.'/generations/gen_1' => Http::response(providerGeneration($generation, 'succeeded', ['image_url' => 'https://pixelforge.test/images/fresh.png'])),
        'pixelforge.test/images/fresh.png' => Http::response(PNG.'FRESH', 200),
    ]);

    expect($this->actingAs($generation->user)->get(route('generations.download', $generation))->getContent())->toBe(PNG.'FRESH');
});

it('explains when an image has expired instead of serving a broken file', function () {
    $generation = succeededGeneration();
    $generation->forceFill(['image_url_expires_at' => now()->subMinute()])->save();
    Http::fake();

    $this->actingAs($generation->user)->get(route('generations.download', $generation))->assertRedirect();
    Http::assertNothingSent();
});

it('filters and sorts the gallery', function () {
    $user = userWithCredits();
    $cheap = succeededGeneration($user);
    $pricey = succeededGeneration($user);
    $pricey->forceFill(['actual_charge_nanos' => 99_000, 'is_favourite' => true])->save();
    $pending = pendingGeneration($user);

    $this->actingAs($user)->get(route('gallery'))
        ->assertInertia(fn (Assert $page) => $page->component('Gallery')
            ->has('generations.data', 3)
            ->where('counts', ['all' => 3, 'favourites' => 1, 'ready' => 2, 'failed' => 0]));

    $this->actingAs($user)->get(route('gallery', ['filter' => 'favourites']))
        ->assertInertia(fn (Assert $page) => $page->has('generations.data', 1)->where('generations.data.0.id', $pricey->id));

    $this->actingAs($user)->get(route('gallery', ['filter' => 'ready', 'sort' => 'cost']))
        ->assertInertia(fn (Assert $page) => $page->where('generations.data.0.id', $pricey->id)->where('generations.data.1.id', $cheap->id));

    $this->actingAs($user)->get(route('gallery', ['sort' => 'nonsense']))->assertSessionHasErrors('sort');
});

it('prefills the studio form when reusing a generation', function () {
    $generation = succeededGeneration();

    $this->actingAs($generation->user)->get(route('dashboard', ['reuse' => $generation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('reuse.id', $generation->id)->where('reuse.prompt', $generation->prompt));
});
