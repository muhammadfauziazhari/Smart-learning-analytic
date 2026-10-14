@extends('layouts.kaprodi')

@section('title', 'Dashboard Kaprodi - EduTrack')
@section('page-title', 'Dashboard')

@section('content')
    <!-- Konten: DASHBOARD -->
    <div id="content-dashboard" class="tab-content space-y-6">
        <!-- Isi dikosongkan dahulu -->
    </div>

    <!-- Konten: PENGUMPULAN TUGAS -->
    <div id="content-pengumpulan-tugas" class="tab-content space-y-6 hidden">
        @include('Kaprodi.PengumpulanTugas')
    </div>

    <!-- Konten: KEAKTIFAN -->
    <div id="content-keaktifan" class="tab-content space-y-6 hidden">
        @include('Kaprodi.Keaktifan')
    </div>

    <!-- Konten: EWS -->
    <div id="content-ews" class="tab-content space-y-6 hidden">
        @include('Kaprodi.Ews')
    </div>

    <!-- Konten: SETTINGS -->
    <div id="content-settings" class="tab-content space-y-6 hidden">
        @include('Kaprodi.Settings')
    </div>
@endsection