@props(['title', 'items', 'label'])

@php $max = max(1, collect($items)->max('total') ?? 1); @endphp

<section class="bg-white rounded-lg border border-gray-200 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">{{ $title }}</h3>
    @if (collect($items)->isEmpty())
        <p class="text-sm text-gray-400">データがありません。</p>
    @else
        <ul class="space-y-2">
            @foreach ($items as $item)
                <li>
                    <div class="flex justify-between text-sm text-gray-700 mb-0.5">
                        <span>{{ $item->$label }}</span>
                        <span class="text-gray-400">{{ $item->total }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded">
                        <div class="h-2 bg-indigo-400 rounded" style="width: {{ round($item->total / $max * 100) }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
