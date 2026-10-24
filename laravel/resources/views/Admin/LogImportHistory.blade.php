@extends('layouts.admin')

@section('title', 'Riwayat Import Log - EduTrack')
@section('sidebar-active', 'uploadlog')
@section('page-title', 'Riwayat Import')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white">Riwayat Import Log LMS</h2>
            <p class="text-xs text-slate-400 mt-0.5">
                Setiap file CSV yang diupload tercatat di sini beserta jumlah baris tersimpan, duplikat, dan gagal.
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}"
            class="text-xs font-medium text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Upload Log
        </a>
    </div>

    {{-- Notifikasi hasil import --}}
    @include('partials.alert')

    @if ($batches->isEmpty())
        <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
            <i class="fa-solid fa-inbox text-3xl mb-2"></i>
            <p class="text-sm">Belum ada file yang diimport.</p>
        </div>
    @else
        <div class="rounded-2xl border border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-900 text-slate-400 uppercase tracking-wider">
                        <tr>
                            <th class="text-left font-medium px-5 py-3">File</th>
                            <th class="text-left font-medium px-5 py-3">Mata Kuliah</th>
                            <th class="text-left font-medium px-5 py-3">Baris</th>
                            <th class="text-left font-medium px-5 py-3">Tersimpan</th>
                            <th class="text-left font-medium px-5 py-3">Duplikat</th>
                            <th class="text-left font-medium px-5 py-3">Gagal</th>
                            <th class="text-left font-medium px-5 py-3">Dikenal</th>
                            <th class="text-left font-medium px-5 py-3">Waktu</th>
                            <th class="text-left font-medium px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-slate-300">
                        @foreach ($batches as $batch)
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-file-csv text-slate-500"></i>
                                        <span class="truncate max-w-[220px]" title="{{ $batch->nama_file }}">
                                            {{ $batch->nama_file }}
                                        </span>
                                        @if ($batch->path)
                                            <a href="{{ route('admin.logs.download', $batch) }}"
                                                class="text-slate-500 hover:text-slate-300" title="Unduh file asli">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($batch->kode_mata_kuliah)
                                        <span class="font-mono text-indigo-300">{{ $batch->kode_mata_kuliah }}</span>
                                        <span class="text-slate-500">{{ $batch->nama_mata_kuliah }}</span>
                                    @else
                                        <span class="text-slate-600">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ number_format($batch->jumlah_baris, 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-emerald-400">
                                    {{ number_format($batch->jumlah_berhasil, 0, ',', '.') }}
                                    <span class="text-slate-600">({{ $batch->persenBerhasil() }}%)</span>
                                </td>
                                <td class="px-5 py-3 text-amber-400/80">{{ number_format($batch->jumlah_duplikat, 0, ',', '.') }}</td>
                                <td class="px-5 py-3 {{ $batch->jumlah_gagal > 0 ? 'text-red-400' : 'text-slate-600' }}">
                                    {{ number_format($batch->jumlah_gagal, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-emerald-400">{{ $batch->jumlah_terkenal }}</span>
                                    <span class="text-slate-600">/ {{ $batch->jumlah_berhasil }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-400">
                                    {{ $batch->finished_at?->translatedFormat('d M Y H:i') ?? $batch->created_at->translatedFormat('d M Y H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'px-2 py-1 rounded-md text-[10px] uppercase tracking-wider',
                                        'bg-emerald-500/10 text-emerald-400' => $batch->status === 'berhasil',
                                        'bg-red-500/10 text-red-400' => $batch->status === 'gagal',
                                        'bg-amber-500/10 text-amber-400' => in_array($batch->status, ['menunggu', 'diproses'], true),
                                    ])>{{ $batch->status }}</span>
                                </td>
                            </tr>

                            @if ($batch->pesan)
                                <tr class="bg-slate-900/40">
                                    <td colspan="9" class="px-5 py-2 text-[11px] text-slate-500">
                                        {{ $batch->pesan }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            {{ $batches->links() }}
        </div>
    @endif

    {{-- Penjelasan angka pada tabel --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-5">
        <p class="text-xs font-semibold text-slate-300 mb-2">Cara membaca angka di atas</p>
        <ul class="text-[11px] text-slate-500 space-y-1 list-disc pl-4">
            <li><strong>Tersimpan</strong> - baris yang berhasil masuk ke tabel <code>log_aktivitas</code>.</li>
            <li><strong>Duplikat</strong> - baris yang isinya identik persis dengan baris sebelumnya di file yang sama.</li>
            <li><strong>Gagal</strong> - baris tidak bisa dibaca, misalnya format waktu rusak. Detailnya ada di tabel <code>log_import_errors</code>.</li>
            <li><strong>Dikenal</strong> - banyaknya identitas yang cocok dengan akun di sistem. Sisanya perlu ditambah dulu oleh Admin.</li>
        </ul>
    </div>

</div>
@endsection