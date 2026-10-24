{{-- Partial: tab Dashboard (ringkasan Admin) --}}

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-xl font-bold text-white">Ringkasan Dashboard</h2>
        <p class="text-xs text-slate-400 mt-0.5">Selamat datang kembali di panel kontrol akademik Anda.</p>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
        <p class="text-[11px] uppercase tracking-wider text-slate-500">Mata kuliah</p>
        <p class="text-2xl font-bold text-white mt-1">{{ $ringkasan['mataKuliah'] ?? 0 }}</p>
    </div>
    <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
        <p class="text-[11px] uppercase tracking-wider text-slate-500">Kelas</p>
        <p class="text-2xl font-bold text-white mt-1">{{ $ringkasan['kelas'] ?? 0 }}</p>
    </div>
    <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
        <p class="text-[11px] uppercase tracking-wider text-slate-500">Mahasiswa</p>
        <p class="text-2xl font-bold text-white mt-1">{{ number_format($ringkasan['mahasiswa'] ?? 0, 0, ',', '.') }}</p>
    </div>
    <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
        <p class="text-[11px] uppercase tracking-wider text-slate-500">Baris log</p>
        <p class="text-2xl font-bold text-white mt-1">{{ number_format($ringkasan['log'] ?? 0, 0, ',', '.') }}</p>
    </div>
</div>

<div class="rounded-2xl border border-slate-800 bg-slate-900/40 divide-y divide-slate-800/70">
    @foreach ($ringkasan['mataKuliahTerdaftar'] as $mk)
        <div class="flex items-center justify-between px-5 py-3">
            <div class="min-w-0">
                <p class="text-sm text-slate-200 truncate">
                    <span class="font-mono text-xs text-slate-500">{{ $mk['kode'] }}</span>
                    {{ $mk['nama'] }}
                </p>
            </div>
            <div class="flex items-center gap-4 text-[11px] text-slate-400 shrink-0 ml-4">
                <span><i class="fa-solid fa-users text-slate-600 mr-1"></i>{{ number_format($mk['mahasiswa'], 0, ',', '.') }} mhs</span>
                <span><i class="fa-solid fa-layer-group text-slate-600 mr-1"></i>{{ $mk['kelas'] }} kelas</span>
                <span><i class="fa-solid fa-chalkboard-user text-slate-600 mr-1"></i>{{ $mk['dosen'] }} dosen</span>
                <span><i class="fa-solid fa-list text-slate-600 mr-1"></i>{{ number_format($mk['log'], 0, ',', '.') }}</span>
            </div>
        </div>
    @endforeach
</div>
