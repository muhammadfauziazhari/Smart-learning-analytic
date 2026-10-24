<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
    /**
     * Password awal untuk akun Dosen dan Kaprodi (keputusan pengguna,
     * 18 Okt 2026).
     *
     * Dulu setiap akun baru mendapat password acak yang hanya tampil satu
     * kali, sehingga sering tidak sampai ke pemiliknya dan Admin harus
     * sering mereset. Sekarang semua akun Dosen/Kaprodi mulai dari
     * password yang sama dan mudah diingat, lalu PEMILIKNYA sendiri yang
     * menggantinya lewat tab Settings.
     *
     * Konsekuensinya harus jujur: selama belum diganti, password ini
     * diketahui semua orang yang punya akses ke sistem. Karena itu
     * `AkunController@resetPassword` juga memakai nilai yang sama, dan
     * halaman Settings wajib benar-benar bisa menggantinya.
     *
     * Password ini TIDAK berlaku untuk akun admin: admin tidak ikut
     * memakai password bawaan.
     */
    public const PASSWORD_BAWAAN = 'password123';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /** Kelas yang diampu pengguna ini sebagai dosen. */
    public function kelasDiampu(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_dosen')
            ->withPivot('peran')
            ->withTimestamps();
    }

    /**
     * Mata kuliah yang boleh diakses pengguna ini.
     *
     * Ini izin yang DIPILIH Admin di menu Kelola Akun. Satu baris berarti
     * satu mata kuliah, dan berlaku untuk SEMUA kelas mata kuliah itu —
     * termasuk kelas yang dibuat setelah izin diberikan.
     *
     * Bedanya dengan kelasDiampu(): kelasDiampu menyimpan peran pengampu
     * atau pembantu per kelas (dipakai halaman Penugasan), sedangkan ini
     * menyimpan boleh/tidaknya boleh melihat.
     */
    public function mataKuliahAkses(): BelongsToMany
    {
        return $this->belongsToMany(MataKuliah::class, 'dosen_mata_kuliah')
            ->withTimestamps();
    }

    /**
     * Kelas milik pengguna ini. HANYA untuk Dosen.
     *
     * Kaprodi sengaja tidak punya relasi: sejak keputusan pengguna
     * (18 Okt 2026) ia melihat seluruh kelas, dan "seluruh kelas" bukan
     * sebuah relasi pivot. Pemakai cukup menulis
     * `Kelas::query()->untukPengguna($pengguna)` yang sudah menangani
     * ketiga peran.
     *
     * Jangan menambahkan cabang lain di sini: setiap query kelas HARUS
     * lewat scope itu (FR-05), bukan lewat relasi, supaya tidak ada dua
     * tempat yang berbeda pendapat tentang siapa boleh melihat apa.
     */
    public function kelasMilik(): BelongsToMany
    {
        return $this->kelasDiampu()->distinct();
    }

    /** Dosen juga boleh masuk ke halaman Kaprodi. */
    public function adakahDosen(): bool
    {
        return in_array($this->role, ['dosen', 'kaprodi'], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
