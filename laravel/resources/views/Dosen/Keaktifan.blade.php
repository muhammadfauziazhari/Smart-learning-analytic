{{--
    Tab "Keaktifan" (pengguna Dosen).

    Satu baris satu mahasiswa, kolom berisi angka aktivitas dari log LMS
    (lihat App\Services\StatistikKeaktifan untuk pemetaan konteksnya).

    Dua hal yang sengaja dinyatakan terbuka di halaman ini, karena keduanya
    sering disalahpahami sebagai "mahasiswanya yang bermasalah": kolom
    Forum (log yang diimpor tidak memuat event post forum) dan kolom Status
    EWS (ambang resminya belum ditetapkan, FR-17).

    Bagian ini berada di dalam <div id="content-keaktifan"> milik cangkang
    Dosen/Dashboard.blade.php; jangan menambah div pembungkus dengan id
    sendiri karena id-nya jadi dua dan tab bisa gagal terbuka.
--}}

@php
    /** @var array<string, mixed> $keaktifan */
    $keaktifan = $keaktifan ?? [];

    $warnaEws = [
        'merah' => 'bg-white text-black border-rose-500',
        'kuning' => 'bg-white text-black border-amber-500',
        'hijau' => 'bg-white text-black border-emerald-500',
        'abu' => 'bg-white text-black border-slate-400',
    ];

    $jumlahKelas = count($keaktifan['kelas'] ?? []);
@endphp

<div>
    <h2 class="text-xl font-bold text-black">Detail Keaktifan Mahasiswa</h2>
    <p class="text-xs text-slate-400 mt-0.5">
        Angka aktivitas diambil dari log LMS dalam rentang yang dipilih.
        Tepat waktu berarti tugas dikumpulkan paling lambat 7 hari setelah tugas dibuat.
    </p>
</div>

