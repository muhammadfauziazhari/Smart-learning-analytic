@props([
    'active' => 'dashboard',
])

@php
    // Semua menu Admin adalah tab di dalam satu halaman (dashboard).
    // Urutannya HARUS sama dengan urutan panel konten di
    // Admin/AdminDashboard.blade.php — ada test yang membandingkannya.
    //
    //   dashboard -> Admin/DashboardRingkasan.blade.php
    //   uploadlog -> Admin/UploadLog.blade.php
    //   akun      -> Admin/Akun.blade.php
    //   penugasan -> Admin/Penugasan.blade.php
    $menus = [
        ['id' => 'dashboard', 'title' => 'Dashboard', 'label' => 'Dashboard', 'icon' => 'fa-house-chimney'],
        ['id' => 'uploadlog', 'title' => 'Upload Log', 'label' => 'Upload Log', 'icon' => 'fa-upload'],
        ['id' => 'akun', 'title' => 'Kelola Akun', 'label' => 'Kelola Akun', 'icon' => 'fa-users-gear'],
        ['id' => 'penugasan', 'title' => 'Penugasan Akademik', 'label' => 'Penugasan', 'icon' => 'fa-chalkboard-user'],
    ];
@endphp

{{--
    Kelas bg-indigo-600 / text-white sengaja tetap dipakai sebagai penanda
    menu aktif, karena tab-switcher (JS) kemungkinan menukar kelas itu.
    Warnanya ditimpa lewat <style> di bawah menjadi amber sesuai desain,
    jadi JS tidak perlu diubah.
--}}
<style>
    #sidebar .nav-item.bg-indigo-600 {
        background-image: linear-gradient(135deg, #F2B84B 0%, #E09F2B 100%);
        color: #0B1B3D;
        box-shadow: 0 8px 20px -8px rgba(235, 168, 58, .55);
    }
    #sidebar .nav-item:not(.bg-indigo-600) {
        color: #CBD5E1;
    }
    #sidebar .nav-item:not(.bg-indigo-600):hover {
        background-color: rgba(255, 255, 255, .07);
        color: #fff;
    }
</style>

<aside id="sidebar" class="w-64 bg-[#0B1B3D] flex flex-col transition-all duration-300 z-30 lg:m-3 lg:rounded-2xl shadow-xl">

    <!-- Sidebar Header / Logo -->
    <div class="h-20 flex items-center px-6">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-300 flex items-center justify-center text-[#0B1B3D] shadow-lg shadow-amber-500/20">
                <i class="fa-solid fa-graduation-cap text-lg"></i>
            </div>
            <div class="sidebar-text leading-none">
                <span class="block font-extrabold text-xl tracking-tight text-white">
                    Edu<span class="text-amber-400">Track</span>
                </span>
                <span class="block text-[10px] text-slate-400 mt-1">Smart Learning Analytics</span>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
        @foreach ($menus as $menu)
            @php $aktif = $menu['id'] === $active; @endphp

            {{-- Semua menu berpindah panel di dalam satu halaman.
                 URL lama (/admin/akun, /admin/penugasan) tetap bekerja:
                 keduanya merender halaman yang sama dengan tabnya terbuka. --}}
            <button type="button"
                onclick="switchTab('{{ $menu['id'] }}')"
                data-target="{{ $menu['id'] }}"
                data-title="{{ $menu['title'] }}"
                @class([
                    'nav-item w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-200',
                    'bg-indigo-600 text-white' => $aktif,
                    'text-slate-400 hover:text-white hover:bg-slate-800/60' => ! $aktif,
                ])>
                <i class="fa-solid {{ $menu['icon'] }} w-5 text-center"></i>
                <span class="sidebar-text">{{ $menu['label'] }}</span>
            </button>
        @endforeach
    </nav>

    <!-- Sidebar Footer / Profil Pengguna -->
    <div class="p-4">
        <div class="border-t border-white/10 pt-4">
            <div class="flex items-center space-x-3 px-2 py-2 rounded-xl hover:bg-white/5 transition-colors">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-500 to-blue-700 flex items-center justify-center text-sm font-bold text-white ring-2 ring-white/10">
                    {{ str(auth()->user()?->nama ?? 'A')->substr(0, 2)->upper() }}
                </div>
                <div class="sidebar-text flex-1 overflow-hidden">
                    <p class="text-xs font-semibold text-white truncate">{{ auth()->user()?->nama ?? 'Administrator' }}</p>
                    <p class="text-[11px] text-slate-400 truncate">{{ ucfirst(auth()->user()?->role ?? 'admin') }}</p>
                </div>
                <i class="fa-solid fa-chevron-right text-[10px] text-slate-500 sidebar-text"></i>
            </div>
        </div>
    </div>
</aside>
