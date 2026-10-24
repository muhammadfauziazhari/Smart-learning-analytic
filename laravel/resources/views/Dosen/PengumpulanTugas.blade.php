{{--
    Tab "Pengumpulan Tugas" (pengguna Dosen).

    Isinya matriks: baris = mahasiswa, kolom = minggu. Kolom mengikuti nomor
    di nama tugas ("Praktikum 7" di Minggu 7); tugas tanpa nomor (ATS, AAS,
    dll.) masuk kolom minggu semester saat tugasnya dibuat. Warna sel
    mengikuti aturan tenggat "7 hari setelah tugas dibuat" yang
    ditetapkan pengguna (lihat RekapTugas).

    Hanya bagian di dalam <div id="content-pengumpulan-tugas"> milik
    cangkang Dosen/Dashboard.blade.php; jangan menambah div pembungkus
    dengan id sendiri, karena id-nya jadi dua dan tab bisa gagal dibuka.
--}}

@php
    /** @var array<string, mixed> $rekapTugas */
    $rekapTugas = $rekapTugas ?? [];

    $gayaSel = [
        App\Services\RekapTugas::TEPAT_WAKTU => 'bg-emerald-500',
        App\Services\RekapTugas::TERLAMBAT => 'bg-amber-400',
        App\Services\RekapTugas::TIDAK_MENGUMPULKAN => 'bg-rose-500',
        App\Services\RekapTugas::TANPA_TUGAS => 'bg-slate-800/40',
    ];

    $judulSel = [
        App\Services\RekapTugas::TEPAT_WAKTU => 'Tepat waktu',
        App\Services\RekapTugas::TERLAMBAT => 'Terlambat',
        App\Services\RekapTugas::TIDAK_MENGUMPULKAN => 'Tidak mengumpulkan',
        App\Services\RekapTugas::TANPA_TUGAS => 'Tidak ada tugas minggu ini',
    ];

    $kelasTerpilih = collect($rekapTugas['kelas'] ?? []);
@endphp

<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h2 class="text-xl font-bold text-white">Pengumpulan Tugas</h2>
        <p class="text-xs text-slate-400 mt-0.5">
            Tepat waktu berarti tugas dikumpulkan paling lambat 7 hari setelah tugas dibuat.
            Arahkan kursor ke kotak untuk melihat rincian tugasnya.
        </p>
    </div>
</div>