{{-- -------------------------------------------------- filter --}}
<form method="GET" class="flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="keaktifan">

    <div class="min-w-[210px]">
        <label for="aktif-mk" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Mata Kuliah</label>
        <select id="aktif-mk" name="mata_kuliah" onchange="this.form.submit()"
            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-black focus:border-indigo-500 focus:outline-none">
            <option value="">Semua</option>
            @foreach ($keaktifan['filter']['pilihanMataKuliah'] ?? [] as $mk)
                <option value="{{ $mk['kode'] }}" @selected(($keaktifan['filter']['mata_kuliah'] ?? null) === $mk['kode'])>
                    {{ $mk['kode'] }} - {{ $mk['nama'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="min-w-[150px]">
        <label for="aktif-jenis" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Jenis Kelas</label>
        <select id="aktif-jenis" name="jenis" onchange="this.form.submit()"
            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-black focus:border-indigo-500 focus:outline-none">
            <option value="">Semua</option>
            @foreach ($keaktifan['filter']['pilihanJenis'] ?? [] as $jenis)
                <option value="{{ $jenis }}" @selected(($keaktifan['filter']['jenis'] ?? null) === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
    </div>

    <div class="min-w-[210px]">
        <label for="aktif-kelas" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Kelas</label>
        <select id="aktif-kelas" name="kelas_id" onchange="this.form.submit()"
            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-black focus:border-indigo-500 focus:outline-none">
            <option value="">Semua</option>
            @foreach ($keaktifan['filter']['pilihanKelas'] ?? [] as $k)
                <option value="{{ $k['id'] }}" @selected((int) ($keaktifan['filter']['kelas_id'] ?? 0) === (int) $k['id'])>
                    {{ $k['label'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="min-w-[150px]">
        <label for="aktif-dari" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Dari</label>
        <input id="aktif-dari" type="date" name="dari" value="{{ $keaktifan['rentang']['dari'] ?? '' }}"
            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-black focus:border-indigo-500 focus:outline-none">
    </div>

    <div class="min-w-[150px]">
        <label for="aktif-sampai" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Sampai</label>
        <input id="aktif-sampai" type="date" name="sampai" value="{{ $keaktifan['rentang']['sampai'] ?? '' }}"
            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-black focus:border-indigo-500 focus:outline-none">
    </div>

    <div class="min-w-[180px]">
        <label for="aktif-cari" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Cari</label>
        <input id="aktif-cari" type="search" name="q" value="{{ $keaktifan['filter']['q'] ?? '' }}"
            placeholder="Nama atau NIM"
            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-black placeholder:text-black focus:border-indigo-500 focus:outline-none">
    </div>

    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-black text-sm transition-all">
        <i class="fa-solid fa-filter mr-1.5"></i>Terapkan
    </button>

    @if (($keaktifan['filter']['mata_kuliah'] ?? null) || ($keaktifan['filter']['jenis'] ?? null)
        || ($keaktifan['filter']['kelas_id'] ?? null) || ($keaktifan['filter']['q'] ?? null)
        || ! ($keaktifan['rentang']['bawaan'] ?? true))
        <a href="{{ route('dosen.dashboard', ['tab' => 'keaktifan']) }}"
            class="px-4 py-2.5 rounded-xl text-xs text-slate-400 hover:text-white transition-all">
            Reset ke rentang semester
        </a>
    @endif
</form>

{{-- -------------------------------------------------- ringkasan --}}
@if (! ($keaktifan['kosong'] ?? true))
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="rounded-xl border border-slate-300 bg-white p-4">
        <p class="text-[11px] uppercase tracking-wider text-black">Mahasiswa</p>
        <p class="text-2xl font-bold text-black mt-1">{{ number_format($keaktifan['ringkasan']['mahasiswa'], 0, ',', '.') }}</p>
        <p class="text-[11px] text-black mt-0.5">{{ $jumlahKelas }} kelas · {{ $keaktifan['rentang']['label'] }}</p>
        </div>
    <div class="rounded-xl border border-slate-300 bg-white p-4">
        <p class="text-[11px] uppercase tracking-wider text-black">Pengumpulan tugas</p>
        <p class="text-2xl font-bold text-black mt-1">{{ number_format($keaktifan['ringkasan']['submission'], 0, ',', '.') }}</p>
        <p class="text-[11px] text-black mt-0.5">
            <span>{{ $keaktifan['ringkasan']['tepat'] }} tepat waktu</span>,
            <span>{{ $keaktifan['ringkasan']['terlambat'] }} terlambat</span>
            </p>
        </div>
    <div class="rounded-xl border border-slate-300 bg-white p-4">
        <p class="text-[11px] uppercase tracking-wider text-black">Rata-rata engagement</p>
        <p class="text-2xl font-bold text-black mt-1">
                {{ $keaktifan['ringkasan']['rataEngagement'] === null ? '—' : round($keaktifan['ringkasan']['rataEngagement'] * 100).'%' }}
            </p>
        <p class="text-[11px] text-black mt-0.5">Dari layanan analytics</p>
        </div>
    <div class="rounded-xl border border-slate-300 bg-white p-4">
        <p class="text-[11px] uppercase tracking-wider text-black">Status EWS</p>
        <p class="text-2xl font-bold text-black mt-1">
            <span>{{ $keaktifan['ringkasan']['ews']['high'] ?? 0 }}</span>
            <span class="text-base">/</span>
            <span>{{ $keaktifan['ringkasan']['ews']['medium'] ?? 0 }}</span>
            <span class="text-base">/</span>
            <span>{{ $keaktifan['ringkasan']['ews']['low'] ?? 0 }}</span>
            </p>
        <p class="text-[11px] text-black mt-0.5">At-Risk / Perhatian / Aman</p>
        </div>
    </div>
@endif

{{-- -------------------------------------------------- tabel --}}
@if ($keaktifan['kosong'] ?? true)
    <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
        <i class="fa-solid fa-table-list mb-2 text-3xl"></i>
        <p class="text-sm">Tidak ada data keaktifan untuk pilihan ini.</p>
        <p class="text-xs mt-1">
            @if (($keaktifan['filter']['q'] ?? null) || ($keaktifan['filter']['kelas_id'] ?? null))
                Coba kosongkan pencarian, atau kembalikan filter kelas ke "Semua".
            @else
                Dosen ini belum punya kelas yang boleh dibuka, atau kelasnya belum berisi mahasiswa.
            @endif
        </p>
    </div>
@else
    <div class="rounded-2xl border border-slate-300 bg-white overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-black">
                <thead class="text-left text-[11px] uppercase tracking-wider text-black bg-white">
                    <tr>
                        <th class="px-3 py-3 font-medium text-center">No</th>
                        <th class="px-3 py-3 font-medium">NIM</th>
                        <th class="px-3 py-3 font-medium">Nama</th>
                        <th class="px-3 py-3 font-medium">Kelas</th>
                        <th class="px-3 py-3 font-medium text-right" title="Event dengan konteks Course: membuka halaman mata kuliah dan seksi">Akses Course</th>
                        <th class="px-3 py-3 font-medium text-right" title="Event modul isi: File, URL, Page, Folder, Subsection, Text and media area">Akses Materi</th>
                        <th class="px-3 py-3 font-medium text-right" title="Event dengan konteks Forum. Log yang diimpor tidak memuat event post forum, jadi angka ini tidak bisa dipakai menilai mahasiswa">Forum</th>
                        <th class="px-3 py-3 font-medium text-right" title="Jumlah pengumpulan tugas">Submission</th>
                        <th class="px-3 py-3 font-medium text-right" title="Dikumpulkan paling lambat 7 hari setelah tugas dibuat">Tepat waktu</th>
                        <th class="px-3 py-3 font-medium text-right" title="Dikumpulkan setelah tenggat">Terlambat</th>
                        <th class="px-3 py-3 font-medium">Klaster</th>
                        <th class="px-3 py-3 font-medium text-right" title="Skor risiko dari 0% sampai 100%">Risiko</th>
                        <th class="px-3 py-3 font-medium">Status EWS</th>
                        <th class="px-3 py-3 font-medium text-right">Engagement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($keaktifan['baris'] as $no => $mhs)
                        <tr @class([
                            'hover:bg-slate-100',
                            // Baris yang tidak pernah menyentuh LMS sama sekali
                            // diberi tanda, karena di situlah perhatian paling
                            // mendesak.
                            'bg-rose-50' => $mhs['course'] + $mhs['materi'] + $mhs['submission'] === 0,
                        ])>
                            <td class="px-3 py-2.5 text-center text-black">{{ $no + 1 }}</td>
                            <td class="px-3 py-2.5 font-mono text-[11px] text-black whitespace-nowrap">{{ $mhs['nim'] }}</td>
                            <td class="px-3 py-2.5 text-black whitespace-nowrap">{{ $mhs['nama'] }}</td>
                            <td class="px-3 py-2.5 text-black text-xs">{{ $mhs['kelas'] }}</td>
                            <td class="px-3 py-2.5 text-right text-black">{{ number_format($mhs['course'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right text-black">{{ number_format($mhs['materi'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right text-black">{{ number_format($mhs['forum'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right text-black">{{ number_format($mhs['submission'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5 text-right text-emerald-400">{{ $mhs['tepat'] }}</td>
                            <td @class([
                                'px-3 py-2.5 text-right',
                                'text-amber-400' => $mhs['terlambat'] > 0,
                                'text-black' => $mhs['terlambat'] === 0,
                            ])>{{ $mhs['terlambat'] }}</td>
                            <td class="px-3 py-2.5 text-black whitespace-nowrap">{{ $mhs['klaster'] ?? 'Belum dihitung' }}</td>
                            <td class="px-3 py-2.5 text-right text-black">
                                {{ $mhs['risk'] === null ? '—' : number_format($mhs['risk'] * 100, 1, ',', '.').'%' }}
                            </td>
                            <td class="px-3 py-2.5">
                                <span @class([
                                    'text-[11px] rounded-full border px-2 py-0.5 whitespace-nowrap',
                                    $warnaEws[$mhs['ews']['warna']],
                                ])>{{ $mhs['ews']['label'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right font-semibold text-black">
                                {{ $mhs['engagement'] === null ? '—' : round($mhs['engagement'] * 100).'%' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- -------------------------------------------------- catatan --}}
    <div class="grid gap-3 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-800 bg-white px-4 py-3 text-[11px] text-black flex items-start gap-2">
            <i class="fa-solid fa-circle-info mt-0.5 text-slate-500"></i>
            <span>
                <strong class="text-black">Kolom Forum tidak bisa dinilai.</strong>
                Log LMS yang diimpor tidak memuat event post forum sama sekali; yang
                tercatat hanya beberapa baris "Course module viewed" pada konteks Forum.
                Angka yang kecil di sana adalah keterbatasan ekspor log, bukan bukti mahasiswa tidak berdiskusi.
            </span>
        </div>
        <div class="rounded-xl border border-slate-800 bg-white px-4 py-3 text-[11px] text-black flex items-start gap-2">
            <i class="fa-solid fa-circle-info mt-0.5 text-slate-500"></i>
            <span>
                <strong class="text-black">Status EWS masih sementara.</strong>
                Angkanya diterjemahkan dari level risiko layanan analytics (At-Risk / Perhatian / Aman).
                Ambang resmi Early Warning belum ditetapkan — pengaturan ambang (FR-17) belum ada,
                jadi nilainya masih ditulis di <span class="font-mono">config/services.php</span>.
            </span>
        </div>
    </div>
@endif
