@extends('layouts.kaprodi')

@section('title', 'Dashboard Kaprodi - EduTrack')
@section('page-title', 'Dashboard')
@section('sidebar-active', $tabAktif ?? 'dashboard')

@section('content')
    <!-- Konten: DASHBOARD -->
    <div id="content-dashboard" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'dashboard',
    ])>
        {{-- ------------------------------------------------ header --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-navy">Statistik Analitik Mahasiswa</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Rekap seluruh mata kuliah dari log LMS yang telah diimpor.
                    Analisis memakai satu semester penuh, sesuai dasar kalibrasi skor.
                </p>
            </div>
        </div>

        {{-- ------------------------------------------------ status layanan --}}
        @include('components.analitik.status-layanan')

        {{-- ------------------------------------------------ kartu ringkasan --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Mata kuliah diawasi</p>
                <p class="text-2xl font-bold text-navy mt-1">{{ $ringkasan['kelas'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Mahasiswa unik</p>
                <p class="text-2xl font-bold text-navy mt-1">{{ number_format($ringkasan['mahasiswaUnik'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Rata-rata engagement</p>
                <p class="text-2xl font-bold text-navy mt-1">
                    {{ $ringkasan['rataEngagement'] === null ? '—' : round($ringkasan['rataEngagement'] * 100).'%' }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Risiko tinggi</p>
                <p class="text-2xl font-bold {{ $ringkasan['risikoTinggi'] > 0 ? 'text-rose-600' : 'text-navy' }} mt-1">
                    {{ number_format($ringkasan['risikoTinggi'], 0, ',', '.') }} mahasiswa
                </p>
            </div>
        </div>

        @if ($kelas === [])
            <div class="border-2 border-dashed border-slate-300 rounded-2xl p-12 text-center text-slate-500 bg-white">
                <i class="fa-solid fa-table-columns text-3xl mb-2"></i>
                <p class="text-sm">Belum ada kelas di sistem. Impor log LMS dan susun datanya terlebih dahulu.</p>
            </div>
        @endif

        {{-- ------------------------------------------------ per kelas --}}
        @foreach ($kelas as $k)
            @include('components.analitik.blok-kelas', ['k' => $k])
        @endforeach
    </div>

    <!-- Konten: PENGUMPULAN TUGAS -->
    <div id="content-pengumpulan-tugas" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'pengumpulan-tugas',
    ])>
        @include('Kaprodi.PengumpulanTugas')
    </div>

    <!-- Konten: KEAKTIFAN -->
    <div id="content-keaktifan" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'keaktifan',
    ])>
        @include('Kaprodi.Keaktifan')
    </div>

    <!-- Konten: EWS -->
    <div id="content-ews" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'ews',
    ])>
        @include('Kaprodi.Ews')
    </div>

    <!-- Konten: SETTINGS -->
    <div id="content-settings" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'settings',
    ])>
        @include('Kaprodi.Settings')
    </div>
@endsection

@section('scripts')
    {{-- Tab awal ditetapkan server, jadi harus di-set SEBELUM tab-switcher
         dipanggil. Dipakai agar halaman kembali ke tab Settings setelah
         form ganti password dikirim (?tab=settings). --}}
    <script>window.tabAwal = @json($tabAktif ?? 'dashboard');</script>
    @include('partials.tab-switcher')
@endsection
