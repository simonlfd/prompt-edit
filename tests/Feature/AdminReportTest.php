<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('is only for admins', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.report'))->assertForbidden();
});

it('reports spend, provider cost and profit per completed UTC day and checks it against Pixelforge', function () {
    $this->travelTo('2026-11-04 20:00:00');
    $generation = pendingGeneration();
    sendWebhook($this, webhookEvent($generation, 'succeeded', 'evt_1'))->assertOk();
    $failed = pendingGeneration($generation->user);
    sendWebhook($this, webhookEvent($failed, 'failed', 'evt_2'))->assertOk();

    Http::fake([PF.'/usage*' => Http::response([
        'object' => 'usage',
        'data' => [
            ['date' => '2026-11-03', 'generations' => 0, 'cost_usd' => '0.000000000'],
            ['date' => '2026-11-04', 'generations' => 1, 'cost_usd' => '0.305510000'],
        ],
        'total' => ['generations' => 1, 'cost_usd' => '0.305510000'],
    ])]);

    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.report', ['start_date' => '2026-11-03', 'end_date' => '2026-11-04']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/SpendReport')
            ->where('report.days.0.date', '2026-11-04')
            ->where('report.days.0.generations', 1)
            ->where('report.days.0.charged', '0.381887500')
            ->where('report.days.0.provider_cost', '0.305510000')
            ->where('report.days.0.profit', '0.076377500')
            ->where('report.days.0.matches_pixelforge', true)
            ->where('report.days.1.matches_pixelforge', true)
            ->where('report.users.0.user.id', $generation->user_id)
            ->where('report.total.matches_pixelforge', true));
});
