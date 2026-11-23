<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\SpendReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpendReportController extends Controller
{
    public function __invoke(Request $request, SpendReport $report): Response
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $end = isset($data['end_date']) ? CarbonImmutable::parse($data['end_date'], 'UTC') : CarbonImmutable::now('UTC');
        $start = isset($data['start_date']) ? CarbonImmutable::parse($data['start_date'], 'UTC') : $end->subDays(13);

        // Pixelforge's usage endpoint allows at most 92 days.
        if ($start->diffInDays($end) > 91) {
            $start = $end->subDays(91);
        }

        return Inertia::render('admin/SpendReport', [
            'report' => $report->build($start->startOfDay(), $end->startOfDay()),
        ]);
    }
}
