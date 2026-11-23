<?php

namespace App\Console\Commands;

use App\Reports\SpendReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Hourly: compare our recorded provider costs with Pixelforge's GET /v1/usage for the last few days and
 * log any day that doesn't match. A generation that has finished at Pixelforge but whose webhook we
 * haven't processed yet can cause a brief mismatch; one that persists is a bug worth a look.
 */
#[AsCommand(name: 'usage:check', description: 'Reconcile recorded provider costs against Pixelforge usage')]
class CheckUsage extends Command
{
    public const CACHE_KEY = 'usage_check.last';

    public function handle(SpendReport $report): int
    {
        $end = CarbonImmutable::now('UTC')->startOfDay();
        $result = $report->build($end->subDays(2), $end);

        if (! $result['pixelforge_available']) {
            $this->warn('Pixelforge usage is unavailable; skipped.');

            return self::SUCCESS;
        }

        $mismatches = array_values(array_filter($result['days'], fn (array $day) => $day['matches_pixelforge'] === false));

        Cache::forever(self::CACHE_KEY, [
            'checked_at' => now()->toIso8601String(),
            'days' => count($result['days']),
            'mismatches' => array_map(fn ($d) => [
                'date' => $d['date'],
                'ours' => $d['provider_cost'],
                'pixelforge' => $d['pixelforge']['cost'],
            ], $mismatches),
        ]);

        foreach ($mismatches as $day) {
            Log::warning('Provider cost does not match Pixelforge usage.', [
                'date' => $day['date'],
                'ours' => $day['provider_cost'],
                'pixelforge' => $day['pixelforge'],
            ]);
        }

        $this->info($mismatches ? count($mismatches).' day(s) do not match.' : 'All days match Pixelforge.');

        return self::SUCCESS;
    }
}
