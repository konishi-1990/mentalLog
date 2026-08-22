<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $people = $request->user()->people()
            ->orderBy('sort_order')
            ->get();

        return view('people.index', compact('people'));
    }

    public function store(StorePersonRequest $request): RedirectResponse
    {
        $request->user()->people()->create([
            'name' => $request->validated('name'),
            'sort_order' => (int) $request->user()->people()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('people.index')->with('status', '相手タグを追加しました。');
    }

    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $person->update([
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('people.index')->with('status', '相手タグを更新しました。');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $this->authorize('delete', $person);

        // 過去ログ保持のため物理削除せず無効化する
        $person->update(['is_active' => false]);

        return redirect()->route('people.index')->with('status', '相手タグを無効化しました。');
    }

    /**
     * 並び替え（自分の相手タグのみ反映）。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['array'],
            'order.*' => ['integer'],
        ]);

        $ownedIds = $request->user()->people()->pluck('id')->all();

        foreach ($validated['order'] ?? [] as $position => $id) {
            if (! in_array((int) $id, $ownedIds, true)) {
                continue; // 他人の相手タグは無視
            }
            Person::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return redirect()->route('people.index')->with('status', '並び順を更新しました。');
    }
}
