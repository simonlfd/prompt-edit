<?php

use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Queue::fake());

it('shows the studio with the user’s generations and balance', function () {
    $generation = pendingGeneration();
    $other = pendingGeneration(); // someone else's

    $this->actingAs($generation->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Studio')
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $generation->id)
            ->where('generations.data.0.status', 'pending')
            ->where('options.sizes', ['1024x1024', '1536x1024', '1024x1536'])
            ->where('balance', '9.532656250'));
});

it('creates a generation, holds its worst-case cost and queues the submission', function () {
    $user = userWithCredits(1);

    $this->actingAs($user)->post(route('generations.store'), [
        'prompt' => '  A red fox in the snow  ',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => ['https://example.com/a.png', ''],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $generation = Generation::sole();
    expect($generation->prompt)->toBe('A red fox in the snow')
        ->and($generation->reference_images)->toBe(['https://example.com/a.png'])
        ->and($generation->status)->toBe(GenerationStatus::Pending)
        ->and($user->fresh()->balance_nanos)->toBe(1_000_000_000 - $generation->max_charge_nanos)
        ->and(CreditTransaction::where('type', 'hold')->sole()->amount_nanos)->toBe(-$generation->max_charge_nanos);

    Queue::assertPushed(SubmitGeneration::class);
});

it('blocks generating when the user can’t afford the worst case', function () {
    $user = User::factory()->create(['balance_nanos' => 100_000]);

    $this->actingAs($user)->post(route('generations.store'), [
        'prompt' => 'A red fox', 'size' => '1536x1024', 'quality' => 'high',
    ])->assertSessionHasErrors('credits');

    expect(Generation::count())->toBe(0)
        ->and($user->fresh()->balance_nanos)->toBe(100_000);
    Queue::assertNothingPushed();
});

it('validates input against Pixelforge’s limits', function () {
    $this->actingAs(userWithCredits())->post(route('generations.store'), [
        'prompt' => str_repeat('a', 4001),
        'size' => '512x512',
        'quality' => 'ultra',
        'reference_images' => ['ftp://x', 'https://a', 'https://b', 'https://c', 'https://d'],
    ])->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images', 'reference_images.0']);
});

it('returns a live estimate from the same calculator used for charging', function () {
    $this->actingAs(userWithCredits())
        ->getJson(route('generations.estimate', [
            'prompt' => str_repeat('a', 400), 'size' => '1536x1024', 'quality' => 'high', 'reference_images' => 2,
        ]))
        ->assertOk()
        ->assertJson([
            'max_charge' => '0.467343750', // 0.373875 × 1.25
            'tokens' => ['input_text_tokens' => 100, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 4500],
        ]);
});

it('adds bought credits to the balance and the ledger', function () {
    $user = userWithCredits(0);

    $this->actingAs($user)->post(route('credits.store'), ['amount' => 10])->assertRedirect();

    expect($user->fresh()->balance_nanos)->toBe(10_000_000_000)
        ->and(CreditTransaction::where('type', 'purchase')->sole()->amount_nanos)->toBe(10_000_000_000);
});

it('only sells the advertised credit packs', function () {
    $this->actingAs(userWithCredits(0))->post(route('credits.store'), ['amount' => 1_000_000])->assertSessionHasErrors('amount');
});

it('gives new users welcome credit', function () {
    $this->post(route('register.store'), [
        'name' => 'New User', 'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'new@example.com')->sole()->balance_nanos)->toBe(2_000_000_000);
});
