<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $from = now()->subDays(13)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $series = $this->analytics->timeSeries($user, $from, $to);
        $todayLog = $user->logs()->whereDate('logged_on', $to)->first();

        $recentStress = $series->avg('stress');
        $topHabits = $this->analytics
            ->checklistFrequency($user, $from, $to, 'thought_habit')
            ->take(3);

        return view('dashboard', [
            'series' => $series,
            'todayLog' => $todayLog,
            'recentStress' => $recentStress,
            'topHabits' => $topHabits,
        ]);
    }
}
