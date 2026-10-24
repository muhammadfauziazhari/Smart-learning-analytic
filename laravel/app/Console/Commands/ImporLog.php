<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\User;
use App\Services\LogCsvImporter;
use Illuminate\Console\Command;

/**
 * Impor file log LMS dari command line.
 *
 * Memakai LogCsvImporter yang sama persis dengan form upload di
 * /admin/log-import, jadi hasil, validasi, dan dedupnya identik.
 * Perintah ini hanya pintasan: tidak ada aturan impor kedua.
 */
class ImporLog extends Command
{
    protected $signature = 'log:impor
                            {file : Path file CSV log Moodle}
                            {--admin= : Email akun admin penanggung jawab import}';

    protected $description = 'Impor file log LMS Moodle dari command line';

    public function handle(LogCsvImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $admin = $this->cariAdmin();

        if ($admin === null) {
            $this->error('Tidak ada akun admin. Jalankan `php artisan db:seed` lebih dulu.');

            return self::FAILURE;
        }

        $this->info('Mengimpor: '.$file);
        $this->line('Penanggung jawab: '.$admin->email);
        $this->newLine();

        $batch = $importer->import($file, $admin);
        $batch->refresh();

        $this->table(
            ['Keterangan', 'Nilai'],
            [
                ['Status', $batch->status],
                ['Baris terbaca', number_format((int) $batch->jumlah_baris, 0, ',', '.')],
                ['Tersimpan', number_format((int) $batch->jumlah_berhasil, 0, ',', '.')],
                ['Duplikat', number_format((int) $batch->jumlah_duplikat, 0, ',', '.')],
                ['Gagal', number_format((int) $batch->jumlah_gagal, 0, ',', '.')],
                ['Identitas dikenal', number_format((int) $batch->jumlah_terkenal, 0, ',', '.')],
                ['Tanpa akun', number_format((int) $batch->jumlah_tak_dikenal, 0, ',', '.')],
                ['Mata kuliah', $batch->kode_mata_kuliah.' / '.$batch->nama_mata_kuliah],
                ['Periode', $batch->periode],
            ],
        );

        if ($batch->status !== ImportBatch::STATUS_BERHASIL) {
            $this->error('Import belum berhasil. Lihat `php artisan log:riwayat` untuk detail.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Admin penanggung jawab import.
     *
     * Diambil dari --admin bila diberikan, kalau tidak dipakai akun admin
     * pertama. Baris `import_batches.admin_id` tidak boleh kosong, jadi
     * impor tidak boleh berjalan tanpa admin yang jelas.
     */
    private function cariAdmin(): ?User
    {
        $email = $this->option('admin');

        if (is_string($email) && $email !== '') {
            return User::where('email', $email)->first();
        }

        return User::where('role', 'admin')->orderBy('id')->first();
    }
}