{{-- -------------------------------------------------- filter --}}
<form method="GET" class="flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="pengumpulan-tugas">

    <div class="min-w-[200px]">
        <label for="tugas-mk" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Mata Kuliah</label>
        <select id="tugas-mk" name="mata_kuliah" onchange="this.form.submit()"
            class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
            <option value="">Semua</option>
            @foreach ($rekapTugas['filter']['pilihanMataKuliah'] ?? [] as $mk)
                <option value="{{ $mk['kode'] }}" @selected(($rekapTugas['filter']['mata_kuliah'] ?? null) === $mk['kode'])>
                    {{ $mk['kode'] }} - {{ $mk['nama'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="min-w-[140px]">
        <label for="tugas-jenis" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Jenis</label>
        <select id="tugas-jenis" name="jenis" onchange="this.form.submit()"
            class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
            <option value="">Semua</option>
            @foreach ($rekapTugas['filter']['pilihanJenis'] ?? [] as $jenis)
                <option value="{{ $jenis }}" @selected(($rekapTugas['filter']['jenis'] ?? null) === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
    </div>

    <div class="min-w-[200px]">
        <label for="tugas-kelas" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Kelas</label>
        <select id="tugas-kelas" name="kelas_id" onchange="this.form.submit()"
            class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
            <option value="">Semua</option>
            @foreach ($rekapTugas['filter']['pilihanKelas'] ?? [] as $k)
                <option value="{{ $k['id'] }}" @selected((int) ($rekapTugas['filter']['kelas_id'] ?? 0) === (int) $k['id'])>
                    {{ $k['label'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="min-w-[180px]">
        <label for="tugas-cari" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Cari</label>
        <input id="tugas-cari" type="search" name="q" value="{{ $rekapTugas['filter']['q'] ?? '' }}"
            placeholder="Nama atau NIM"
            class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none">
    </div>

    <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm transition-colors">
        <i class="fa-solid fa-magnifying-glass mr-1.5"></i>Cari
    </button>

    @if (($rekapTugas['filter']['mata_kuliah'] ?? null) || ($rekapTugas['filter']['jenis'] ?? null)
        || ($rekapTugas['filter']['kelas_id'] ?? null) || ($rekapTugas['filter']['q'] ?? null))
        <a href="{{ route('dosen.dashboard', ['tab' => 'pengumpulan-tugas']) }}"
            class="px-4 py-2.5 rounded-xl text-xs text-slate-400 hover:text-white transition-all">Reset</a>
    @endif
</form>

{{-- -------------------------------------------------- legenda --}}
<div class="flex flex-wrap items-center gap-4 text-[11px] text-slate-400">
    <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded bg-emerald-500"></span> Tepat waktu
    </span>
    <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded bg-amber-400"></span> Terlambat
    </span>
    <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded bg-rose-500"></span> Tidak mengumpulkan
    </span>
    <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded bg-slate-800/40 border border-slate-700"></span> Tidak ada tugas
    </span>

    @if (! ($rekapTugas['kosong'] ?? true))
        <span class="ml-auto text-slate-500">
            {{ number_format($rekapTugas['ringkasan']['tugas'], 0, ',', '.') }} tugas ·
            {{ count($rekapTugas['baris']) }} mahasiswa ·
            {{ $rekapTugas['ringkasan']['tepat'] }} tepat waktu,
            {{ $rekapTugas['ringkasan']['terlambat'] }} terlambat,
            {{ $rekapTugas['ringkasan']['tidak'] }} tidak mengumpulkan
        </span>
    @endif
</div>

@if (! ($rekapTugas['kosong'] ?? true) && ($rekapTugas['jumlahMahasiswa'] ?? 0) > count($rekapTugas['baris']))
    <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 px-4 py-3 text-xs text-amber-400 flex items-start gap-2">
        <i class="fa-solid fa-circle-info mt-0.5"></i>
        <span>
            Menampilkan {{ count($rekapTugas['baris']) }} dari {{ $rekapTugas['jumlahMahasiswa'] }} mahasiswa,
            dimulai dari yang paling banyak menunggak. Saring per kelas atau pakai pencarian untuk melihat sisanya;
            tab Keaktifan memuat seluruh mahasiswa dalam satu baris yang jauh lebih ringan.
        </span>
    </div>
@endif

@if ($rekapTugas['kosong'] ?? true)
    <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
        <i class="fa-solid fa-inbox mb-2 text-3xl"></i>
        <p class="text-sm">Belum ada data pengumpulan tugas untuk pilihan ini.</p>
        <p class="text-xs mt-1">
            Hanya tugas yang namanya menyebut kelas yang ditampilkan, dan tugas harus punya catatan
            "dibuat" di log LMS agar tenggatnya bisa dihitung.
        </p>
    </div>
@else
    {{-- -------------------------------------------------- matriks --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="text-xs border-collapse">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-20 bg-slate-900 px-4 py-2 text-left font-medium text-slate-400 whitespace-nowrap">
                            Nama / NIM
                        </th>
                        @foreach ($rekapTugas['minggu'] as $minggu)
                            <th class="px-1 py-2 text-center font-medium text-slate-400 whitespace-nowrap"
                                title="{{ $minggu['mulai'] }} · {{ $minggu['tugas'] }} tugas jatuh tempo">
                                <span class="block">{{ $minggu['label'] }}</span>
                                <span class="block text-[10px] font-normal text-slate-600">{{ date('d/m', strtotime($minggu['mulai'])) }}</span>
                            </th>
                        @endforeach
                        <th class="px-3 py-2 text-center font-medium text-slate-400 whitespace-nowrap">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    @foreach ($rekapTugas['baris'] as $mhs)
                        <tr class="hover:bg-slate-800/20">
                            <td class="sticky left-0 z-10 bg-slate-900 px-4 py-2 whitespace-nowrap">
                                <span class="text-slate-200">{{ $mhs['nama'] }}</span>
                                <span class="block font-mono text-[10px] text-slate-500">{{ $mhs['nim'] }}</span>
                            </td>

                            @foreach ($rekapTugas['minggu'] as $minggu)
                                @php
                                    $sel = $mhs['sel'][$minggu['minggu']] ?? ['status' => App\Services\RekapTugas::TANPA_TUGAS, 'detail' => []];
                                    $terkumpul = count(array_filter(
                                        $sel['detail'],
                                        fn ($d) => $d['status'] !== App\Services\RekapTugas::TIDAK_MENGUMPULKAN
                                    ));
                                    $judul = $judulSel[$sel['status']].' - '.$minggu['label']."\n";
                                    foreach ($sel['detail'] as $d) {
                                        // Tooltip dipendekkan: 5.390 sel x
                                        // keterangan panjang membuat HTML
                                        // halaman dashboard naik ke 5 MB.
                                        // Yang dikumpulkan cukup tanggalnya;
                                        // tenggat hanya ditulis untuk yang
                                        // tidak mengumpulkan.
                                        $keterangan = $d['waktu'] === 'tidak mengumpulkan'
                                            ? 'tenggat '.date('d/m/Y', strtotime($d['tenggat']))
                                            : date('d/m H:i', strtotime($d['waktu']));
                                        $judul .= '- '.$d['nama'].': '.$judulSel[$d['status']].' ('.$keterangan.")\n";
                                    }
                                @endphp
                                <td class="px-1 py-1 text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-md {{ $gayaSel[$sel['status']] }} text-[11px] font-semibold
                                        {{ $sel['status'] === App\Services\RekapTugas::TANPA_TUGAS ? 'text-slate-600' : 'text-slate-950' }}"
                                        title="{{ trim($judul) }}">
                                        {{ $sel['status'] === App\Services\RekapTugas::TANPA_TUGAS ? '' : $terkumpul }}
                                    </span>
                                </td>
                            @endforeach

                            <td class="px-3 py-2 text-center whitespace-nowrap">
                                <span class="text-emerald-400">{{ $mhs['tepat'] }}</span>
                                <span class="text-slate-600">/</span>
                                <span class="text-amber-400">{{ $mhs['terlambat'] }}</span>
                                <span class="text-slate-600">/</span>
                                <span class="text-rose-400">{{ $mhs['tidak'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- -------------------------------------------------- contoh notifikasi --}}
    @php
        $untukDiingatkan = array_slice(array_filter(
            $rekapTugas['baris'],
            fn ($m) => $m['tidak'] > 0 || $m['terlambat'] > 0
        ), 0, 5);
    @endphp

    @if ($untukDiingatkan !== [])
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
            <header class="px-5 py-4 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">
                    <i class="fa-solid fa-bell text-slate-500 mr-1.5"></i>Contoh pesan yang bisa dikirim
                </h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    Hanya pratinjau. Sistem belum mengirim pesan apa pun (FR-24/FR-25 belum dikerjakan).
                </p>
            </header>
            <ul class="divide-y divide-slate-800/70">
                @foreach ($untukDiingatkan as $mhs)
                    <li class="px-5 py-3 text-xs text-slate-300">
                        <span class="text-slate-200 font-medium">{{ $mhs['nama'] }}</span>
                        <span class="font-mono text-[10px] text-slate-500 ml-1">{{ $mhs['nim'] }}</span>
                        <p class="mt-1 text-slate-400">
                            Halo {{ \Illuminate\Support\Str::before($mhs['nama'], ' ') }}, dari rekap pengumpulan tugas
                            {{ $kelasTerpilih->pluck('nama')->join(', ') }} tercatat
                            @if ($mhs['tidak'] > 0)
                                <span class="text-rose-400">{{ $mhs['tidak'] }} minggu tanpa pengumpulan</span>@endif
                            @if ($mhs['terlambat'] > 0)
                                dan <span class="text-amber-400">{{ $mhs['terlambat'] }} pengumpulan terlambat</span>@endif
                            . Mohon lengkapi ya.
                        </p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif