@extends('layouts.dosen')

@section('title', 'Dashboard Dosen - EduTrack')
@section('page-title', 'Dashboard')
@section('sidebar-active', $tabAktif ?? 'dashboard')

@section('content')
    {{--
        Konten: DASHBOARD (FR-20)

        Angka per kelas dihitung StatistikKelas, sama seperti yang dipakai
        Kaprodi, sehingga rumusnya tidak mungkin berbeda antar peran. Yang
        khas sisi Dosen ada di StatistikDosen: daftar kerjaan
        "perlu perhatian" dan asal akses tiap kelas.

        Tampilan: kartu terang di atas kanvas abu-abu muda. Komponen
        analitik.* masih bertema gelap, jadi dibungkus panel navy.
    --}}
    @php
        $engPersen = $ringkasan['rataEngagement'] === null ? null : round($ringkasan['rataEngagement'] * 100);
        $risikoPersen = ($ringkasan['mahasiswaUnik'] ?? 0) > 0
            ? round(($ringkasan['risikoTinggi'] / $ringkasan['mahasiswaUnik']) * 100)
            : 0;
        $adaFilter = $filter['jenis'] !== null || $filter['asal'] !== null || $filter['mata_kuliah'] !== null;
    @endphp

    <div id="content-dashboard" @class([
        'tab-content space-y-6 rounded-3xl bg-slate-100 p-5 lg:p-7',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'dashboard',
    ])>

        {{-- ------------------------------------------------ sapaan --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-[#0B1B3D]">
                    Halo, {{ auth()->user()?->name ?? 'Dosen' }} 👋
                </h2>
                <p class="text-sm text-blue-600 mt-1">
                    Berikut adalah ringkasan aktivitas pembelajaran kelas Anda.
                </p>
                <p class="text-[11px] text-slate-500 mt-1">
                    Hanya kelas yang Anda ampu atau yang mata kuliahnya diberi izin oleh Admin.
                    Analisis memakai satu semester penuh, sesuai dasar kalibrasi skor.
                </p>
            </div>
            <div class="flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600 shadow-sm">
                <i class="fa-regular fa-calendar text-slate-400"></i>
                {{ now()->translatedFormat('l, d F Y') }}
            </div>
        </div>

        @include('components.analitik.status-layanan')

        {{-- ------------------------------------------------ filter kelas --}}
        @if ($filter['pilihanJenis'] !== [] || $filter['pilihanAsal'] !== [])
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl bg-white border border-slate-200 p-4 shadow-sm" id="filterKelas">
                <input type="hidden" name="tab" value="{{ $tabAktif ?? 'dashboard' }}">

                @if ($filter['pilihanMataKuliah'] !== [])
                    <div class="min-w-[210px]">
                        <label for="filter-mk" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                            Mata kuliah
                        </label>
                        <select id="filter-mk" name="mata_kuliah"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:border-blue-600 focus:outline-none">
                            <option value="">Semua</option>
                            @foreach ($filter['pilihanMataKuliah'] as $mk)
                                <option value="{{ $mk['kode'] }}" @selected($filter['mata_kuliah'] === $mk['kode'])>
                                    {{ $mk['kode'] }} - {{ $mk['nama'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="min-w-[180px]">
                    <label for="filter-jenis" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                        Waktu
                    </label>
                    <select id="filter-jenis" name="jenis"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:border-blue-600 focus:outline-none">
                        <option value="">Semua</option>
                        @foreach ($filter['pilihanJenis'] as $jenis)
                            <option value="{{ $jenis }}" @selected($filter['jenis'] === $jenis)>{{ $jenis }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[160px]">
                    <label for="filter-asal" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                        Kelas asal
                    </label>
                    <select id="filter-asal" name="asal"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:border-blue-600 focus:outline-none">
                        <option value="">Semua</option>
                        @foreach ($filter['pilihanAsal'] as $asal)
                            <option value="{{ $asal }}" @selected($filter['asal'] === $asal)>{{ $asal }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-[#0F3277] hover:bg-[#0B255B] text-white transition-all">
                    <i class="fa-solid fa-filter mr-1.5"></i>Terapkan
                </button>

                @if ($adaFilter)
                    <a href="{{ route('dosen.dashboard', ['tab' => $tabAktif ?? 'dashboard']) }}"
                        class="px-4 py-2.5 rounded-xl text-xs text-slate-500 hover:text-slate-900 transition-all">
                        Reset
                    </a>
                    <span class="text-[11px] text-slate-500 self-center pb-1">
                        Menampilkan {{ count($kelas) }} dari {{ $filter['jumlahTanpaFilter'] }} kelas yang boleh Anda lihat.
                    </span>
                @endif
            </form>
        @endif

        {{-- ------------------------------------------------ kartu ringkasan --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Kelas diawasi (navy) --}}
            <div class="relative overflow-hidden rounded-2xl bg-[#0B1B3D] p-5 text-white shadow-md">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-blue-500/30 flex items-center justify-center">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-200">Kelas diawasi</p>
                </div>
                <p class="text-4xl font-extrabold mt-3">{{ $ringkasan['kelas'] }}</p>
                <i class="fa-solid fa-chalkboard absolute -bottom-2 right-3 text-6xl text-white/5"></i>
            </div>

            {{-- Mahasiswa unik (biru) --}}
            <div class="relative overflow-hidden rounded-2xl bg-blue-600 p-5 text-white shadow-md">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <p class="text-sm font-medium text-blue-50">Mahasiswa unik</p>
                </div>
                <p class="text-4xl font-extrabold mt-3">{{ number_format($ringkasan['mahasiswaUnik'], 0, ',', '.') }}</p>
                <i class="fa-solid fa-users absolute -bottom-2 right-3 text-6xl text-white/10"></i>
            </div>

            {{-- Rata-rata engagement (amber) --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-300 to-amber-400 p-5 text-[#0B1B3D] shadow-md">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-white/50 flex items-center justify-center">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <p class="text-sm font-semibold">Rata-rata engagement</p>
                </div>
                <p class="text-4xl font-extrabold mt-3">{{ $engPersen === null ? '—' : $engPersen.'%' }}</p>
                <i class="fa-solid fa-arrow-trend-up absolute -bottom-2 right-3 text-6xl text-white/30"></i>
            </div>

            {{-- Risiko tinggi --}}
            <div @class([
                'relative overflow-hidden rounded-2xl p-5 shadow-md border',
                'bg-rose-50 border-rose-200 text-rose-700' => $ringkasan['risikoTinggi'] > 0,
                'bg-blue-50 border-blue-100 text-[#0B1B3D]' => $ringkasan['risikoTinggi'] <= 0,
            ])>
                <div class="flex items-center gap-3">
                    <div @class([
                        'w-11 h-11 rounded-full flex items-center justify-center text-white',
                        'bg-rose-500' => $ringkasan['risikoTinggi'] > 0,
                        'bg-blue-500' => $ringkasan['risikoTinggi'] <= 0,
                    ])>
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <p class="text-sm font-semibold">Risiko tinggi</p>
                </div>
                <p class="text-4xl font-extrabold mt-3">
                    {{ number_format($ringkasan['risikoTinggi'], 0, ',', '.') }}
                    <span class="text-sm font-semibold opacity-70">mahasiswa</span>
                </p>
            </div>
        </div>

        {{-- ------------------------------------------------ donut + insight --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Donut engagement --}}
            <section class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
                <h3 class="text-sm font-bold text-[#0B1B3D]">Ringkasan Engagement</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Rata-rata seluruh kelas yang tampil.</p>

                <div class="flex flex-wrap items-center gap-8 mt-5">
                    <div class="relative w-40 h-40 rounded-full shrink-0"
                        style="background: conic-gradient(#0F3C8A {{ ($engPersen ?? 0) * 3.6 }}deg, #E2E8F0 0deg);">
                        <div class="absolute inset-4 rounded-full bg-white flex flex-col items-center justify-center">
                            <span class="text-2xl font-extrabold text-[#0B1B3D]">{{ $engPersen === null ? '—' : $engPersen.'%' }}</span>
                            <span class="text-[10px] text-slate-500">Rata-rata<br>Engagement</span>
                        </div>
                    </div>

                    <div class="flex-1 min-w-[220px] space-y-4">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-600 font-medium">Engagement</span>
                                <span class="font-bold text-[#0B1B3D]">{{ $engPersen === null ? '—' : $engPersen.'%' }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full bg-[#0F3C8A]" style="width: {{ $engPersen ?? 0 }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-600 font-medium">Mahasiswa risiko tinggi</span>
                                <span class="font-bold text-rose-600">{{ $risikoPersen }}%</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full bg-rose-500" style="width: {{ $risikoPersen }}%"></div>
                            </div>
                        </div>
                        @if (($aktivitas['totalAkses'] ?? 0) > 0)
                            <div class="flex justify-between text-xs pt-1">
                                <span class="text-slate-600 font-medium">Total akses materi</span>
                                <span class="font-bold text-[#0B1B3D]">{{ number_format($aktivitas['totalAkses'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if (($aktivitas['login']['hari'] ?? 0) > 0)
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600 font-medium">Hari login tercatat</span>
                                <span class="font-bold text-[#0B1B3D]">{{ number_format($aktivitas['login']['hari'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Insight --}}
            <section class="rounded-2xl bg-amber-50 border border-amber-200 p-5 shadow-sm flex flex-col">
                <div class="flex items-center gap-2">
                    <i class="fa-regular fa-lightbulb text-xl text-amber-500"></i>
                    <h3 class="text-sm font-bold text-[#0B1B3D]">Insight</h3>
                </div>
                <p class="text-xs leading-relaxed text-slate-600 mt-3 flex-1">
                    @if ($ringkasan['risikoTinggi'] > 0)
                        Ada {{ number_format($ringkasan['risikoTinggi'], 0, ',', '.') }} mahasiswa berisiko tinggi
                        ({{ $risikoPersen }}% dari mahasiswa unik). Tinjau daftar perlu perhatian dan
                        pertimbangkan menghubungi mereka lebih awal.
                    @elseif ($engPersen !== null)
                        Tidak ada mahasiswa berisiko tinggi. Rata-rata engagement berada di {{ $engPersen }}%,
                        pertahankan ritme materi dan tugas yang sekarang.
                    @else
                        Skor engagement belum tersedia untuk kelas yang tampil.
                    @endif
                </p>
                <a href="{{ route('dosen.dashboard', ['tab' => 'ews']) }}"
                    class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-[#0F3277] hover:bg-[#0B255B] px-4 py-2.5 text-xs font-semibold text-white transition-all">
                    Lihat Rekomendasi <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </section>
        </div>

        {{-- Komponen aktivitas masih bertema gelap: dibungkus panel navy --}}
        @if (($aktivitas['totalAkses'] ?? 0) > 0 || ($aktivitas['login']['hari'] ?? 0) > 0)
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                @include('components.analitik.aktivitas', ['aktivitas' => $aktivitas])
            </div>
        @endif

        @if ($kelas === [])
            <div class="border-2 border-dashed border-slate-300 rounded-2xl p-12 text-center text-slate-500 bg-white">
                <i class="fa-solid fa-user-tie mb-2 text-3xl text-slate-400"></i>
                @if ($adaFilter)
                    <p class="text-sm font-medium">Tidak ada kelas yang cocok dengan filter ini.</p>
                    <p class="text-xs mt-1">Coba kembalikan ke "Semua", atau periksa apakah kelas itu memang boleh Anda lihat.</p>
                @else
                    <p class="text-sm font-medium">Belum ada kelas yang bisa Anda lihat.</p>
                    <p class="text-xs mt-1">
                        Minta Admin menugaskan Anda di menu Penugasan Akademik, atau memberi izin mata kuliah
                        di menu Kelola Akun. Dashboard ini hanya menampilkan kelas penugasan dan mata kuliah berizin.
                    </p>
                @endif
            </div>
        @endif

        {{-- ------------------------------------------------ daftar kerjaan --}}
        @if ($perluPerhatian !== [])
            <section class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
                <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-[#0B1B3D]">
                            <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-1.5"></i>
                            Perlu perhatian
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            {{ $jumlahPerluPerhatian }} mahasiswa berisiko sedang atau tinggi, diurut dari paling tinggi.
                            @if ($jumlahPerluPerhatian > $jumlahTampilPerluPerhatian)
                                Menampilkan {{ $jumlahTampilPerluPerhatian }} teratas.
                            @endif
                        </p>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 font-medium">Mahasiswa</th>
                                <th class="px-3 py-3 font-medium">Kelas</th>
                                <th class="px-3 py-3 font-medium text-right">Event</th>
                                <th class="px-3 py-3 font-medium text-right">Hari</th>
                                <th class="px-3 py-3 font-medium text-right">Tugas</th>
                                <th class="px-3 py-3 font-medium text-right">Engagement</th>
                                <th class="px-5 py-3 font-medium">Risiko</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($perluPerhatian as $m)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-2.5">
                                        <span class="font-medium text-slate-800">{{ $m['nama'] }}</span>
                                        <span class="block font-mono text-[11px] text-slate-400">{{ $m['nim'] }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-500 text-xs">{{ $m['kelas'] }}</td>
                                    <td class="px-3 py-2.5 text-right text-slate-700">{{ number_format($m['event'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2.5 text-right text-slate-500">{{ $m['hari'] }}</td>
                                    <td class="px-3 py-2.5 text-right text-slate-500">{{ $m['tugas'] }}</td>
                                    <td @class([
                                        'px-3 py-2.5 text-right font-semibold',
                                        'text-slate-400' => $m['engagement'] === null,
                                        'text-emerald-600' => $m['engagement'] !== null && $m['engagement'] >= 0.5,
                                        'text-rose-600' => $m['engagement'] !== null && $m['engagement'] < 0.5,
                                    ])>
                                        {{ $m['engagement'] === null ? '—' : round($m['engagement'] * 100).'%' }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        {{-- @class, bukan ekspresi {{ }} multi-baris:
                                             Blade gagal mengompilasinya jadi PHP. --}}
                                        <span @class([
                                            'text-[11px] font-medium rounded-full border px-2 py-0.5',
                                            'bg-rose-50 text-rose-600 border-rose-200' => ($m['level'] ?? '') === 'high',
                                            'bg-amber-50 text-amber-600 border-amber-200' => ($m['level'] ?? '') !== 'high',
                                        ])>
                                            {{ $m['level'] }} · {{ round(($m['risk'] ?? 0) * 100).'%' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @elseif ($kelas !== [] && ! $analisisAda)
            {{-- Daftar kosong karena skor tidak pernah dihitung, bukan karena aman --}}
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs text-amber-700 flex items-start gap-2">
                <i class="fa-solid fa-circle-info mt-0.5"></i>
                <span>
                    Daftar mahasiswa yang perlu perhatian belum bisa disusun karena skor risiko belum dihitung
                    (FR-12a). Angka mentah di bawah tetap terbaca.
                </span>
            </div>
        @endif

        {{-- ------------------------------------------------ per kelas --}}
        {{-- blok-kelas masih bertema gelap: dibungkus panel navy --}}
        @if ($kelas !== [])
            <section class="rounded-2xl bg-white p-5 shadow-sm space-y-6">
                <h3 class="text-sm font-bold text-white">Performa Kelas</h3>
                @foreach ($kelas as $k)
                    @include('components.analitik.blok-kelas', ['k' => $k, 'tagna' => $k['sumberAkses']])
                @endforeach
            </section>
        @endif
    </div>

    <!-- Konten: PENGUMPULAN TUGAS -->
    <div id="content-pengumpulan-tugas" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'pengumpulan-tugas',
    ])>
        @include('Dosen.PengumpulanTugas')
    </div>

    <!-- Konten: KEAKTIFAN -->
    <div id="content-keaktifan" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'keaktifan',
    ])>
        @include('Dosen.Keaktifan')
    </div>

    <!-- Konten: EWS -->
    <div id="content-ews" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'ews',
    ])>
        @include('Dosen.Ews')
    </div>

    <!-- Konten: SETTINGS -->
    <div id="content-settings" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'settings',
    ])>
        @include('Dosen.Settings')
    </div>
@endsection

@section('scripts')
    {{-- Tab awal ditetapkan server, jadi harus di-set SEBELUM tab-switcher
         dipanggil. Dipakai agar halaman kembali ke tab Settings setelah
         form ganti password dikirim (?tab=settings). --}}
    <script>window.tabAwal = @json($tabAktif ?? 'dashboard');</script>
    @include('partials.tab-switcher')
@endsection
