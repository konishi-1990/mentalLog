<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChecklistOptionRequest;
use App\Http\Requests\Admin\UpdateChecklistOptionRequest;
use App\Models\ChecklistCategory;
use App\Models\ChecklistOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChecklistOptionController extends Controller
{
    public function index(): View
    {
        $categories = ChecklistCategory::with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('admin.checklist.index', compact('categories'));
    }

    public function store(StoreChecklistOptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['requires_text'] = $request->boolean('requires_text');
        $data['is_none'] = $request->boolean('is_none');
        $data['is_active'] = true;
        $data['sort_order'] = (int) ChecklistOption::where('category_id', $data['category_id'])->max('sort_order') + 1;

        ChecklistOption::create($data);

        return redirect()->route('admin.checklist.index')->with('status', '選択肢を追加しました。');
    }

    public function update(UpdateChecklistOptionRequest $request, ChecklistOption $checklist): RedirectResponse
    {
        $data = $request->validated();
        $data['requires_text'] = $request->boolean('requires_text');
        $data['is_none'] = $request->boolean('is_none');
        $data['is_active'] = $request->boolean('is_active');

        $checklist->update($data);

        return redirect()->route('admin.checklist.index')->with('status', '選択肢を更新しました。');
    }

    public function destroy(ChecklistOption $checklist): RedirectResponse
    {
        // マスタは物理削除せず無効化（過去ログ保持）
        $checklist->update(['is_active' => false]);

        return redirect()->route('admin.checklist.index')->with('status', '選択肢を無効化しました。');
    }
}
