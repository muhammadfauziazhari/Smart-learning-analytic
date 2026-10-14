@props([
    'active' => 'dashboard',
])

@php
    // Setiap menu id harus punya view terkait di resources/views/Kaprodi
    //   dashboard          -> Kaprodi/Dashboard.blade.php
    //   pengumpulan-tugas  -> Kaprodi/PengumpulanTugas.blade.php
    //   keaktifan          -> Kaprodi/Keaktifan.blade.php
    //   ews                -> Kaprodi/Ews.blade.php
    //   settings           -> Kaprodi/Settings.blade.php
    $menus = [
        ['id' => 'dashboard', 'title' => 'Dashboard', 'label' => 'Dashboard', 'icon' => 'fa-house-chimney'],
        ['id' => 'pengumpulan-tugas', 'title' => 'Pengumpulan Tugas', 'label' => 'Pengumpulan Tugas', 'icon' => 'fa-folder-open'],
        ['id' => 'keaktifan', 'title' => 'Keaktifan', 'label' => 'Keaktifan', 'icon' => 'fa-chart-line'],
        ['id' => 'ews', 'title' => 'Early Warning System', 'label' => 'EWS', 'icon' => 'fa-triangle-exclamation'],
        ['id' => 'settings', 'title' => 'Settings', 'label' => 'Settings', 'icon' => 'fa-gear'],
    ];
@endphp

<aside id="sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col transition-all duration-300 z-30">
    <!-- Sidebar Header / Logo -->
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <span class="font-bold text-lg tracking-wide text-white sidebar-text">EduTrack</span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
        @foreach ($menus as $menu)
            <button type="button"
                onclick="switchTab('{{ $menu['id'] }}')"
                data-target="{{ $menu['id'] }}"
                data-title="{{ $menu['title'] }}"
                @class([
                    'nav-item w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200',
                    'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' => $menu['id'] === $active,
                    'text-slate-400 hover:text-white hover:bg-slate-800/60' => $menu['id'] !== $active,
                ])>
                <i class="fa-solid {{ $menu['icon'] }} w-5 text-center"></i>
                <span class="sidebar-text">{{ $menu['label'] }}</span>
            </button>
        @endforeach
    </nav>

    <!-- Sidebar Footer / Profile Pengguna -->
    <div class="p-4 border-t border-slate-800">
        <div class="flex items-center space-x-3 px-3 py-2 rounded-xl bg-slate-800/50">
            <div class="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center font-semibold text-slate-300">
                {{ str(auth()->user()?->nama ?? 'A')->substr(0, 2)->upper() }}
            </div>
            <div class="sidebar-text overflow-hidden">
                <p class="text-xs font-semibold text-white truncate">{{ auth()->user()?->nama ?? 'Kaprodi' }}</p>
                <p class="text-[11px] text-slate-400 truncate">{{ ucfirst(auth()->user()?->role ?? 'kaprodi') }}</p>
            </div>
        </div>
    </div>
</aside>