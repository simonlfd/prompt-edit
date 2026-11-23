<?php

namespace App\Reports;

use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;

/**
 * Hour-by-hour activity for the last 24 hours, for the operations charts. Bucketed in PHP from a narrow
 * select (two or three columns) so it works the same on SQLite, MySQL and Postgres.
 */
class OperationsTimeline
{
    public const HOURS = 24;

    /**
     * One count per hour, oldest first; index i is the hour in hours[i].
     *
     * @return array{hours: list<string>, generations: array<string, array<int, int>>, webhooks: array<string, array<int, int>>}
     */
    public function build(?CarbonImmutable $now = null): array
    {
        $end = ($now ?? CarbonImmutable::now('UTC'))->utc()->startOfHour();
        $start = $end->subHours(self::HOURS - 1);

        $hours = [];
        for ($h = $start; $h->lte($end); $h = $h->addHour()) {
            $hours[] = $h->toIso8601String();
        }
        $index = array_flip($hours);
        $empty = array_fill(0, self::HOURS, 0);

        $generations = ['succeeded' => $empty, 'failed' => $empty];
        Generation::query()
            ->whereIn('status', [GenerationStatus::Succeeded, GenerationStatus::Failed])
            ->where('completed_at', '>=', $start)
            ->toBase()
            ->get(['status', 'completed_at'])
            ->each(function ($row) use (&$generations, $index) {
                $slot = $index[CarbonImmutable::parse($row->completed_at, 'UTC')->startOfHour()->toIso8601String()] ?? null;
                if ($slot !== null) {
                    $generations[$row->status][$slot]++;
                }
            });

        $webhooks = ['applied' => $empty, 'stale' => $empty, 'duplicates' => $empty];
        WebhookEvent::query()
            ->where('created_at', '>=', $start)
            ->toBase()
            ->get(['outcome', 'duplicate_deliveries', 'created_at'])
            ->each(function ($row) use (&$webhooks, $index) {
                $slot = $index[CarbonImmutable::parse($row->created_at, 'UTC')->startOfHour()->toIso8601String()] ?? null;
                if ($slot === null) {
                    return;
                }
                if ($row->outcome === 'applied') {
                    $webhooks['applied'][$slot]++;
                } elseif ($row->outcome === 'ignored_stale') {
                    $webhooks['stale'][$slot]++;
                }
                // Re-deliveries are counted where the original landed; close enough at an hourly grain.
                $webhooks['duplicates'][$slot] += (int) $row->duplicate_deliveries;
            });

        return ['hours' => $hours, 'generations' => $generations, 'webhooks' => $webhooks];
    }
}
