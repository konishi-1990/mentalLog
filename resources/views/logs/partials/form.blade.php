@php
    /** @var \App\Models\Log|null $log */
    $log = $log ?? null;
    $checkValues = $checkValues ?? collect();
    $selectedOptionIds = old('checklist', $selectedOptionIds ?? []);
    $selectionDetails = $selectionDetails ?? collect();
    $selectionMeta = $selectionMeta ?? collect();
    $people = $people ?? collect();
    $selectedPersonIds = old('people', $selectedPersonIds ?? []);
    $personDetails = $personDetails ?? collect();

    $loggedOn = old('logged_on', $log?->logged_on?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $scores = [
        'stress' => ['label' => 'ストレス', 'default' => 5, 'hint' => '高いときつい'],
        'stamina' => ['label' => '体力', 'default' => 5, 'hint' => '高いと元気'],
        'mental_capacity' => ['label' => 'メンタル余裕', 'default' => 5, 'hint' => '高いと余裕あり'],
    ];

    // 「くわしく」内の任意項目。触らなければ送信されず NULL のまま保存される。
    $extraScores = [
        'sleep_quality' => ['label' => '睡眠の質', 'hint' => '高いとよく眠れた'],
        'carryover' => ['label' => '前日からの持ち越し感', 'hint' => '高いと引きずっている'],
        'controllability' => ['label' => 'コントロール可能度', 'hint' => '高いと自分で動かせた'],
    ];

    $sleepHours = old('sleep_hours', $log?->sleep_hours);
    $dayType = old('day_type', $log?->day_type);
    // 追加項目に入力済みの値があるか（あれば「くわしく」を開いた状態で表示する）
    $hasExtras = filled($sleepHours) || filled($dayType)
        || collect(array_keys($extraScores))->contains(fn ($k) => filled(old($k, $log?->{$k})));
@endphp

<div class="space-y-8">
    {{-- 対象日 --}}
    <section class="bg-white rounded-lg border border-gray-200 p-6">
        <label for="logged_on" class="block text-sm font-medium text-gray-700 mb-1">対象日</label>
        <input type="date" id="logged_on" name="logged_on" value="{{ $loggedOn }}"
               class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('logged_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </section>

    {{-- 数値 --}}
    <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-5">
        <h3 class="font-semibold text-gray-800">数値（0〜10）</h3>
        @foreach ($scores as $key => $meta)
            @include('logs.partials.score-slider', [
                'key' => $key,
                'label' => $meta['label'],
                'hint' => $meta['hint'],
                'current' => old($key, $log?->{$key} ?? $meta['default']),
            ])
        @endforeach
    </section>

    {{-- くわしく（任意項目。開かなければ従来と同じ手数で保存できる） --}}
    <details class="bg-white rounded-lg border border-gray-200" @if ($hasExtras) open @endif>
        <summary class="cursor-pointer px-6 py-4 font-semibold text-gray-800">
            くわしく
            <span class="ml-1 text-xs font-normal text-gray-400">（すべて任意。未入力でも保存できます）</span>
        </summary>
        <div class="px-6 pb-6 space-y-5 border-t border-gray-100 pt-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="sleep_hours" class="block text-sm font-medium text-gray-700 mb-1">
                        睡眠時間
                        <span class="ml-1 text-xs font-normal text-gray-400">（時間・0.5刻み）</span>
                    </label>
                    <input type="number" id="sleep_hours" name="sleep_hours" value="{{ $sleepHours }}"
                           min="0" max="24" step="0.5" placeholder="例: 6.5"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('sleep_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="day_type" class="block text-sm font-medium text-gray-700 mb-1">勤務形態</label>
                    <select id="day_type" name="day_type"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">未選択</option>
                        @foreach (\App\Support\DayTypes::options() as $code => $label)
                            <option value="{{ $code }}" @selected($dayType === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('day_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @foreach ($extraScores as $key => $meta)
                @include('logs.partials.score-slider', [
                    'key' => $key,
                    'label' => $meta['label'],
                    'hint' => $meta['hint'],
                    'current' => old($key, $log?->{$key}),
                    'optional' => true,
                ])
            @endforeach
        </div>
    </details>

    {{-- ○×項目 --}}
    <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">ストレス源（○×）</h3>
            @if (Route::has('check-items.index'))
                <a href="{{ route('check-items.index') }}" class="text-xs text-indigo-600 hover:underline">項目を編集</a>
            @endif
        </div>
        @forelse ($checkItems as $item)
            @php
                $isOn = (bool) old("check_items.{$item->id}.is_on", $checkValues[$item->id]->is_on ?? false);
                $detail = old("check_items.{$item->id}.detail_text", $checkValues[$item->id]->detail_text ?? '');
            @endphp
            <div class="border-b border-gray-100 pb-3 last:border-0" data-check-row>
                <div class="flex items-center gap-6">
                    <span class="w-40 text-sm text-gray-700">{{ $item->name }}</span>
                    <label class="inline-flex items-center gap-1 text-sm">
                        <input type="radio" name="check_items[{{ $item->id }}][is_on]" value="1"
                               {{ $isOn ? 'checked' : '' }} data-toggle-detail> ○
                    </label>
                    <label class="inline-flex items-center gap-1 text-sm">
                        <input type="radio" name="check_items[{{ $item->id }}][is_on]" value="0"
                               {{ $isOn ? '' : 'checked' }} data-toggle-detail> ✕
                    </label>
                </div>
                <input type="text" name="check_items[{{ $item->id }}][detail_text]" value="{{ $detail }}"
                       placeholder="○の内容（任意）"
                       class="mt-2 w-full rounded-md border-gray-300 text-sm shadow-sm {{ $isOn ? '' : 'hidden' }}"
                       data-detail-input>
            </div>
        @empty
            <p class="text-sm text-gray-500">○×項目が未設定です。</p>
        @endforelse
    </section>

    {{-- 相手タグ --}}
    <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">
                関わった相手
                <span class="ml-1 text-xs font-normal text-gray-400">（任意）</span>
            </h3>
            @if (Route::has('people.index'))
                <a href="{{ route('people.index') }}" class="text-xs text-indigo-600 hover:underline">相手タグを編集</a>
            @endif
        </div>
        @forelse ($people as $person)
            @php $checked = in_array($person->id, $selectedPersonIds); @endphp
            <div data-person-row>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="people[]" value="{{ $person->id }}"
                           {{ $checked ? 'checked' : '' }}
                           data-person
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    {{ $person->name }}
                </label>
                <input type="text" name="people_details[{{ $person->id }}]"
                       value="{{ old("people_details.{$person->id}", $personDetails[$person->id] ?? '') }}"
                       placeholder="何があったか（任意）"
                       class="mt-1 ml-6 w-full max-w-md rounded-md border-gray-300 text-sm shadow-sm {{ $checked ? '' : 'hidden' }}"
                       data-person-detail>
                @error("people_details.{$person->id}") <p class="ml-6 mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @empty
            <p class="text-sm text-gray-500">
                相手タグが未登録です。
                @if (Route::has('people.index'))
                    <a href="{{ route('people.index') }}" class="text-indigo-600 hover:underline">設定画面</a>から追加できます。
                @endif
            </p>
        @endforelse
    </section>

    {{-- テキスト --}}
    <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
        <div>
            <label for="hardest_text" class="block text-sm font-medium text-gray-700 mb-1">今日一番きつかったこと</label>
            <textarea id="hardest_text" name="hardest_text" rows="3"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('hardest_text', $log?->hardest_text) }}</textarea>
            @error('hardest_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="summary_text" class="block text-sm font-medium text-gray-700 mb-1">一言まとめ</label>
            <input type="text" id="summary_text" name="summary_text" value="{{ old('summary_text', $log?->summary_text) }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('summary_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- チェックリスト --}}
    @error('checklist') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    @foreach ($categories as $category)
        <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-3" data-category>
            <h3 class="font-semibold text-gray-800">
                {{ $category->name }}
                @if ($category->code === 'thought_habit')
                    <span class="text-xs text-red-500">（超重要）</span>
                @endif
            </h3>
            @foreach ($category->options as $option)
                @php
                    $checked = in_array($option->id, $selectedOptionIds);
                    $meta = $selectionMeta[$option->id] ?? [];
                @endphp
                <div data-option-row>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="checklist[]" value="{{ $option->id }}"
                               {{ $checked ? 'checked' : '' }}
                               data-option
                               data-none="{{ $option->is_none ? '1' : '0' }}"
                               @if ($option->requires_text) data-requires-text="{{ $option->id }}" @endif
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        {{ $option->label }}
                    </label>
                    @if ($option->requires_text)
                        <input type="text" name="checklist_details[{{ $option->id }}]"
                               value="{{ old("checklist_details.{$option->id}", $selectionDetails[$option->id] ?? '') }}"
                               placeholder="補足を入力"
                               class="mt-1 ml-6 w-64 rounded-md border-gray-300 text-sm shadow-sm">
                        @error("checklist_details.{$option->id}") <p class="ml-6 mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif

                    {{-- 効果測定（tracks_effect のカテゴリのみ。選択したときだけ表示） --}}
                    @if ($category->tracks_effect)
                        <div class="mt-1 ml-6 flex flex-wrap items-center gap-4 {{ $checked ? '' : 'hidden' }}"
                             data-effect-inputs>
                            <label class="text-xs text-gray-500">
                                所要時間
                                <input type="number" name="selection_meta[{{ $option->id }}][duration_min]"
                                       value="{{ old("selection_meta.{$option->id}.duration_min", $meta['duration_min'] ?? '') }}"
                                       min="0" max="1440" step="5" placeholder="分"
                                       class="ml-1 w-24 rounded-md border-gray-300 text-sm shadow-sm">
                                <span class="ml-1">分</span>
                            </label>
                            <label class="text-xs text-gray-500">
                                効いた感
                                <input type="number" name="selection_meta[{{ $option->id }}][effect_score]"
                                       value="{{ old("selection_meta.{$option->id}.effect_score", $meta['effect_score'] ?? '') }}"
                                       min="0" max="10" step="1" placeholder="0-10"
                                       class="ml-1 w-24 rounded-md border-gray-300 text-sm shadow-sm">
                                <span class="ml-1">/ 10</span>
                            </label>
                            @error("selection_meta.{$option->id}.duration_min") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error("selection_meta.{$option->id}.effect_score") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    @endforeach

    {{-- 送信 --}}
    <div class="flex items-center gap-3">
        <button type="submit"
                class="inline-flex items-center px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
            保存する
        </button>
        <a href="{{ url()->previous() }}" class="text-sm text-gray-500 hover:underline">キャンセル</a>
    </div>
</div>

<script>
    // ○選択時のみ内容欄を表示
    document.querySelectorAll('[data-check-row]').forEach(row => {
        const detail = row.querySelector('[data-detail-input]');
        row.querySelectorAll('[data-toggle-detail]').forEach(radio => {
            radio.addEventListener('change', () => {
                const on = row.querySelector('input[value="1"]').checked;
                detail.classList.toggle('hidden', !on);
            });
        });
    });

    // 選択した相手だけ補足欄を表示する
    document.querySelectorAll('[data-person-row]').forEach(row => {
        const checkbox = row.querySelector('[data-person]');
        const detail = row.querySelector('[data-person-detail]');
        checkbox.addEventListener('change', () => {
            detail.classList.toggle('hidden', !checkbox.checked);
        });
    });

    // 選択した項目だけ計測欄（所要時間・効いた感）を表示する
    const syncEffectPanel = opt => {
        const panel = opt.closest('[data-option-row]')?.querySelector('[data-effect-inputs]');
        if (panel) panel.classList.toggle('hidden', !opt.checked);
    };

    // 「特になし」を選ぶと同カテゴリの他項目を解除（逆も同様）
    document.querySelectorAll('[data-category]').forEach(cat => {
        const options = cat.querySelectorAll('[data-option]');
        options.forEach(opt => {
            opt.addEventListener('change', () => {
                if (opt.checked) {
                    const isNone = opt.dataset.none === '1';
                    options.forEach(other => {
                        if (other === opt) return;
                        if (isNone || other.dataset.none === '1') other.checked = false;
                    });
                }
                // 排他で解除された項目も含めて表示を合わせる
                options.forEach(syncEffectPanel);
            });
        });
    });
</script>
