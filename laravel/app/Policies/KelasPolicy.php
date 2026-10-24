<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;

/**
 * Batas akses berbasis peran dan cakupan (FR-05).
 *
 * Policy ini diperiksa di server pada setiap request. Menyembunyikan menu
 * di tampilan BUKAN Mechanisms keamanan: tanpa policy, Dosen cukup
 * mengetik URL kelas orang lain untuk membukanya.
 *
 * Aturan:
 * - Admin   : melihat semuanya.
 * - Dosen   : hanya kelas yang ia ampu, dan mahasiswa di kelas itu.
 * - Kaprodi : seluruh kelas, tanpa batas prodi (keputusan pengguna,
 *             18 Okt 2026). Sebelumnya hanya melihat prodi sendiri.
 * - Mahasiswa: hanya kelas yang ia ikuti.
 */
class KelasPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return in_array($pengguna->role, ['admin', 'dosen', 'kaprodi'], true);
    }

    public function view(User $pengguna, Kelas $kelas): bool
    {
        if (in_array($pengguna->role, ['admin', 'kaprodi'], true)) {
            return true;
        }

        if ($pengguna->role === 'dosen') {
            // Dua sumber izin, satu arti: boleh melihat kelas ini kalau
            // (1) ditugaskan pada kelas ini di halaman Penugasan, atau
            // (2) mata kuliahnya diberi izin di menu Kelola Akun.
            return $kelas->dosen()->where('users.id', $pengguna->id)->exists()
                || $kelas->mataKuliah?->aksesDosen()->where('users.id', $pengguna->id)->exists() === true;
        }

        return false;
    }

    public function update(User $pengguna, Kelas $kelas): bool
    {
        return $this->view($pengguna, $kelas);
    }

    /**
     * Melihat aktivitas satu mahasiswa pada kelas tertentu.
     *
     * Dosen boleh hanya jika mahasiswa dengan NIM tersebut terdaftar di
     * kelas yang ia ampu. Inilah yang mencegah Dosen mengintip mahasiswa
     * kelas lain.
     *
     * Mahasiswa tidak punya akun, jadi yang diperiksa adalah NIM-nya.
     */
    public function lihatMahasiswa(User $pengguna, Kelas $kelas, string $nim): bool
    {
        if (! $this->view($pengguna, $kelas)) {
            return false;
        }

        if ($pengguna->role === 'admin' || $pengguna->role === 'kaprodi') {
            return true;
        }

        return $kelas->punyaMahasiswa($nim);
    }
}
