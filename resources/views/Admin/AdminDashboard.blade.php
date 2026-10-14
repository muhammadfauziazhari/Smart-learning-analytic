@extends('layouts.admin')

@section('title', 'Dashboard Admin - EduTrack')
@section('page-title', 'Dashboard')

@section('content')
    <!-- Konten: DASHBOARD -->
    <div id="content-dashboard" class="tab-content space-y-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-white">Ringkasan Dashboard</h2>
                <p class="text-xs text-slate-400 mt-0.5">Selamat datang kembali di panel kontrol akademik Anda.</p>
            </div>
        </div>
        <!-- Isi dikosongkan dahulu -->
        <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
            <i class="fa-solid fa-hammer text-3xl mb-2"></i>
            <p class="text-sm">Konten Dashboard akan ditampilkan di sini.</p>
        </div>
    </div>

    <!-- Konten: UPLOAD LOG (resources/views/Admin/UploadLog.blade.php) -->
    <div id="content-uploadlog" class="tab-content space-y-6 hidden">
        @include('Admin.UploadLog')
    </div>
@endsection

@section('scripts')
    @include('partials.tab-switcher')
@endsection
