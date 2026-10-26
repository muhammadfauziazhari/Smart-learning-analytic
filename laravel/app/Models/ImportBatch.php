<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'admin_id', 'nama_file', 'path',
    'kode_mata_kuliah', 'nama_mata_kuliah', 'periode',
    'jumlah_baris', 'jumlah_berhasil', 'jumlah_duplikat', 'jumlah_gagal',
    'jumlah_terkenal', 'jumlah_tak_dikenal',
    'status', 'pesan', 'started_at', 'finished_at',
])]
class ImportBatch extends Model
{
    /** Berkas log pada cakram, untuk keperluan audit. */
    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DIPROSES = 'diproses';

    public const STATUS_BERHASIL = 'berhasil';

    public const STATUS_GAGAL = 'gagal';

    /**
     * Waktu mulai/selesai disimpan sebagai datetime, bukan string, supaya
     * view bisa memanggil translatedFormat() tanpa error.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(LogAktivitas::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(LogImportError::class);
    }

    /** Persentase baris yang berhasil disimpan, 0 sampai 100. */
    public function persenBerhasil(): float
    {
        if ($this->jumlah_baris === 0) {
            return 0.0;
        }

        return round($this->jumlah_berhasil / $this->jumlah_baris * 100, 2);
    }

    public function selesai(): bool
    {
        return in_array($this->status, [self::STATUS_BERHASIL, self::STATUS_GAGAL], true);
    }
}
