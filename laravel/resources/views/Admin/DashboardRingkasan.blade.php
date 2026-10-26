{{-- Partial: tab Dashboard (ringkasan Admin) --}}
@php
    $daftarMk = collect($ringkasan['mataKuliahTerdaftar'] ?? []);
    $maxMhs = max(1, (int) $daftarMk->max('mahasiswa'));
    $maxLog = max(1, (int) $daftarMk->max('log'));
    $mkTerbanyakLog = $daftarMk->sortByDesc('log')->first();
@endphp

<div class="space-y-6 rounded-3xl bg-slate-100 p-5 lg:p-7">

    {{-- ------------------------------------------------ sapaan --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-[#0B1B3D]">
                Halo, {{ auth()->user()?->nama ?? 'Administrator' }} 👋
            </h2>
            <p class="text-sm text-blue-600 mt-1">
                Selamat datang kembali di panel kontrol akademik Anda.
            </p>
        </div>
        <div class="flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600 shadow-sm">
            <i class="fa-regular fa-calendar text-slate-400"></i>
            {{ now()->translatedFormat('l, d F Y') }}
        </div>
    </div>

    {{-- ------------------------------------------------ kartu ringkasan --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Mata kuliah (navy) --}}
        <div class="relative overflow-hidden rounded-2xl bg-[#0B1B3D] p-5 text-white shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-blue-500/30 flex items-center justify-center">
                    <i class="fa-solid fa-book"></i>
                </div>
                <p class="text-sm font-medium text-slate-200">Mata kuliah</p>
            </div>
            <p class="text-4xl font-extrabold mt-3">{{ $ringkasan['mataKuliah'] ?? 0 }}</p>
            <i class="fa-solid fa-book-open absolute -bottom-2 right-3 text-6xl text-white/5"></i>
        </div>

        {{-- Kelas (biru) --}}
        <div class="relative overflow-hidden rounded-2xl bg-blue-600 p-5 text-white shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <p class="text-sm font-medium text-blue-50">Kelas</p>
            </div>
            <p class="text-4xl font-extrabold mt-3">{{ $ringkasan['kelas'] ?? 0 }}</p>
            <i class="fa-solid fa-chalkboard absolute -bottom-2 right-3 text-6xl text-white/10"></i>
        </div>

        {{-- Mahasiswa (amber) --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-300 to-amber-400 p-5 text-[#0B1B3D] shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-white/50 flex items-center justify-center">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <p class="text-sm font-semibold">Mahasiswa</p>
            </div>
            <p class="text-4xl font-extrabold mt-3">{{ number_format($ringkasan['mahasiswa'] ?? 0, 0, ',', '.') }}</p>
            <i class="fa-solid fa-users absolute -bottom-2 right-3 text-6xl text-white/30"></i>
        </div>

        {{-- Baris log (biru muda) --}}
        <div class="relative overflow-hidden rounded-2xl bg-blue-50 border border-blue-100 p-5 text-[#0B1B3D] shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-blue-500 flex items-center justify-center text-white">
                    <i class="fa-solid fa-database"></i>
                </div>
                <p class="text-sm font-semibold">Baris log</p>
            </div>
            <p class="text-4xl font-extrabold mt-3">{{ number_format($ringkasan['log'] ?? 0, 0, ',', '.') }}</p>
            <i class="fa-solid fa-list absolute -bottom-2 right-3 text-6xl text-blue-200/50"></i>
        </div>
    </div>

    {{-- ------------------------------------------------ tabel + insight --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Mata kuliah terdaftar --}}
        <section class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <header class="px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-[#0B1B3D]">Mata Kuliah Terdaftar</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Jumlah mahasiswa dan volume log per mata kuliah.</p>
            </header>

            @if ($daftarMk->isEmpty())
                <div class="p-10 text-center text-sm text-slate-500">
                    <i class="fa-solid fa-book-open text-2xl text-slate-300 mb-2"></i>
                    <p>Belum ada mata kuliah terdaftar.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 font-medium">Mata kuliah</th>
                                <th class="px-3 py-3 font-medium text-right">Kelas</th>
                                <th class="px-3 py-3 font-medium text-right">Dosen</th>
                                <th class="px-3 py-3 font-medium w-40">Mahasiswa</th>
                                <th class="px-5 py-3 font-medium w-40">Log</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($daftarMk as $mk)
                                <tr class="hover:bg-slate-50"
                                    data-kode="{{ $mk['kode'] }}"
                                    data-kelas="{{ $mk['kelas'] }}"
                                    data-mahasiswa="{{ $mk['mahasiswa'] }}"
                                    data-log="{{ $mk['log'] }}">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-800 truncate">{{ $mk['nama'] }}</p>
                                                <p class="font-mono text-[11px] text-slate-400">{{ $mk['kode'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right text-slate-600">{{ $mk['kelas'] }}</td>
                                    <td class="px-3 py-3 text-right text-slate-600">{{ $mk['dosen'] }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full rounded-full bg-emerald-500"
                                                    style="width: {{ round(($mk['mahasiswa'] / $maxMhs) * 100) }}%"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-slate-700 w-10 text-right">{{ number_format($mk['mahasiswa'], 0, ',', '.') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full rounded-full bg-[#0F3C8A]"
                                                    style="width: {{ round(($mk['log'] / $maxLog) * 100) }}%"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-slate-700 w-14 text-right">{{ number_format($mk['log'], 0, ',', '.') }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Insight --}}
        <section class="rounded-2xl bg-amber-50 border border-amber-200 p-5 shadow-sm flex flex-col">
            <div class="flex items-center gap-2">
                <i class="fa-regular fa-lightbulb text-xl text-amber-500"></i>
                <h3 class="text-sm font-bold text-[#0B1B3D]">Insight</h3>
            </div>
            <p class="text-xs leading-relaxed text-slate-600 mt-3 flex-1">
                @if (($ringkasan['log'] ?? 0) <= 0)
                    Belum ada log pembelajaran yang diunggah. Unggah log LMS agar analitik bisa dihitung.
                @elseif ($mkTerbanyakLog)
                    Volume log terbesar ada di {{ $mkTerbanyakLog['nama'] }}
                    ({{ number_format($mkTerbanyakLog['log'], 0, ',', '.') }} baris).
                    Pastikan penugasan dosen dan kelasnya sudah lengkap supaya datanya terbaca di dashboard dosen.
                @else
                    Data mata kuliah belum tersedia.
                @endif
            </p>
            <button type="button" onclick="switchTab('uploadlog')"
                class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-[#0F3277] hover:bg-[#0B255B] px-4 py-2.5 text-xs font-semibold text-white transition-all">
                Buka Upload Log <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </button>
        </section>
    </div>
</div>
