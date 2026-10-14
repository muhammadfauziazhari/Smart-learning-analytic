{{-- Layout halaman Admin --}}
@extends('layouts.base')

@section('sidebar')
    {{-- Halaman bisa menandai menu aktif lain lewat @section('sidebar-active', 'uploadlog') --}}
    <x-admin.sidebar :active="trim($__env->yieldContent('sidebar-active', 'dashboard'))" />
@endsection