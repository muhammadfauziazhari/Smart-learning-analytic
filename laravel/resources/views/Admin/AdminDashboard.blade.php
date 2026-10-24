@extends('layouts.admin')

@section('title', 'Dashboard Admin - EduTrack')
@section('sidebar-active', $tabAktif ?? 'dashboard')
@section('page-title', 'Dashboard')

@section('content')
    {{--
        Semua menu Admin hidup sebagai tab di dalam satu halaman, supaya
        berpindah bagian tidak memuat ulang halaman.

        Dua aturan yang perlu dijaga di sini:

        1. Urutan panel harus sama dengan urutan menu di
           components/admin/sidebar.blade.php. Ada test yang membandingkan
           keduanya persis.

        2. Semua panel dirender, bukan hanya tab yang aktif. Kalau tab
           lain dibiarkan kosong, sidebar yang bisa berpindah tidak ada
           gunanya. Klas "hidden" hanya controlar tampilan.

        Kelas panel memakai @class, bukan @if di dalam atribut class,
        karena @if menyisakan spasi dan baris baru pada class sehingga
        hasil HTML-nya tidak rapi.
    --}}

    <!-- Konten: DASHBOARD -->
    <div id="content-dashboard" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'dashboard',
    ])>
        @include('Admin.DashboardRingkasan')
    </div>

    <!-- Konten: UPLOAD LOG (resources/views/Admin/UploadLog.blade.php) -->
    <div id="content-uploadlog" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'uploadlog',
    ])>
        @include('Admin.UploadLog')
    </div>

    <!-- Konten: KELOLA AKUN (resources/views/Admin/Akun.blade.php) -->
    <div id="content-akun" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'akun',
    ])>
        @include('Admin.Akun')
    </div>

    <!-- Konten: PENUGASAN (resources/views/Admin/Penugasan.blade.php) -->
    <div id="content-penugasan" @class([
        'tab-content space-y-6',
        'hidden' => ($tabAktif ?? 'dashboard') !== 'penugasan',
    ])>
        @include('Admin.Penugasan')
    </div>
@endsection

@section('scripts')
    {{-- Tab awal ditetapkan server, jadi harus di-set SEBELUM tab-switcher
         dipanggil. --}}
    <script>window.tabAwal = @json($tabAktif ?? 'dashboard');</script>
    @include('partials.tab-switcher')
@endsection