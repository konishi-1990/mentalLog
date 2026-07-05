@php
    // メニュー定義：ルートが未実装のものは無効表示（フェーズ進行で自動的に有効化される）
    $menu = [
        ['route' => 'dashboard',          'label' => 'ダッシュボード',   'icon' => '🏠'],
        ['route' => 'logs.create',        'label' => 'ログを書く',       'icon' => '📝'],
        ['route' => 'logs.index',         'label' => 'ログ一覧',         'icon' => '📚'],
        ['route' => 'analytics.index',    'label' => '分析',             'icon' => '📊'],
        ['route' => 'check-items.index',  'label' => '○×項目の設定',    'icon' => '⚙️'],
    ];
    $adminMenu = [
        ['route' => 'admin.users.index',     'label' => 'ユーザ管理',           'icon' => '👥'],
        ['route' => 'admin.checklist.index', 'label' => 'チェックリスト管理',   'icon' => '🗂️'],
    ];
@endphp

<aside class="w-60 shrink-0 bg-white border-r border-gray-200 flex flex-col min-h-screen">
    {{-- ロゴ --}}
    <div class="h-16 flex items-center px-6 border-b border-gray-200">
        <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-800">
            🧠 MentalLog
        </a>
    </div>

    {{-- メインメニュー --}}
    <nav class="flex-1 px-3 py-4 space-y-1">
        @foreach ($menu as $item)
            @include('layouts.partials.nav-item', $item)
        @endforeach

        @php $hasAdmin = Auth::user()?->isAdmin()
            && collect($adminMenu)->contains(fn ($m) => Route::has($m['route'])); @endphp
        @if ($hasAdmin)
            <div class="pt-4 mt-4 border-t border-gray-200">
                <p class="px-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">管理</p>
                @foreach ($adminMenu as $item)
                    @include('layouts.partials.nav-item', $item)
                @endforeach
            </div>
        @endif
    </nav>

    {{-- フッター（ユーザ情報 / ログアウト）--}}
    <div class="px-3 py-4 border-t border-gray-200">
        <div class="px-3 pb-2 text-sm text-gray-600 truncate">
            {{ Auth::user()?->name }}
        </div>
        <a href="{{ route('profile.edit') }}"
           class="block px-3 py-2 rounded-md text-sm text-gray-600 hover:bg-gray-100">
            プロフィール
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full text-left px-3 py-2 rounded-md text-sm text-gray-600 hover:bg-gray-100">
                ログアウト
            </button>
        </form>
    </div>
</aside>
