<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_batch_id', 'nomor_baris', 'kolom', 'kode_error', 'pesan', 'nilai_asal',
])]
class LogImportError extends Model
{
    public const KODE_HEADER_TIDAK_VALID = 'header_tidak_valid';

    public const KODE_WAKTU_TIDAK_VALID = 'waktu_tidak_valid';

    public const KODE_NAMA_KOSONG = 'nama_kosong';

    public const KODE_EVENT_KOSONG = 'event_kosong';

    public const KODE_BARIS_KURANG = 'baris_kurang';

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
