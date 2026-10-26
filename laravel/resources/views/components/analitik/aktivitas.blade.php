{{--
    Kartu aktivitas (login, akses materi, clustering) dan dua daftar materi
    untuk dashboard Dosen (FR-20).

    Angka disiapkan StatistikAktivitas; partial ini tidak menghitung apa pun.
    Semua donut dirender SVG di server (lihat components/analitik/donut),
    jadi aman berada di dalam tab yang tersembunyi.

    Tema: Modern Academic Analytics
      Navy #0F1939 · Deep Blue #26428B · Sapphire #516AC8 · Gold #E3AF64
      Cream #FAEBD7 · Off-white #F8F7F5
    Font: Bricolage Grotesque (heading section) · Plus Jakarta Sans (card, body, label, caption)
    Pastikan kedua font sudah dimuat di layout (Google Fonts atau @font-face).
--}}
@php
    /** @var array<string, mixed> $aktivitas */
    $a = $aktivitas ?? [];
    $login = $a['login'] ?? [];
    $materi = $a['materi'] ?? [];
    $risiko = $a['risiko'] ?? [];

    $angka = fn ($n, $desimal = 0) => $n === null ? '—' : number_format((float) $n, $desimal, ',', '.');

    $segmenLogin = [
        ['label' => 'Maksimal', 'nilai' => $login['maks'] ?? 0, 'warna' => '#26428B'],
        ['label' => 'Rata-rata', 'nilai' => $login['rata'] ?? 0, 'warna' => '#E3AF64'],
        ['label' => 'Minimal', 'nilai' => $login['min'] ?? 0, 'warna' => '#516AC8'],
    ];

    $segmenRisiko = [
        ['label' => 'Aktif (risiko rendah)', 'nilai' => $risiko['aktif'] ?? 0, 'warna' => '#516AC8'],
        ['label' => 'Sedang (risiko sedang)', 'nilai' => $risiko['sedang'] ?? 0, 'warna' => '#E3AF64'],
        ['label' => 'At Risk (risiko tinggi)', 'nilai' => $risiko['atRisk'] ?? 0, 'warna' => '#0F1939'],
    ];

    if (($risiko['belum'] ?? 0) > 0) {
        $segmenRisiko[] = ['label' => 'Belum dianalisis', 'nilai' => $risiko['belum'], 'warna' => '#CBD0DF'];
    }

    $totalRisiko = max(1, (int) ($risiko['total'] ?? 0));
    $persenMateri = $materi['persen'] ?? null;
    $minMateri = $materi['min'] ?? null;
    $maksMateri = $materi['maks'] ?? null;

    $daftarKartu = [
        [
            'judul' => 'Materi Pembelajaran yang Paling Sering Diakses',
            'keterangan' => 'Peringkat akses terbanyak',
            'daftar' => $a['materiSering'] ?? [],
            'warna' => 'bg-gradient-to-r from-[#26428B] to-[#516AC8]',
            'badge' => 'bg-[#0F1939] text-[#E3AF64]',
            'kosong' => 'Belum ada materi yang namanya diawali "Modul" atau "Materi".',
        ],
        [
            'judul' => 'Materi Pembelajaran yang Jarang Diakses',
            'keterangan' => 'Peringkat akses tersedikit',
            'daftar' => $a['materiJarang'] ?? [],
            'warna' => 'bg-[#E3AF64]',
            'badge' => 'bg-[#FAEBD7] text-[#0F1939]',
            'kosong' => 'Belum ada materi yang namanya diawali "Modul" atau "Materi".',
        ],
    ];
@endphp

