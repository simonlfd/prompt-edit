<?php

namespace App\Reports;

use App\Billing\Money;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Credit spend, provider cost and profit per UTC day and per user.
 *
 * Days are the UTC day a generation *completed*, because that's how Pixelforge's GET /v1/usage buckets
 * costs. Only succeeded generations count (failed ones cost nothing on either side). Sums stay in exact
 * nanodollars, so our per-day provider cost should equal Pixelforge's cost_usd to the last digit.
 */
class SpendReport
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /** @return array<string, mixed> */
    public function build(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $generations = Generation::query()
            ->with('user:id,name,email')
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
            ->get(['id', 'user_id', 'completed_at', 'actual_provider_nanos', 'actual_charge_nanos']);

        $usage = $this->providerUsage($start, $end);

        $days = [];
        for ($day = $end; $day->gte($start); $day = $day->subDay()) {
            $date = $day->toDateString();
            $rows = $generations->filter(fn (Generation $g) => $g->completed_at->utc()->toDateString() === $date);
            $days[] = [
                'date' => $date,
                ...$this->totals($rows),
                'pixelforge' => $usage[$date] ?? null,
            ];
        }

        $users = $generations->groupBy('user_id')->map(fn ($rows) => [
            'user' => ['id' => $rows->first()->user->id, 'name' => $rows->first()->user->name, 'email' => $rows->first()->user->email],
            ...$this->totals($rows),
        ])->sortByDesc('charged_nanos')->values()->all();

        $total = $this->totals($generations);
        $pixelforgeTotal = $usage === null ? null : array_sum(array_map(fn ($d) => Money::nanosFromDecimal($d['cost']), $usage));

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => array_map(fn ($d) => $this->present($d), $days),
            'users' => array_map(fn ($u) => $this->present($u), $users),
            'total' => $this->present([
                ...$total,
                'pixelforge' => $pixelforgeTotal === null ? null : [
                    'generations' => array_sum(array_column($usage, 'generations')),
                    'cost' => Money::toDecimal($pixelforgeTotal),
                ],
            ]),
            'pixelforge_available' => $usage !== null,
        ];
    }

    /**
     * @param  Collection<int, Generation>  $rows
     * @return array{generations: int, charged_nanos: int, provider_nanos: int}
     */
    private function totals(Collection $rows): array
    {
        return [
            'generations' => $rows->count(),
            'charged_nanos' => (int) $rows->sum('actual_charge_nanos'),
            'provider_nanos' => (int) $rows->sum('actual_provider_nanos'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $pixelforge = $row['pixelforge'] ?? null;
        $providerCost = Money::toDecimal($row['provider_nanos']);

        return [
            ...array_diff_key($row, array_flip(['charged_nanos', 'provider_nanos'])),
            'charged' => Money::toDecimal($row['charged_nanos']),
            'provider_cost' => $providerCost,
            'profit' => Money::toDecimal($row['charged_nanos'] - $row['provider_nanos']),
            'pixelforge' => $pixelforge,
            'matches_pixelforge' => $pixelforge === null ? null
                : $pixelforge['cost'] === $providerCost && $pixelforge['generations'] === $row['generations'],
        ];
    }

    /** @return array<string, array{generations: int, cost: string}>|null  Keyed by date; null if unavailable. */
    private function providerUsage(CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        try {
            $response = Cache::remember(
                "pixelforge.usage.{$start->toDateString()}.{$end->toDateString()}",
                now()->addSeconds(30),
                fn () => $this->client->usage($start->toDateString(), $end->toDateString()),
            );
        } catch (PixelforgeException) {
            return null;
        }

        $usage = [];
        foreach ($response['data'] as $day) {
            $usage[$day['date']] = [
                'generations' => (int) $day['generations'],
                // Normalise to 9 decimals so it compares exactly with our own figures.
                'cost' => Money::toDecimal(Money::nanosFromDecimal($day['cost_usd'])),
            ];
        }

        return $usage;
    }
}
