@php
    $enabled = Route::has($route);
    $active  = $enabled && request()->routeIs($route);
@endphp

@if ($enabled)
    <a href="{{ route($route) }}"
       class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium
              {{ $active
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-100' }}">
        <span>{{ $icon }}</span>
        <span>{{ $label }}</span>
    </a>
@else
    {{-- 未実装：無効表示（該当フェーズで自動的にリンク化される）--}}
    <span class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-300 cursor-not-allowed"
          title="準備中">
        <span>{{ $icon }}</span>
        <span>{{ $label }}</span>
    </span>
@endif