<section class="space-y-4 font-['Plus_Jakarta_Sans'] text-sm text-[#0F1939]">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        {{-- Kartu 1: mahasiswa aktif (proxy login) per hari --}}
        <div class="rounded-2xl border border-[#EFE6D8] bg-white shadow-sm p-5">
            <h3 class="text-base font-bold text-[#0F1939]">Mahasiswa Login per Hari</h3>
            <p class="text-xs text-[#5D6788] mt-0.5">
                Mahasiswa beraktivitas di LMS · {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>

            <div class="flex items-center gap-4 mt-4">
                <x-analitik.donut :segmen="$segmenLogin" :pusat="$angka($login['rata'] ?? null, 1)"
                    bawah="mahasiswa/hari" ukuran="w-32 h-32" />

                <ul class="space-y-2 text-sm min-w-0">
                    @foreach ($segmenLogin as $s)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#5D6788]">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $s['warna'] }}"></span>
                                {{ $s['label'] }}
                            </span>
                            <span class="font-semibold text-[#0F1939]">{{ $angka($s['nilai'], $s['label'] === 'Rata-rata' ? 1 : 0) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-xs text-[#5D6788] mt-3 leading-relaxed">
                Dihitung dari {{ $angka($login['hari'] ?? 0) }} hari yang ada aktivitasnya.
                Log Moodle tidak mencatat event login, jadi aktivitas harian dipakai sebagai ukurannya.
            </p>
        </div>

        {{-- Kartu 2: rata-rata persentase mahasiswa yang membuka materi --}}
        <div class="rounded-2xl border border-[#EFE6D8] bg-white shadow-sm p-5">
            <h3 class="text-base font-bold text-[#0F1939]">Akses Materi</h3>
            <p class="text-xs text-[#5D6788] mt-0.5">
                Rata-rata persentase per kelas · {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>

            <div class="flex items-center gap-4 mt-4">
                <x-analitik.donut :pusat="$persenMateri === null ? '—' : $angka($persenMateri, 1).'%'"
                    :bawah="$persenMateri === null ? 'belum ada data' : 'dari '.$angka($materi['jumlahKelas'] ?? 0).' kelas'"
                    :segmen="$persenMateri === null ? [] : [
                        ['label' => 'Membuka materi', 'nilai' => $persenMateri, 'warna' => '#26428B'],
                        ['label' => 'Belum membuka', 'nilai' => 100 - $persenMateri, 'warna' => '#FAEBD7'],
                    ]"
                    ukuran="w-32 h-32" />

                <ul class="space-y-2 text-sm min-w-0">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-[#5D6788]">Rata-rata</span>
                        <span class="font-semibold text-[#0F1939]">{{ $persenMateri === null ? '—' : $angka($persenMateri, 1).'%' }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-[#5D6788]">Terendah</span>
                        <span class="font-semibold text-[#0F1939]">{{ $minMateri === null ? '—' : $angka($minMateri, 1).'%' }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-[#5D6788]">Tertinggi</span>
                        <span class="font-semibold text-[#0F1939]">{{ $maksMateri === null ? '—' : $angka($maksMateri, 1).'%' }}</span>
                    </li>
                </ul>
            </div>

            <p class="text-xs text-[#5D6788] mt-3 leading-relaxed">
                Tiap kelas dihitung sendiri (mahasiswa yang membuka minimal satu materi dibagi jumlah mahasiswa kelas),
                lalu dirata-ratakan supaya kelas besar tidak mendominasi.
            </p>
        </div>

        {{-- Kartu 3: sebaran level risiko (aktif / sedang / at risk) --}}
        <div class="rounded-2xl border border-[#EFE6D8] bg-white shadow-sm p-5">
            <h3 class="text-base font-bold text-[#0F1939]">Clustering Kelas</h3>
            <p class="text-xs text-[#5D6788] mt-0.5">
                Level risiko hasil analytics · {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>

            <div class="flex items-center gap-4 mt-4">
                <x-analitik.donut :segmen="$segmenRisiko" :pusat="$angka($risiko['total'] ?? 0)"
                    bawah="mahasiswa" ukuran="w-32 h-32" />

                <ul class="space-y-2 text-sm min-w-0">
                    @foreach ($segmenRisiko as $s)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#5D6788]">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $s['warna'] }}"></span>
                                {{ $s['label'] }}
                            </span>
                            <span class="font-semibold text-[#0F1939] whitespace-nowrap">
                                {{ $angka($s['nilai']) }}
                                <span class="font-normal text-[#5D6788]">({{ $angka($s['nilai'] / $totalRisiko * 100, 1) }}%)</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-xs text-[#5D6788] mt-3 leading-relaxed">
                Risiko rendah disebut Aktif, risiko sedang disebut Sedang, risiko tinggi disebut At Risk.
                Mahasiswa yang belum dianalisis tidak dihitung sebagai aman.
            </p>
        </div>
    </div>

    {{-- Judul section: Heading / Section = Bricolage Grotesque 700 24px --}}
    <div class="flex flex-wrap items-end justify-between gap-3 pt-2">
        <div>
            <h3 class="font-['Bricolage_Grotesque'] text-2xl font-bold tracking-tight text-[#0F1939]">Analytics Materi</h3>
            <p class="text-xs text-[#5D6788] mt-0.5">
                Hanya materi bernama "Modul ..." dan "Materi ..." ·
                {{ $angka($a['jumlahMateri'] ?? 0) }} materi ·
                {{ $angka($a['totalAkses'] ?? 0) }} akses ·
                {{ $a['periode'] ?? 'Seluruh periode data' }}
            </p>
        </div>
        <p class="text-xs text-[#5D6788]">
            Persentase = bagian dari total akses materi di atas · panjang bar = peringkat dalam daftarnya
        </p>
    </div>

    {{-- Dua daftar materi berdampingan di layar lebar, menumpuk di layar sempit. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        @foreach ($daftarKartu as $kartu)
            <div class="rounded-2xl border border-[#EFE6D8] bg-white shadow-sm overflow-hidden">
                <header class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 border-b border-[#EFE6D8]">
                    <h4 class="text-base font-bold text-[#0F1939]">{{ $kartu['judul'] }}</h4>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full bg-[#FAEBD7] text-[#0F1939] whitespace-nowrap">
                        {{ $kartu['keterangan'] }}
                    </span>
                </header>

                @if ($kartu['daftar'] === [])
                    <p class="px-5 py-6 text-xs text-[#5D6788]">{{ $kartu['kosong'] }}</p>
                @else
                    <ol class="divide-y divide-[#EFE6D8]">
                        @foreach ($kartu['daftar'] as $i => $m)
                            <li class="px-5 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="flex items-center gap-3 min-w-0">
                                        <span class="w-7 h-7 rounded-lg {{ $kartu['badge'] }} text-xs font-bold flex items-center justify-center shrink-0">
                                            {{ $i + 1 }}
                                        </span>
                                        <span class="text-sm font-semibold text-[#0F1939] truncate" title="{{ $m['nama'] }}">{{ $m['nama'] }}</span>
                                    </span>
                                    <span class="text-sm font-bold text-[#0F1939] whitespace-nowrap">
                                        {{ $angka($m['akses']) }} akses
                                        <span class="font-normal text-[#5D6788]">· {{ $angka($m['persen'], 1) }}%</span>
                                    </span>
                                </div>

                                <div class="mt-3 h-2 rounded-full bg-[#FAEBD7] overflow-hidden">
                                    <div class="h-full rounded-full {{ $kartu['warna'] }}" style="width: {{ $m['lebar'] }}%"></div>
                                </div>

                                <p class="text-xs text-[#5D6788] mt-2">{{ $angka($m['mhs']) }} mahasiswa membuka</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endforeach
    </div>
</section>
