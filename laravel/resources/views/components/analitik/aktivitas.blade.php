{{--
    Kartu aktivitas (login, akses materi, clustering) dan dua daftar materi
    untuk dashboard Dosen (FR-20).

    Angka disiapkan StatistikAktivitas; partial ini tidak menghitung apa pun.
    Semua donut dirender SVG di server (lihat components/analitik/donut),
    jadi aman berada di dalam tab yang tersembunyi.
--}}
@php
    /** @var array<string, mixed> $aktivitas */
    $a = $aktivitas ?? [];
    $login = $a['login'] ?? [];
    $materi = $a['materi'] ?? [];
    $risiko = $a['risiko'] ?? [];

    $angka = fn ($n, $desimal = 0) => $n === null ? '—' : number_format((float) $n, $desimal, ',', '.');

    $segmenLogin = [
        ['label' => 'Maksimal', 'nilai' => $login['maks'] ?? 0, 'warna' => '#6366f1'],
        ['label' => 'Rata-rata', 'nilai' => $login['rata'] ?? 0, 'warna' => '#10b981'],
        ['label' => 'Minimal', 'nilai' => $login['min'] ?? 0, 'warna' => '#f43f5e'],
    ];

    $segmenRisiko = [
        ['label' => 'Aktif (risiko rendah)', 'nilai' => $risiko['aktif'] ?? 0, 'warna' => '#10b981'],
        ['label' => 'Sedang (risiko sedang)', 'nilai' => $risiko['sedang'] ?? 0, 'warna' => '#f59e0b'],
        ['label' => 'At Risk (risiko tinggi)', 'nilai' => $risiko['atRisk'] ?? 0, 'warna' => '#f43f5e'],
    ];

    if (($risiko['belum'] ?? 0) > 0) {
        $segmenRisiko[] = ['label' => 'Belum dianalisis', 'nilai' => $risiko['belum'], 'warna' => '#475569'];
    }

    $totalRisiko = max(1, (int) ($risiko['total'] ?? 0));
    $persenMateri = $materi['persen'] ?? null;

    $daftarKartu = [
        [
            'judul' => 'Materi Pembelajaran yang Paling Sering Diakses',
            'keterangan' => 'Peringkat akses terbanyak',
            'daftar' => $a['materiSering'] ?? [],
            'warna' => 'bg-indigo-500',
            'kosong' => 'Belum ada materi yang namanya diawali "Modul" atau "Materi".',
        ],
        [
            'judul' => 'Materi Pembelajaran yang Jarang Diakses',
            'keterangan' => 'Peringkat akses tersedikit',
            'daftar' => $a['materiJarang'] ?? [],
            'warna' => 'bg-rose-500',
            'kosong' => 'Belum ada materi yang namanya diawali "Modul" atau "Materi".',
        ],
    ];
@endphp

