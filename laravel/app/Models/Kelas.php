<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelas perkuliahan (FR-03).
 *
 * Scope `untukPengguna` di sini adalah inti dari FR-05: seluruh query
 * kelas harus lewat scope ini, bukan sekadar menyembunyikan menu di UI.
 */
#[Fillable([
    'kode', 'nama', 'mata_kuliah_id', 'prodi_id',
    'tahun_akademik', 'semester', 'kapasitas',
    // Asal (A/B/C/D) dan jenis (Pagi/Malam) dari daftar mahasiswa, dipakai
    // untuk memfilter dashboard. Wajib ada di sini: kolom yang tidak
    // terdaftar di Fillable dibuang diam-diam tanpa pesan apa pun.
    'asal_kelas', 'jenis_kelas',
])]
class Kelas extends Model
{
    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /**
     * Mahasiswa yang terdaftar di kelas ini.
     *
     * Bukan relasi ke User: mahasiswa tidak punya akun. Yang disimpan
     * hanya NIM dan nama, lewat tabel kelas_mahasiswa.
     */
    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Dosen yang mengampu kelas ini. */
    public function dosen(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kelas_dosen')
            ->withPivot('peran')
            ->withTimestamps();
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(LogAktivitas::class, 'kode_mata_kuliah', 'kode_mata_kuliah');
    }

    /**
     * Batasi query kelas sesuai cakupan pengguna (FR-05).
     *
     * - Admin    : semua kelas
     * - Dosen    : kelas yang ia ampu, plus kelas dari mata kuliah yang
     *              diberi izin oleh Admin lewat menu Kelola Akun
     * - Kaprodi  : semua kelas, tanpa batas prodi
     *
     * Tidak ada cabang untuk mahasiswa: mereka tidak punya akun, jadi
     * tidak pernah menjadi pengguna yang menjalankan query ini.
     */
    public function scopeUntukPengguna(Builder $query, User $pengguna): Builder
    {
        return match ($pengguna->role) {
            // Admin dan Kaprodi sama-sama melihat seluruh kelas. Kaprodi
            // tidak dibatasi prodi lagi (keputusan pengguna, 18 Okt 2026):
            // tugasnya mengawasi semua mata kuliah, bukan satu prodi.
            'admin', 'kaprodi' => $query,

            'dosen' => $query->where(function (Builder $q) use ($pengguna) {
                // (1) Ditugaskan sebagai pengampu/pembantu pada kelas ini.
                $q->whereHas(
                    'dosen',
                    fn (Builder $d) => $d->where('users.id', $pengguna->id)
                )
                // (2) Atau mata kuliahnya diberi izin lewat Kelola Akun.
                //     Syarat ini yang membuat kelas yang dibuat belakangan
                //     otomatis ikut terlihat tanpa ditandai ulang.
                    ->orWhereHas('mataKuliah.aksesDosen', fn (Builder $m) => $m
                        ->where('users.id', $pengguna->id));
            }),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /** Apakah NIM ini terdaftar di kelas ini? */
    public function punyaMahasiswa(string $nim): bool
    {
        return $this->mahasiswa()->where('nim', $nim)->exists();
    }

    /** Label siap tampil, mis. "Jaringan Komputer (IF209) - 2A Pagi". */
    public function label(): string
    {
        return sprintf(
            '%s (%s) - %s',
            $this->mataKuliah?->nama ?? 'Tanpa mata kuliah',
            $this->mataKuliah?->kode ?? '-',
            $this->nama,
        );
    }
}
