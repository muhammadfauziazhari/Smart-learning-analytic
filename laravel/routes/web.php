<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LogImportController;
use App\Http\Controllers\Admin\PenugasanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route Auth (Login & Logout)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Ubah password mandiri (Terbuka untuk semua user terautentikasi)
Route::middleware('auth')->group(function () {
    Route::post('/akun/password', [ProfilController::class, 'ubahPassword'])
        ->name('akun.password.ubah');
});

// Route Khusus Admin
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');

    // Import log LMS dan riwayatnya
    Route::get('/admin/log-import', [LogImportController::class, 'index'])->name('admin.logs.index');
    Route::post('/admin/log-import', [LogImportController::class, 'store'])->name('admin.logs.import');
    Route::get('/admin/log-import/{batch}/unduh', [LogImportController::class, 'unduh'])->name('admin.logs.download');

    // Kelola akun Dosen & Kaprodi
    Route::get('/admin/akun', [AkunController::class, 'index'])->name('admin.akun');
    Route::post('/admin/akun', [AkunController::class, 'store'])->name('admin.akun.store');
    Route::put('/admin/akun/{user}', [AkunController::class, 'update'])->name('admin.akun.update');
    Route::put('/admin/akun/{user}/akses-mata-kuliah', [AkunController::class, 'updateAkses'])->name('admin.akun.akses');
    Route::post('/admin/akun/{user}/aktif', [AkunController::class, 'toggleAktif'])->name('admin.akun.aktif');
    Route::post('/admin/akun/{user}/password', [AkunController::class, 'resetPassword'])->name('admin.akun.password');

    // Penugasan akademik
    Route::get('/admin/penugasan', [PenugasanController::class, 'index'])->name('admin.penugasan');
    Route::post('/admin/penugasan/kelas/{kelas}/dosen', [PenugasanController::class, 'tambahDosen'])->name('admin.penugasan.dosen');
    Route::delete('/admin/penugasan/kelas/{kelas}/dosen/{user}', [PenugasanController::class, 'hapusDosen'])->name('admin.penugasan.dosen.hapus');
    Route::post('/admin/penugasan/kelas/{kelas}/mahasiswa', [PenugasanController::class, 'tambahMahasiswa'])->name('admin.penugasan.mahasiswa');
    Route::delete('/admin/penugasan/kelas/{kelas}/mahasiswa/{enrollment}', [PenugasanController::class, 'hapusMahasiswa'])->name('admin.penugasan.mahasiswa.hapus');
});

// Route Khusus Kaprodi
Route::middleware(['auth', 'role:kaprodi'])->group(function () {
    Route::get('/kaprodi/dashboard', [DashboardController::class, 'kaprodiDashboard'])->name('kaprodi.dashboard');
});

// Route Khusus Dosen
Route::middleware(['auth', 'role:dosen'])->group(function () {
    Route::get('/dosen/dashboard', [DashboardController::class, 'dosenDashboard'])->name('dosen.dashboard');
});