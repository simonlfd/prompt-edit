<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CheckUsage;
use App\Generations\ErrorMessages;
use App\Generations\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Reports\SpendReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/** What the integration is doing: webhook deliveries, retries, stuck work and cost reconciliation. */
class OperationsController extends Controller
{
    public function __invoke(SpendReport $report): Response
    {
        $since = now()->subDay();
        $recent = WebhookEvent::where('created_at', '>=', $since);

        $today = CarbonImmutable::now('UTC')->startOfDay();
        $reconciliation = $report->build($today->subDays(6), $today);

        return Inertia::render('admin/Operations', [
            'webhooks' => [
                'received' => (clone $recent)->count(),
                'duplicates_ignored' => (int) (clone $recent)->sum('duplicate_deliveries'),
                'stale_ignored' => (clone $recent)->where('outcome', 'ignored_stale')->count(),
                'unmatched' => (clone $recent)->where('outcome', 'unmatched')->count(),
                'retried_deliveries' => (clone $recent)->where('delivery_attempt', '>', 1)->count(),
                'last_received_at' => WebhookEvent::max('created_at'),
            ],
            'generations' => [
                'in_flight' => Generation::inFlight()->count(),
                'waiting_for_capacity' => Generation::where('status', GenerationStatus::Pending)->whereNotNull('next_attempt_at')->count(),
                'stuck' => Generation::inFlight()->where('updated_at', '<', now()->subMinutes(5))->count(),
                'images_not_saved' => Generation::where('status', GenerationStatus::Succeeded)->whereNull('image_path')->count(),
                'failures' => Generation::where('status', GenerationStatus::Failed)
                    ->where('completed_at', '>=', $since)
                    ->selectRaw('error_code, count(*) as count')
                    ->groupBy('error_code')
                    ->orderByDesc('count')
                    ->get()
                    ->map(fn ($row) => [
                        'code' => $row->error_code,
                        'message' => ErrorMessages::for($row->error_code),
                        'count' => (int) $row->count,
                    ]),
            ],
            'reconciliation' => [
                'available' => $reconciliation['pixelforge_available'],
                'days' => $reconciliation['days'],
                'last_check' => Cache::get(CheckUsage::CACHE_KEY),
            ],
            'events' => WebhookEvent::query()
                ->leftJoin('generations', 'generations.id', '=', 'webhook_events.generation_id')
                ->latest('webhook_events.id')
                ->paginate(25, [
                    'webhook_events.id', 'event_id', 'type', 'outcome', 'delivery_attempt', 'duplicate_deliveries',
                    'webhook_events.created_at', 'generations.prompt',
                    'webhook_events.provider_generation_id',
                ]),
        ]);
    }
}
