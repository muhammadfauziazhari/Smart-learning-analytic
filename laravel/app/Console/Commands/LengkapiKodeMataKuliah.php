<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\LogCsvImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lengkapi kode mata kuliah pada baris log yang sudah terlanjur diimpor.
 *
 * Perintah `log:impor` sudah melengkapinya sejak dibuat, jadi
 * perintah ini hanya dibutuhkan untuk batch lama yang diimpor sebelum
 * perbaikan itu ada, atau untuk memeriksa apakah sebuah file log memang
 * berasal dari satu mata kuliah saja.
 */
class LengkapiKodeMataKuliah extends Command
{
    protected $signature = 'log:lengkapi-kode-mk
                            {--batch= : Batasi hanya satu batch}';

    protected $description = 'Isi kode mata kuliah pada baris log yang konteksnya bukan Course';

    public function handle(LogCsvImporter $importer): int
    {
        $batchId = $this->option('batch');

        $batch = DB::table('import_batches')
            ->when(is_numeric($batchId), fn ($q) => $q->where('id', (int) $batchId))
            ->orderBy('id')
            ->get();

        if ($batch->isEmpty()) {
            $this->warn('Tidak ada batch import yang cocok.');

            return self::SUCCESS;
        }

        $baris = [];

        foreach ($batch as $b) {
            $model = ImportBatch::find($b->id);
            $diperbarui = $importer->lengkapiKodeMataKuliah($model);

            $kodeDistinct = DB::table('log_aktivitas')
                ->where('import_batch_id', $b->id)
                ->whereNotNull('kode_mata_kuliah')
                ->where('kode_mata_kuliah', '<>', '')
                ->distinct()
                ->count('kode_mata_kuliah');

            $baris[] = [
                (string) $b->id,
                substr((string) $b->nama_file, 0, 40),
                (string) ($b->kode_mata_kuliah ?? '-'),
                (string) $kodeDistinct,
                number_format($diperbarui, 0, ',', '.'),
            ];
        }

        $this->table(
            ['Batch', 'File', 'Kode MK', 'Jumlah kode di file', 'Baris dilengkapi'],
            $baris
        );

        return self::SUCCESS;
    }
}
