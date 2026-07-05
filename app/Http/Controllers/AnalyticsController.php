<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $from = $validated['from'] ?? now()->subDays(29)->format('Y-m-d');
        $to = $validated['to'] ?? now()->format('Y-m-d');

        return view('analytics.index', [
            'from' => $from,
            'to' => $to,
            'series' => $this->analytics->timeSeries($user, $from, $to),
            'checkItemFreq' => $this->analytics->checkItemFrequency($user, $from, $to),
            'thoughtFreq' => $this->analytics->checklistFrequency($user, $from, $to, 'thought_habit'),
            'bodyFreq' => $this->analytics->checklistFrequency($user, $from, $to, 'body_reaction'),
            'recovery' => $this->analytics->recoveryPattern($user, $from, $to),
        ]);
    }
}
