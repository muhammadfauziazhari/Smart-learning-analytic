<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'sks', 'semester', 'prodi_id'])]
class MataKuliah extends Model
{
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    /**
     * Dosen yang diberi izin melihat mata kuliah ini oleh Admin.
     *
     * Berbeda dengan `kelas.dosen` yang menyimpan peran pengampu per
     * kelas, relasi ini menyimpan izin lihat per mata kuliah.
     */
    public function aksesDosen(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dosen_mata_kuliah')
            ->withTimestamps();
    }

    public function scopeUntukPengguna(Builder $query, User $pengguna): Builder
    {
        return match ($pengguna->role) {
            'admin' => $query,

            // Izin lewat Kelola Akun (mata kuliah) ATAU penugasan pengampu
            // per kelas. Sama seperti scope-nya di model Kelas.
            'dosen' => $query->where(function (Builder $q) use ($pengguna) {
                $q->whereHas(
                    'aksesDosen',
                    fn (Builder $a) => $a->where('users.id', $pengguna->id)
                )->orWhereHas(
                    'kelas.dosen',
                    fn (Builder $k) => $k->where('users.id', $pengguna->id)
                );
            }),

            'kaprodi' => $query,
            default => $query->whereRaw('1 = 0'),
        };
    }
}
