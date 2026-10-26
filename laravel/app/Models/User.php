<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nama', 'nomor_induk', 'email', 'password', 'role', 'prodi_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const PASSWORD_BAWAAN = 'password123';

    use HasFactory, Notifiable;

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function kelasDiampu(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_dosen')
            ->withPivot('peran')
            ->withTimestamps();
    }

    public function mataKuliahAkses(): BelongsToMany
    {
        return $this->belongsToMany(MataKuliah::class, 'dosen_mata_kuliah')
            ->withTimestamps();
    }

    public function kelasMilik(): BelongsToMany
    {
        return $this->kelasDiampu()->distinct();
    }

    public function adakahDosen(): bool
    {
        return in_array($this->role, ['dosen', 'kaprodi'], true);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
