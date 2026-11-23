<?php

use App\Billing\CreditLedger;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Reports\SpendReport;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** A succeeded generation without going through Pixelforge, for volume tests. */
function seedSucceeded(User $user, int $chargeNanos, string $completedAt = 'now'): Generation
{
    return Generation::create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Succeeded,
        'prompt' => 'p', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => [],
        'rates' => pricingResponse()['rates'],
        'estimated_provider_nanos' => 1, 'estimated_charge_nanos' => 1, 'max_charge_nanos' => 1,
        'actual_provider_nanos' => intdiv($chargeNanos * 4, 5), 'actual_charge_nanos' => $chargeNanos,
        'completed_at' => now()->parse($completedAt),
    ]);
}

it('pages the spend report’s users, biggest spender first, with totals over everyone', function () {
    Http::fake([PF.'/usage*' => Http::response(['object' => 'usage', 'data' => [], 'total' => []])]);
    $users = User::factory()->count(SpendReport::USERS_PER_PAGE + 5)->create();
    $users->each(fn (User $u, int $i) => seedSucceeded($u, ($i + 1) * 1000));
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.report'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.users.data', SpendReport::USERS_PER_PAGE)
            ->where('report.users.total', 30)
            ->where('report.users.data.0.user.id', $users->last()->id) // spent the most
            ->where('report.total.generations', 30));

    $this->actingAs($admin)->get(route('admin.report', ['users_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.users.data', 5)
            ->where('report.users.data.4.user.id', $users->first()->id)
            ->where('report.total.generations', 30)); // totals aren't per page
});

it('sums per day in SQL on the UTC completion date', function () {
    Http::fake([PF.'/usage*' => Http::response(['object' => 'usage', 'data' => [], 'total' => []])]);
    $user = User::factory()->create();
    seedSucceeded($user, 1000, '2026-11-20 23:59:59');
    seedSucceeded($user, 2000, '2026-11-21 00:00:00');
    seedSucceeded($user, 3000, '2026-11-21 12:00:00');

    $report = app(SpendReport::class)->build(now()->parse('2026-11-20'), now()->parse('2026-11-21'));

    expect($report['days'][0])->toMatchArray(['date' => '2026-11-21', 'generations' => 2, 'charged' => '0.000005000'])
        ->and($report['days'][1])->toMatchArray(['date' => '2026-11-20', 'generations' => 1, 'charged' => '0.000001000']);
});

it('pages the billing ledger and keeps other users out', function () {
    $user = User::factory()->create();
    $ledger = app(CreditLedger::class);
    foreach (range(1, 35) as $i) {
        $ledger->purchase($user, 1_000_000_000, "Top-up {$i}");
    }

    $this->actingAs($user)->get(route('billing', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 5)
            ->where('entries.total', 35)
            ->where('entries.current_page', 2));
});

it('pages the gallery while keeping the filter and sort', function () {
    $user = User::factory()->create();
    foreach (range(1, 30) as $i) {
        seedSucceeded($user, $i * 1000);
    }

    $this->actingAs($user)->get(route('gallery', ['filter' => 'ready', 'sort' => 'cost', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations.data', 6)
            ->where('generations.meta.total', 30)
            ->where('generations.data.5.actual_charge', '0.000001000')); // cheapest last
});
