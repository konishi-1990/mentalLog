@php
    /**
     * 0〜10 のスコア用スライダー。
     *
     * $optional = true かつ現在値が未入力のときは name を付けずに描画し、
     * ユーザがスライダーを操作した時点で JS が name を与える。
     * これにより「くわしく」を開かず（触らず）保存したときに NULL のまま残る。
     *
     * @var string $key
     * @var string $label
     * @var int|string|null $current
     */
    $hint = $hint ?? null;
    $default = $default ?? 5;
    $optional = $optional ?? false;
    $pending = $optional && ($current === null || $current === '');
    $value = $pending ? $default : $current;
@endphp

<div>
    <div class="flex items-center justify-between mb-1">
        <label for="{{ $key }}" class="text-sm font-medium text-gray-700">
            {{ $label }}
            @if ($hint)
                <span class="ml-1 text-xs font-normal text-gray-400">（{{ $hint }}）</span>
            @endif
        </label>
        <span id="{{ $key }}_out"
              class="text-sm font-semibold {{ $pending ? 'text-gray-400' : 'text-indigo-600' }}">{{ $pending ? '未入力' : $value }}</span>
    </div>
    <input type="range" id="{{ $key }}" @if (! $pending) name="{{ $key }}" @endif
           data-score-name="{{ $key }}"
           min="0" max="10" step="1" value="{{ $value }}" class="w-full"
           oninput="this.name = this.dataset.scoreName;
                    const out = document.getElementById('{{ $key }}_out');
                    out.textContent = this.value;
                    out.classList.remove('text-gray-400');
                    out.classList.add('text-indigo-600');">
    @error($key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