<section class="space-y-4">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        {{-- Kartu 1: mahasiswa aktif (proxy login) per hari --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-5">
            <h3 class="text-sm font-semibold text-white">Mahasiswa Login per Hari</h3>
            <p class="text-[11px] text-slate-500 mt-0.5">
                Mahasiswa beraktivitas di LMS · {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>

            <div class="flex items-center gap-4 mt-4">
                <x-analitik.donut :segmen="$segmenLogin" :pusat="$angka($login['rata'] ?? null, 1)"
                    bawah="mahasiswa/hari" ukuran="w-32 h-32" />

                <ul class="space-y-2 text-xs min-w-0">
                    @foreach ($segmenLogin as $s)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-slate-400">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $s['warna'] }}"></span>
                                {{ $s['label'] }}
                            </span>
                            <span class="font-semibold text-slate-200">{{ $angka($s['nilai'], $s['label'] === 'Rata-rata' ? 1 : 0) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-[10px] text-slate-500 mt-3 leading-relaxed">
                Dihitung dari {{ $angka($login['hari'] ?? 0) }} hari yang ada aktivitasnya.
                Log Moodle tidak mencatat event login, jadi aktivitas harian dipakai sebagai ukurannya.
            </p>
        </div>

        {{-- Kartu 2: rata-rata persentase mahasiswa yang membuka materi --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-5">
            <h3 class="text-sm font-semibold text-white">Akses Materi</h3>
            <p class="text-[11px] text-slate-500 mt-0.5">
                Rata-rata persentase per kelas · {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>

            <div class="flex items-center gap-4 mt-4">
                <x-analitik.donut :pusat="$persenMateri === null ? '—' : $angka($persenMateri, 1).'%'"
                    :bawah="$persenMateri === null ? 'belum ada data' : 'dari '.$angka($materi['jumlahKelas'] ?? 0).' kelas'"
                    :segmen="$persenMateri === null ? [] : [
                        ['label' => 'Membuka materi', 'nilai' => $persenMateri, 'warna' => '#0ea5e9'],
                        ['label' => 'Belum membuka', 'nilai' => 100 - $persenMateri, 'warna' => '#334155'],
                    ]"
                    ukuran="w-32 h-32" />

                <ul class="space-y-2 text-xs min-w-0">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-400">Rata-rata</span>
                        <span class="font-semibold text-slate-200">{{ $persenMateri === null ? '—' : $angka($persenMateri, 1).'%' }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-400">Terendah</span>
                        <span class="font-semibold text-slate-200">{{ $materi['min'] === null ? '—' : $angka($materi['min'], 1).'%' }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-400">Tertinggi</span>
                        <span class="font-semibold text-slate-200">{{ $materi['maks'] === null ? '—' : $angka($materi['maks'], 1).'%' }}</span>
                    </li>
                </ul>
            </div>

            <p class="text-[10px] text-slate-500 mt-3 leading-relaxed">
                Tiap kelas dihitung sendiri (mahasiswa yang membuka minimal satu materi dibagi jumlah mahasiswa kelas),
                lalu dirata-ratakan supaya kelas besar tidak mendominasi.
            </p>
        </div>

        {{-- Kartu 3: sebaran level risiko (aktif / sedang / at risk) --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-5">
            <h3 class="text-sm font-semibold text-white">Clustering Kelas</h3>
            <p class="text-[11px] text-slate-500 mt-0.5">
                Level risiko hasil analytics · {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>

            <div class="flex items-center gap-4 mt-4">
                <x-analitik.donut :segmen="$segmenRisiko" :pusat="$angka($risiko['total'] ?? 0)"
                    bawah="mahasiswa" ukuran="w-32 h-32" />

                <ul class="space-y-2 text-xs min-w-0">
                    @foreach ($segmenRisiko as $s)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-slate-400">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $s['warna'] }}"></span>
                                {{ $s['label'] }}
                            </span>
                            <span class="font-semibold text-slate-200 whitespace-nowrap">
                                {{ $angka($s['nilai']) }}
                                <span class="font-normal text-slate-500">({{ $angka($s['nilai'] / $totalRisiko * 100, 1) }}%)</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-[10px] text-slate-500 mt-3 leading-relaxed">
                Risiko rendah disebut Aktif, risiko sedang disebut Sedang, risiko tinggi disebut At Risk.
                Mahasiswa yang belum dianalisis tidak dihitung sebagai aman.
            </p>
        </div>
    </div>

    {{-- Baris ketiga & keempat: dua daftar materi dengan progress bar. --}}
    <div class="flex flex-wrap items-end justify-between gap-3 pt-2">
        <div>
            <h3 class="text-lg font-bold text-white">Analytics Materi</h3>
            <p class="text-xs text-slate-400 mt-0.5">
                Hanya materi bernama "Modul ..." dan "Materi ..." ·
                {{ $angka($a['jumlahMateri'] ?? 0) }} materi ·
                {{ $angka($a['totalAkses'] ?? 0) }} akses ·
                {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>
        </div>
        <p class="text-[10px] text-slate-500">
            Persentase = bagian dari total akses materi di atas · panjang bar = peringkat dalam daftarnya
        </p>
    </div>

    @foreach ($daftarKartu as $kartu)
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 border-b border-slate-800">
                <h4 class="text-sm font-semibold text-white">{{ $kartu['judul'] }}</h4>
                <span class="text-[11px] text-slate-500">{{ $kartu['keterangan'] }}</span>
            </header>

            @if ($kartu['daftar'] === [])
                <p class="px-5 py-6 text-xs text-slate-500">{{ $kartu['kosong'] }}</p>
            @else
                <ol class="divide-y divide-slate-800/60">
                    @foreach ($kartu['daftar'] as $i => $m)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-3 min-w-0">
                                    <span class="w-6 h-6 rounded-md bg-slate-800 text-slate-300 text-[11px] font-semibold flex items-center justify-center shrink-0">
                                        {{ $i + 1 }}
                                    </span>
                                    <span class="text-sm text-slate-200 truncate" title="{{ $m['nama'] }}">{{ $m['nama'] }}</span>
                                </span>
                                <span class="text-xs text-slate-400 whitespace-nowrap">
                                    {{ $angka($m['akses']) }} akses
                                    <span class="text-slate-500">· {{ $angka($m['persen'], 1) }}%</span>
                                </span>
                            </div>

                            <div class="mt-2 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full {{ $kartu['warna'] }}" style="width: {{ $m['lebar'] }}%"></div>
                            </div>

                            <p class="text-[10px] text-slate-500 mt-1">{{ $angka($m['mhs']) }} mahasiswa membuka</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endforeach
</section>
