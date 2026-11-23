<?php

namespace App\Reports;

use App\Billing\Money;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;

/**
 * Credit spend, provider cost and profit per UTC day and per user.
 *
 * Days are the UTC day a generation *completed*, because that's how Pixelforge's GET /v1/usage buckets
 * costs. Only succeeded generations count (failed ones cost nothing on either side). Sums are done in SQL
 * over exact integer nanodollars, so they scale with the data and our per-day provider cost should equal
 * Pixelforge's cost_usd to the last digit.
 */
class SpendReport
{
    public const USERS_PER_PAGE = 25;

    public function __construct(private readonly PixelforgeClient $client) {}

    /** @return array<string, mixed> */
    public function build(CarbonImmutable $start, CarbonImmutable $end, int $usersPage = 1): array
    {
        $byDay = $this->succeededBetween($start, $end)
            ->toBase()
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as generations, SUM(actual_charge_nanos) as charged, SUM(actual_provider_nanos) as provider')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

        $usage = $this->providerUsage($start, $end);

        $days = [];
        for ($day = $end; $day->gte($start); $day = $day->subDay()) {
            $date = $day->toDateString();
            $row = $byDay->get($date);
            $days[] = $this->present([
                'date' => $date,
                ...$this->totals($row),
                'pixelforge' => $usage[$date] ?? null,
            ]);
        }

        $total = $this->totals($this->succeededBetween($start, $end)
            ->toBase()
            ->selectRaw('COUNT(*) as generations, SUM(actual_charge_nanos) as charged, SUM(actual_provider_nanos) as provider')
            ->first());
        $pixelforgeTotal = $usage === null ? null : [
            'generations' => array_sum(array_column($usage, 'generations')),
            'cost' => Money::toDecimal(array_sum(array_map(fn ($d) => Money::nanosFromDecimal($d['cost']), $usage))),
        ];

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'users' => $this->byUser($start, $end, $usersPage),
            'top_users' => $this->topUsers($start, $end, 8),
            'total' => $this->present([...$total, 'pixelforge' => $pixelforgeTotal]),
            'pixelforge_available' => $usage !== null,
        ];
    }

    /**
     * Biggest spenders first, a page at a time.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function byUser(CarbonImmutable $start, CarbonImmutable $end, int $page): LengthAwarePaginator
    {
        return $this->userTotals($start, $end)
            ->paginate(self::USERS_PER_PAGE, pageName: 'users_page', page: $page)
            ->withQueryString()
            ->through(fn ($row) => $this->presentUser($row));
    }

    /**
     * The biggest spenders, for the chart.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topUsers(CarbonImmutable $start, CarbonImmutable $end, int $limit): array
    {
        return $this->userTotals($start, $end)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->presentUser($row))
            ->values()
            ->all();
    }

    private function userTotals(CarbonImmutable $start, CarbonImmutable $end): QueryBuilder
    {
        return $this->succeededBetween($start, $end)
            ->toBase()
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->selectRaw('users.id, users.name, users.email, COUNT(*) as generations, SUM(actual_charge_nanos) as charged, SUM(actual_provider_nanos) as provider')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByRaw('SUM(actual_charge_nanos) DESC')
            ->orderBy('users.id');
    }

    /** @return array<string, mixed> */
    private function presentUser(\stdClass $row): array
    {
        return $this->present([
            'user' => ['id' => (int) $row->id, 'name' => $row->name, 'email' => $row->email],
            ...$this->totals($row),
        ]);
    }

    /** @return Builder<Generation> */
    private function succeededBetween(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()]);
    }

    /** @return array{generations: int, charged_nanos: int, provider_nanos: int} */
    private function totals(?object $row): array
    {
        return [
            'generations' => (int) ($row->generations ?? 0),
            'charged_nanos' => (int) ($row->charged ?? 0),
            'provider_nanos' => (int) ($row->provider ?? 0),
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
