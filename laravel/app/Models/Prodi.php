<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'fakultas_id'])]
class Prodi extends Model
{
    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class);
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Hanya prodi milik pengguna sendiri yang boleh tampil untuk Kaprodi. */
    public function scopeUntukPengguna(Builder $query, User $pengguna): Builder
    {
        return match ($pengguna->role) {
            'admin' => $query,
            'kaprodi' => $pengguna->prodi_id === $this->id
                ? $query->where('id', $this->id)
                : $query->whereRaw('1 = 0'),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
