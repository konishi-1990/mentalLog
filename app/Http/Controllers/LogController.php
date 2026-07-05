<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogFilterRequest;
use App\Http\Requests\StoreLogRequest;
use App\Http\Requests\UpdateLogRequest;
use App\Models\ChecklistCategory;
use App\Models\Log;
use App\Models\User;
use App\Services\LogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function __construct(private readonly LogService $logService)
    {
    }

    /**
     * 一覧＋絞り込み。一般ユーザは自分のみ、管理者は全件（ユーザ絞り込み可）。
     */
    public function index(LogFilterRequest $request): View
    {
        $filters = $request->validated();
        $isAdmin = $request->user()->isAdmin();

        $query = $isAdmin
            ? Log::query()->with('user')
            : $request->user()->logs();

        // 管理者：ユーザ絞り込み
        if ($isAdmin && ! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // 日付範囲
        $query->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v));
        $query->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v));

        // 各数値の min/max
        $ranges = [
            'stress' => ['stress_min', 'stress_max'],
            'stamina' => ['stamina_min', 'stamina_max'],
            'mental_capacity' => ['mental_min', 'mental_max'],
        ];
        foreach ($ranges as $column => [$minKey, $maxKey]) {
            $query->when($filters[$minKey] ?? null, fn ($q, $v) => $q->where($column, '>=', $v));
            $query->when($filters[$maxKey] ?? null, fn ($q, $v) => $q->where($column, '<=', $v));
        }

        $logs = $query->orderByDesc('logged_on')->paginate(20)->withQueryString();
        $users = $isAdmin ? User::orderBy('name')->get(['id', 'name']) : collect();

        return view('logs.index', compact('logs', 'users', 'filters', 'isAdmin'));
    }

    public function create(Request $request): View
    {
        return view('logs.create', $this->formData($request));
    }

    public function store(StoreLogRequest $request): RedirectResponse
    {
        $log = $this->logService->upsertDailyLog($request->user(), $request->validated());

        return redirect()
            ->route('logs.show', $log)
            ->with('status', 'ログを保存しました。');
    }

    public function show(Log $log): View
    {
        $this->authorize('view', $log);

        $log->load(['checkItemValues.checkItem', 'checklistSelections.option.category']);

        return view('logs.show', compact('log'));
    }

    public function edit(Request $request, Log $log): View
    {
        $this->authorize('update', $log);

        $log->load(['checkItemValues', 'checklistSelections']);

        return view('logs.edit', array_merge($this->formData($request), [
            'log' => $log,
            'checkValues' => $log->checkItemValues->keyBy('check_item_id'),
            'selectedOptionIds' => $log->checklistSelections->pluck('checklist_option_id')->all(),
            'selectionDetails' => $log->checklistSelections->pluck('detail_text', 'checklist_option_id'),
        ]));
    }

    public function update(UpdateLogRequest $request, Log $log): RedirectResponse
    {
        $this->authorize('update', $log);

        $this->logService->upsertDailyLog($log->user, $request->validated());

        return redirect()
            ->route('logs.show', $log)
            ->with('status', 'ログを更新しました。');
    }

    public function destroy(Log $log): RedirectResponse
    {
        $this->authorize('delete', $log);

        $log->delete();

        return redirect()
            ->route('logs.index')
            ->with('status', 'ログを削除しました。');
    }

    /**
     * 入力フォーム用のマスタデータ。
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        return [
            'checkItems' => $request->user()->checkItems()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'categories' => ChecklistCategory::with(['options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(),
        ];
    }
}
