<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed dasar untuk pengembangan lokal.
     *
     * Data master akademik dan hubungan pengampuannya dibuat terpisah oleh
     * DataMasterSeeder, supaya `migrate:fresh --seed` di produksi tidak ikut
     * mengisi data contoh.
     */
    public function run(): void
    {
        $fakultas = Fakultas::firstOrCreate(
            ['kode' => 'FIK'],
            ['nama' => 'Fakultas Ilmu Komputer']
        );

        $prodi = Prodi::firstOrCreate(
            ['kode' => 'TI'],
            ['nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id]
        );

        User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'nama' => 'admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'dosen@gmail.com'],
            [
                'nama' => 'Dosen Pengampu',
                // Password bawaan yang sama seperti akun lain (keputusan
                // pengguna, 18 Okt 2026), lalu digantinya sendiri
                // lewat tab Settings.
                'password' => Hash::make(User::PASSWORD_BAWAAN),
                'role' => 'dosen',
                'nomor_induk' => '198602192014042001',
                'prodi_id' => $prodi->id,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'kaprodi@gmail.com'],
            [
                'nama' => 'Kepala Program Studi',
                'password' => Hash::make(User::PASSWORD_BAWAAN),
                'role' => 'kaprodi',
                'nomor_induk' => '197901132021211003',
                'prodi_id' => $prodi->id,
                'is_active' => true,
            ]
        );
    }
}
