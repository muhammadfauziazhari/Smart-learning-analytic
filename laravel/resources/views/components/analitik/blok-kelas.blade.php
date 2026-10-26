{{--
    Satu blok statistik untuk satu kelas: judul, sebaran bulanan, dan tabel
    mahasiswa. Dipakai dashboard Dosen (FR-20) dan Kaprodi (FR-21) karena
    keduanya menampilkan angka yang sama (lihat StatistikKelas).

    Data yang dibutuhkan:
      $k    array statistik kelas dari StatistikKelas::untukKelas()
      $tagna menampilkan asal akses kelas (khusus Dosen: penugasan atau izin)

    Tanpa variabel, blok ini tetap aman untuk di-@include.
--}}
@php
    /** @var array<string, mixed> $k */
    $k = $k ?? [];

    $badgeRisiko = [
        'low' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
        'medium' => 'bg-amber-50 text-amber-600 border-amber-200',
        'high' => 'bg-rose-50 text-rose-600 border-rose-200',
    ];

    $tagna = $tagna ?? null;
@endphp

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
        <div>
            {{-- Judul dua lapis: mata kuliah di atas, kelas yang sedang
                 dibuka di bawah. Tanpa baris kedua, semua blok kelas
                 terlihat sama dan pengguna tidak tahu sedang melihat
                 kelas pagi atau malam. --}}
            <h3 class="text-sm font-semibold text-[#0B1B3D]">
                {{ $k['kelasNama'] ?? $k['nama'] ?? 'Tanpa mata kuliah' }}
                @if (! empty($k['kelasKode']) && ! str_contains((string) ($k['kelasNama'] ?? ''), (string) $k['kelasKode']))
                    <span class="ml-1 text-xs font-normal text-slate-500">{{ $k['kelasKode'] }}</span>
                @endif
            </h3>
            <p class="text-[11px] text-slate-500 mt-0.5">
                {{ $k['nama'] ?? '' }}
                @if (! empty($k['nama']))
                    <span class="font-mono">({{ $k['kode'] }})</span>
                @endif
            </p>
            <p class="text-[11px] text-slate-500 mt-0.5">
                @if ($tagna !== null)
                    <span class="text-blue-600">{{ $tagna }}</span> ·
                @endif
                {{ $k['periode'] ?? 'Belum ada data log' }} ·
                {{ number_format($k['terdaftar'] ?? 0, 0, ',', '.') }} mahasiswa terdaftar ·
                {{ number_format($k['terpantau'] ?? 0, 0, ',', '.') }} aktif ·
                {{ number_format($k['totalEvent'] ?? 0, 0, ',', '.') }} baris log
                @if (($k['diLuarDaftar'] ?? 0) > 0)
                    · <span class="text-amber-600">{{ number_format($k['diLuarDaftar'], 0, ',', '.') }} identitas di luar daftar</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @forelse ($k['klaster'] ?? [] as $c)
                <span class="text-[11px] rounded-full border border-blue-200 bg-blue-50 text-blue-700 px-2.5 py-1">
                    {{ $c['label'] }}: {{ $c['jumlah'] }}
                </span>
            @empty
                <span class="text-[11px] text-slate-500">Klaster tidak tersedia</span>
            @endforelse
        </div>
    </header>

    @if (! ($k['analisisAda'] ?? false) && ! empty($k['peringatan']))
        <p class="px-5 py-2 text-[11px] text-amber-600 bg-amber-50 border-b border-slate-100">
            {{ $k['peringatan'] }}
        </p>
    @endif

    {{-- Sebaran aktivitas per bulan dalam jendela analisis --}}
    @if (($k['perBulan'] ?? []) !== [])
        @php $puncakBulan = max(array_column($k['perBulan'], 'event')); @endphp
        <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-end gap-2">
            @foreach ($k['perBulan'] as $b)
                <div class="flex flex-col items-center gap-1 w-14" title="{{ $b['periode'] }}: {{ number_format($b['event'], 0, ',', '.') }} baris">
                    <div class="w-full rounded-t bg-[#0F3C8A]/80"
                         style="height: {{ max(3, (int) round($b['event'] / max(1, $puncakBulan) * 44)) }}px"></div>
                    <span class="text-[10px] text-slate-500">{{ $b['label'] }}</span>
                    <span class="text-[10px] text-slate-400">{{ number_format($b['event'], 0, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="max-h-[26rem] overflow-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-[11px] uppercase tracking-wider text-slate-500 sticky top-0 bg-slate-50">
                <tr>
                    <th class="px-5 py-3 font-medium">Mahasiswa</th>
                    <th class="px-3 py-3 font-medium">NIM</th>
                    <th class="px-3 py-3 font-medium text-right">Event</th>
                    <th class="px-3 py-3 font-medium text-right">Hari</th>
                    <th class="px-3 py-3 font-medium text-right">Tugas</th>
                    <th class="px-3 py-3 font-medium text-right">Jenis</th>
                    <th class="px-3 py-3 font-medium text-right">Kuis ditolak</th>
                    <th class="px-3 py-3 font-medium text-right">Engagement</th>
                    <th class="px-3 py-3 font-medium">Klaster</th>
                    <th class="px-5 py-3 font-medium">Risiko</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($k['mahasiswa'] ?? [] as $m)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-2.5 text-slate-800">{{ $m['nama'] }}</td>
                        <td class="px-3 py-2.5 text-slate-500 font-mono text-xs">{{ $m['nim'] }}</td>
                        <td class="px-3 py-2.5 text-right text-slate-600">{{ number_format($m['event'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2.5 text-right text-slate-500">{{ $m['hari'] }}</td>
                        <td class="px-3 py-2.5 text-right text-slate-500">{{ $m['tugas'] }}</td>
                        <td class="px-3 py-2.5 text-right text-slate-500">{{ $m['jenis'] }}</td>
                        <td class="px-3 py-2.5 text-right {{ $m['kuis'] > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ $m['kuis'] }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold
                            {{ $m['engagement'] === null ? 'text-slate-400' : ($m['engagement'] >= 0.5 ? 'text-emerald-600' : 'text-rose-600') }}">
                            {{ $m['engagement'] === null ? '—' : round($m['engagement'] * 100).'%' }}
                        </td>
                        <td class="px-3 py-2.5 text-slate-500">{{ $m['klaster'] ?? '—' }}</td>
                        <td class="px-5 py-2.5">
                            @if ($m['level'] === null)
                                <span class="text-slate-400">—</span>
                            @else
                                <span class="text-[11px] rounded-full border px-2 py-0.5 {{ $badgeRisiko[$m['level']] ?? 'bg-slate-100 text-slate-500 border-slate-200' }}">
                                    {{ $m['level'] }} · {{ round($m['risk'] * 100).'%' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-5 py-10 text-center text-slate-400">
                            <i class="fa-solid fa-user-slash mb-2"></i>
                            <p class="text-sm">Belum ada daftar mahasiswa di kelas ini untuk periode yang sedang dipilih.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
