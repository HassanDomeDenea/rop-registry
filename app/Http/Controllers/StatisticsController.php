<?php

namespace App\Http\Controllers;

use App\Services\RegistryStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    /**
     * Show the registry statistics, optionally limited to a date range.
     */
    public function __invoke(Request $request, RegistryStatistics $statistics): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'unverified' => ['nullable', 'boolean'],
        ]);

        $from = isset($filters['from']) ? Date::parse($filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? Date::parse($filters['to'])->endOfDay() : null;

        $includeUnverified = $request->boolean('unverified');

        return Inertia::render('statistics/Index', [
            'filters' => ['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d'), 'unverified' => $includeUnverified],
            'statistics' => $statistics->calculate($from, $to, $includeUnverified),
        ]);
    }
}
