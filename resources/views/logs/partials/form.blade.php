@php
    /** @var \App\Models\Log|null $log */
    $log = $log ?? null;
    $checkValues = $checkValues ?? collect();
    $selectedOptionIds = old('checklist', $selectedOptionIds ?? []);
    $selectionDetails = $selectionDetails ?? collect();

    $loggedOn = old('logged_on', $log?->logged_on?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $scores = [
        'stress' => ['label' => 'ストレス', 'default' => 5, 'hint' => '高いときつい'],
        'stamina' => ['label' => '体力', 'default' => 5, 'hint' => '高いと元気'],
        'mental_capacity' => ['label' => 'メンタル余裕', 'default' => 5, 'hint' => '高いと余裕あり'],
    ];
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
            @php $current = old($key, $log?->{$key} ?? $meta['default']); @endphp
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="{{ $key }}" class="text-sm font-medium text-gray-700">
                        {{ $meta['label'] }}
                        <span class="ml-1 text-xs font-normal text-gray-400">（{{ $meta['hint'] }}）</span>
                    </label>
                    <span id="{{ $key }}_out" class="text-sm font-semibold text-indigo-600">{{ $current }}</span>
                </div>
                <input type="range" id="{{ $key }}" name="{{ $key }}" min="0" max="10" step="1"
                       value="{{ $current }}" class="w-full"
                       oninput="document.getElementById('{{ $key }}_out').textContent = this.value">
                @error($key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </section>

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
                @php $checked = in_array($option->id, $selectedOptionIds); @endphp
                <div>
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

    // 「特になし」を選ぶと同カテゴリの他項目を解除（逆も同様）
    document.querySelectorAll('[data-category]').forEach(cat => {
        const options = cat.querySelectorAll('[data-option]');
        options.forEach(opt => {
            opt.addEventListener('change', () => {
                if (!opt.checked) return;
                const isNone = opt.dataset.none === '1';
                options.forEach(other => {
                    if (other === opt) return;
                    if (isNone || other.dataset.none === '1') other.checked = false;
                });
            });
        });
    });
</script>
