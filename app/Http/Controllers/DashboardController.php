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

        // 今回いちばん検証したい仮説「睡眠 × メンタル余裕」を1枚のカードで出す
        $sleepMental = collect($this->analytics->correlations($user, $from, $to))
            ->firstWhere('key', 'sleep_hours_mental_capacity');

        return view('dashboard', [
            'series' => $series,
            'todayLog' => $todayLog,
            'recentStress' => $recentStress,
            'topHabits' => $topHabits,
            'sleepMental' => $sleepMental,
            // 記録の継続が分析精度への一番の投資（report-202609.md §6）。
            // 入力項目は増やさず、途切れていることだけ知らせる。
            'coverage' => $this->analytics->coverage($user, $from, $to),
        ]);
    }
}
