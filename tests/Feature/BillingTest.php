<?php

use App\Billing\Money;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a user their own ledger with totals', function () {
    Queue::fake();
    $done = pendingGeneration();
    $user = $done->user;
    sendWebhook($this, webhookEvent($done, 'succeeded', 'evt_1'))->assertOk();
    $inFlight = pendingGeneration($user);
    pendingGeneration(); // someone else's, must not appear

    $this->actingAs($user)->get(route('billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing')
            ->where('summary.spent', '0.381887500')
            ->where('summary.reserved', Money::toDecimal($inFlight->max_charge_nanos))
            ->where('summary.images_paid_for', 1)
            ->has('entries.data', 4) // hold, release, charge for one; hold for the other
            ->where('entries.data.0.type', 'hold')
            ->where('entries.data.0.generation.id', $inFlight->id));
});
