<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckItemRequest;
use App\Http\Requests\UpdateCheckItemRequest;
use App\Models\CheckItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckItemController extends Controller
{
    public function index(Request $request): View
    {
        $checkItems = $request->user()->checkItems()
            ->orderBy('sort_order')
            ->get();

        return view('check_items.index', compact('checkItems'));
    }

    public function store(StoreCheckItemRequest $request): RedirectResponse
    {
        $request->user()->checkItems()->create([
            'name' => $request->validated('name'),
            'sort_order' => (int) $request->user()->checkItems()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('check-items.index')->with('status', '項目を追加しました。');
    }

    public function update(UpdateCheckItemRequest $request, CheckItem $checkItem): RedirectResponse
    {
        $this->authorize('update', $checkItem);

        $checkItem->update([
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('check-items.index')->with('status', '項目を更新しました。');
    }

    public function destroy(CheckItem $checkItem): RedirectResponse
    {
        $this->authorize('delete', $checkItem);

        // 過去ログ保持のため物理削除せず無効化する
        $checkItem->update(['is_active' => false]);

        return redirect()->route('check-items.index')->with('status', '項目を無効化しました。');
    }

    /**
     * 並び替え（自分の項目のみ反映）。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['array'],
            'order.*' => ['integer'],
        ]);

        $ownedIds = $request->user()->checkItems()->pluck('id')->all();

        foreach ($validated['order'] ?? [] as $position => $id) {
            if (! in_array((int) $id, $ownedIds, true)) {
                continue; // 他人の項目は無視
            }
            CheckItem::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return redirect()->route('check-items.index')->with('status', '並び順を更新しました。');
    }
}
