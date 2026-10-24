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
    --}}
    <div id="content-dashboard" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'dashboard',
    ])>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-white">Statistik Analitik Kelas Saya</h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Hanya kelas yang Anda ampu atau yang mata kuliahnya diberi izin oleh Admin.
                    Analisis memakai satu semester penuh, sesuai dasar kalibrasi skor.
                </p>
            </div>
        </div>

        @include('components.analitik.status-layanan')

        {{-- ------------------------------------------------ filter kelas --}}
        @if ($filter['pilihanJenis'] !== [] || $filter['pilihanAsal'] !== [])
            <form method="GET" class="flex flex-wrap items-end gap-3" id="filterKelas">
                <input type="hidden" name="tab" value="{{ $tabAktif ?? 'dashboard' }}">

                @if ($filter['pilihanMataKuliah'] !== [])
                    <div class="min-w-[210px]">
                        <label for="filter-mk" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                            Mata kuliah
                        </label>
                        <select id="filter-mk" name="mata_kuliah"
                            class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
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
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
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
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                        <option value="">Semua</option>
                        @foreach ($filter['pilihanAsal'] as $asal)
                            <option value="{{ $asal }}" @selected($filter['asal'] === $asal)>{{ $asal }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white transition-all">
                    <i class="fa-solid fa-filter mr-1.5"></i>Terapkan
                </button>

                @if ($filter['jenis'] !== null || $filter['asal'] !== null || $filter['mata_kuliah'] !== null)
                    <a href="{{ route('dosen.dashboard', ['tab' => $tabAktif ?? 'dashboard']) }}"
                        class="px-4 py-2.5 rounded-xl text-xs text-slate-400 hover:text-white transition-all">
                        Reset
                    </a>
                    <span class="text-[11px] text-slate-500 self-center pb-1">
                        Menampilkan {{ count($kelas) }} dari {{ $filter['jumlahTanpaFilter'] }} kelas yang boleh Anda lihat.
                    </span>
                @endif
            </form>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Kelas diawasi</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $ringkasan['kelas'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Mahasiswa unik</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($ringkasan['mahasiswaUnik'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Rata-rata engagement</p>
                <p class="text-2xl font-bold text-white mt-1">
                    {{ $ringkasan['rataEngagement'] === null ? '—' : round($ringkasan['rataEngagement'] * 100).'%' }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Risiko tinggi</p>
                <p class="text-2xl font-bold {{ $ringkasan['risikoTinggi'] > 0 ? 'text-rose-400' : 'text-white' }} mt-1">
                    {{ number_format($ringkasan['risikoTinggi'], 0, ',', '.') }} mahasiswa
                </p>
            </div>
        </div>

        @if (($aktivitas['totalAkses'] ?? 0) > 0 || ($aktivitas['login']['hari'] ?? 0) > 0)
            @include('components.analitik.aktivitas', ['aktivitas' => $aktivitas])
        @endif

        @if ($kelas === [])
            <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
                <i class="fa-solid fa-user-tie mb-2 text-3xl"></i>
                @if ($filter['jenis'] !== null || $filter['asal'] !== null || $filter['mata_kuliah'] !== null)
                    <p class="text-sm">Tidak ada kelas yang cocok dengan filter ini.</p>
                    <p class="text-xs mt-1">Coba kembalikan ke "Semua", atau periksa apakah kelas itu memang boleh Anda lihat.</p>
                @else
                    <p class="text-sm">Belum ada kelas yang bisa Anda lihat.</p>
                    <p class="text-xs mt-1">
                        Minta Admin menugaskan Anda di menu Penugasan Akademik, atau memberi izin mata kuliah
                        di menu Kelola Akun. Dashboard ini hanya menampilkan kelas penugasan dan mata kuliah berizin.
                    </p>
                @endif
            </div>
        @endif

        {{-- ------------------------------------------------ daftar kerjaan --}}
        @if ($perluPerhatian !== [])
            <section class="rounded-2xl border border-rose-500/25 bg-rose-500/5 overflow-hidden">
                <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-rose-500/20">
                    <div>
                        <h3 class="text-sm font-semibold text-white">
                            <i class="fa-solid fa-triangle-exclamation text-rose-400 mr-1.5"></i>
                            Perlu perhatian
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            {{ $jumlahPerluPerhatian }} mahasiswa berisiko sedang atau tinggi, diurut dari paling tinggi.
                            @if ($jumlahPerluPerhatian > $jumlahTampilPerluPerhatian)
                                Menampilkan {{ $jumlahTampilPerluPerhatian }} teratas.
                            @endif
                        </p>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-900/60">
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
                        <tbody class="divide-y divide-rose-500/10">
                            @foreach ($perluPerhatian as $m)
                                <tr class="hover:bg-rose-500/5">
                                    <td class="px-5 py-2.5">
                                        <span class="text-slate-200">{{ $m['nama'] }}</span>
                                        <span class="block font-mono text-[11px] text-slate-500">{{ $m['nim'] }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-400 text-xs">{{ $m['kelas'] }}</td>
                                    <td class="px-3 py-2.5 text-right text-slate-300">{{ number_format($m['event'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2.5 text-right text-slate-400">{{ $m['hari'] }}</td>
                                    <td class="px-3 py-2.5 text-right text-slate-400">{{ $m['tugas'] }}</td>
                                    <td class="px-3 py-2.5 text-right font-semibold
                                        {{ $m['engagement'] === null ? 'text-slate-600' : ($m['engagement'] >= 0.5 ? 'text-emerald-400' : 'text-rose-400') }}">
                                        {{ $m['engagement'] === null ? '—' : round($m['engagement'] * 100).'%' }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        {{-- @class, bukan ekspresi {{ }} multi-baris:
                                             Blade gagal mengompilasinya jadi PHP. --}}
                                        <span @class([
                                            'text-[11px] rounded-full border px-2 py-0.5',
                                            'bg-rose-500/10 text-rose-400 border-rose-500/30' => ($m['level'] ?? '') === 'high',
                                            'bg-amber-500/10 text-amber-400 border-amber-500/30' => ($m['level'] ?? '') !== 'high',
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
            <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 px-4 py-3 text-xs text-amber-400 flex items-start gap-2">
                <i class="fa-solid fa-circle-info mt-0.5"></i>
                <span>
                    Daftar mahasiswa yang perlu perhatian belum bisa disusun karena skor risiko belum dihitung
                    (FR-12a). Angka mentah di bawah tetap terbaca.
                </span>
            </div>
        @endif

        {{-- ------------------------------------------------ per kelas --}}
        @foreach ($kelas as $k)
            @include('components.analitik.blok-kelas', ['k' => $k, 'tagna' => $k['sumberAkses']])
        @endforeach
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