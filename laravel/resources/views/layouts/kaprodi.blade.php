{{-- Layout halaman Kaprodi --}}
@extends('layouts.base')

@section('sidebar')
    {{-- Halaman bisa menandai menu aktif lain lewat @section('sidebar-active', 'keaktifan') --}}
    <x-kaprodi.sidebar :active="trim($__env->yieldContent('sidebar-active', 'dashboard'))" />
@endsection

@section('scripts')
    @include('partials.tab-switcher')
@endsection