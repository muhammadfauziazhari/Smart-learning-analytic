{{--
    Donut chart murni SVG, tanpa library JavaScript.

    Kenapa bukan Chart.js: kartu-kartu ini berada di dalam tab yang
    tersembunyi. Canvas Chart.js yang digambar saat elemen masih `hidden`
    berukuran nol dan tidak otomatis digambar ulang saat tab dibuka.
    SVG yang dirender server selalu benar, tidak butuh resize, dan tidak
    menambah satu CDN lagi.

    Data yang dibutuhkan:
      $segmen  list<array{label: string, nilai: int|float, warna: string}>
      $pusat   teks besar di tengah (mis. "49,2")
      $bawah   teks kecil di bawah angka pusat (mis. "mahasiswa/hari")
      $ukuran  kelas Tailwind untuk lebar/tinggi (bawaan w-40 h-40)

    Rumus offset: lingkaran SVG mulai di jam 3 dan berjalan searah jarum
    jam. Angka 25 pada stroke-dashoffset memindahkan awal dash ke jam 12;
    sisanya menggeser tiap bagian tepat setelah bagian sebelumnya.
--}}
@props([
    'segmen' => [],
    'pusat' => null,
    'bawah' => null,
    'ukuran' => 'w-40 h-40',
])

@php
    $bagian = collect($segmen)
        ->filter(fn ($s) => ($s['nilai'] ?? 0) > 0)
        ->values();

    $total = (float) $bagian->sum('nilai');
    $total = $total > 0 ? $total : 1.0;
    $geser = 0.0;
@endphp

<div @class(['relative shrink-0', $ukuran])>
    <svg viewBox="0 0 42 42" class="w-full h-full" aria-hidden="true">
        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#1e293b" stroke-width="5"></circle>

        @foreach ($bagian as $s)
            @php
                $porsi = (float) $s['nilai'] / $total * 100;
                // Celah tipis antarbagian supaya batas warna terbaca,
                // tetapi bagian yang sangat kecil tidak sampai hilang.
                $tampil = $porsi > 2 ? $porsi - 0.8 : $porsi;
            @endphp
            <circle cx="21" cy="21" r="15.9155" fill="none"
                stroke="{{ $s['warna'] }}" stroke-width="5"
                stroke-dasharray="{{ round($tampil, 3) }} {{ round(100 - $tampil, 3) }}"
                stroke-dashoffset="{{ round(25 - $geser, 3) }}"></circle>
            @php $geser += $porsi; @endphp
        @endforeach
    </svg>

    <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6">
        <span class="text-2xl font-bold text-white leading-none">{{ $pusat ?? '—' }}</span>
        @if ($bawah)
            <span class="text-[10px] text-slate-500 mt-1 leading-tight">{{ $bawah }}</span>
        @endif
    </div>
</div>
