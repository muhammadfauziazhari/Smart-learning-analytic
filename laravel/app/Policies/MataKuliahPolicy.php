<?php

namespace App\Policies;

use App\Models\MataKuliah;
use App\Models\User;

/**
 * Batas akses data master mata kuliah.
 *
 * Dipisah dari KelasPolicy karena Laravel mencari policy berdasarkan
 * model yang dicek: `can('kelola', $mataKuliah)` akan mencari
 * MataKuliahPolicy, bukan KelasPolicy.
 */
class MataKuliahPolicy
{
    /** Hanya Admin yang boleh menambah atau mengubah data master. */
    public function kelola(User $pengguna, MataKuliah $mataKuliah): bool
    {
        return $pengguna->role === 'admin';
    }

    public function view(User $pengguna, MataKuliah $mataKuliah): bool
    {
        return match ($pengguna->role) {
            'admin' => true,
            'kaprodi' => $pengguna->prodi_id !== null
                && $mataKuliah->prodi_id === $pengguna->prodi_id,
            'dosen' => $mataKuliah->kelas()->whereHas(
                'dosen',
                fn ($q) => $q->where('users.id', $pengguna->id)
            )->exists(),
            default => false,
        };
    }
}
