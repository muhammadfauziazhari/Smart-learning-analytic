<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu mahasiswa pada satu kelas.
 *
 * Sengaja BUKAN model User: mahasiswa tidak punya akun, tidak pernah
 * login, dan tidak punya password. Yang dicatat hanya identitasnya.
 *
 * @property int $id
 * @property int $kelas_id
 * @property string $nim
 * @property string|null $nama
 */
#[Fillable(['kelas_id', 'nim', 'nama', 'terdaftar_pada'])]
class Enrollment extends Model
{
    protected $table = 'kelas_mahasiswa';

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** Nama yang ditampilkan, dengan cadangan kalau belum dilengkapi. */
    public function namaTampil(): string
    {
        return $this->nama ?: 'NIM '.$this->nim;
    }

    /** Inisial untuk avatar, mis. "Budi Santoso" menjadi "BS". */
    public function inisial(): string
    {
        if ($this->nama === null || trim($this->nama) === '') {
            return substr($this->nim, 0, 2);
        }

        $kata = preg_split('/\s+/', trim($this->nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return strtoupper(
            implode('', array_map(
                fn ($k) => mb_substr($k, 0, 1),
                array_slice($kata, 0, 2)
            ))
        );
    }
}
