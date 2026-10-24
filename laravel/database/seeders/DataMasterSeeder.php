<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use Illuminate\Database\Seeder;

/**
 * Data master akademik.
 *
 * Dua mata kuliah diambil dari file log LMS yang nyata:
 *   IF209  Jaringan Komputer              (131.767 baris log)
 *   IF207  Proyek Pembuatan Prototipe     (52.491 baris log)
 *
 * Jalankan: php artisan db:seed --class=Database\\Seeders\\DataMasterSeeder
 */
class DataMasterSeeder extends Seeder
{
    public function run(): void
    {
        $fakultas = Fakultas::updateOrCreate(
            ['kode' => 'FIK'],
            ['nama' => 'Fakultas Ilmu Komputer']
        );

        $prodi = Prodi::updateOrCreate(
            ['kode' => 'TI'],
            ['nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id]
        );

        $mataKuliah = [
            ['kode' => 'IF209', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'semester' => 5],
            ['kode' => 'IF207', 'nama' => 'Proyek Pembuatan Prototipe', 'sks' => 4, 'semester' => 5],
        ];

        foreach ($mataKuliah as $mk) {
            $mata = MataKuliah::updateOrCreate(
                ['kode' => $mk['kode']],
                $mk + ['prodi_id' => $prodi->id]
            );

            // Kelas diambil dari nama assignment pada file log asli:
            // "Pengumpulan Praktikum 1 Kelas 2A Pagi" dan sejenisnya.
            foreach (['2A Pagi', '2B Pagi', '2C Pagi', '2D Pagi'] as $namaKelas) {
                Kelas::updateOrCreate(
                    [
                        'mata_kuliah_id' => $mata->id,
                        'kode' => $this->kodeKelas($namaKelas),
                        'tahun_akademik' => '2026/2027',
                        'semester' => 1,
                    ],
                    [
                        'nama' => $namaKelas,
                        'prodi_id' => $prodi->id,
                        'kapasitas' => 30,
                    ]
                );
            }
        }
    }

    /** "2A Pagi" menjadi "2A". */
    private function kodeKelas(string $nama): string
    {
        return explode(' ', $nama)[0];
    }
}
