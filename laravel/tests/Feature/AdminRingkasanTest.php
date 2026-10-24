<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\ImportBatch;
use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\MataKuliah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tab "Dashboard" Admin: angka ringkasan.
 *
 * Test ini sengaja memasukkan baris log sungguhan. Ringkasan memakai
 * agregasi SQL, dan agregasi SQL bisa lolos di SQLite tapi ditolak
 * MySQL (mis. pluck dengan nama alias kolom). Kalau tabel log kosong,
 * kesalahan seperti itu tidak pernah tersentuh.
 */
class AdminRingkasanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin',
            'email' => 'admin@uji.local',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);
    }

    public function test_ringkasan_menghitung_baris_log_per_mata_kuliah(): void
    {
        $mk = MataKuliah::create([
            'kode' => 'IF209',
            'nama' => 'Jaringan Komputer',
            'sks' => 3,
            'semester' => 5,
        ]);

        $kelas = Kelas::create([
            'mata_kuliah_id' => $mk->id,
            'kode' => 'IF209-A',
            'nama' => 'IF209-A',
            'tahun_akademik' => '2025/2026',
            'semester' => 1,
        ]);

        $this->tambahMahasiswa($kelas, '3312501013', 'Budi Santoso');
        $this->tambahMahasiswa($kelas, '3312501014', 'Siti Aminah');

        $this->tambahLog('IF209', 3);
        // Baris tanpa kode mata kuliah tidak boleh ikut terhitung.
        $this->tambahLog(null, 5);

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('IF209', $html);
        $this->assertStringContainsString('Jaringan Komputer', $html);

        // Dua mahasiswa unik, satu kelas, tiga baris log.
        $this->assertStringContainsString('2 mhs', $html);
        $this->assertStringContainsString('1 kelas', $html);

        preg_match('/Baris log<\/p>\s*<p[^>]*>(?<angka>[^<]+)<\/p>/', $html, $cocok);
        $this->assertSame(
            '3',
            str_replace(['.', ','], '', $cocok['angka'] ?? ''),
            'Baris log harus 3, hanya yang punya kode mata kuliah.',
        );
    }

    public function test_query_agregasi_tidak_menyandera_nama_alias(): void
    {
        // Penjaga: pluck() dengan nama alias kolom menghasilkan SQL yang
        // ditolak MySQL ("Unknown column") tapi diterima SQLite. Query
        // agregasi di composer harus tetap bisa jalan di dua mesin.
        $sql = DB::table('log_aktivitas')
            ->selectRaw('kode_mata_kuliah, count(*) as jumlah')
            ->groupBy('kode_mata_kuliah')
            ->toSql();

        $this->assertStringNotContainsString('"j"', $sql);
        $this->assertStringContainsString('count(*)', $sql);

        $this->assertIsArray(DB::table('log_aktivitas')
            ->selectRaw('kode_mata_kuliah, count(*) as jumlah')
            ->groupBy('kode_mata_kuliah')
            ->get()
            ->all());
    }

    private function tambahMahasiswa(Kelas $kelas, string $nim, string $nama): void
    {
        Enrollment::create([
            'kelas_id' => $kelas->id,
            'nim' => $nim,
            'nama' => $nama,
            'terdaftar_pada' => now(),
        ]);
    }

    private function tambahLog(?string $kode, int $jumlah): void
    {
        $batch = ImportBatch::firstOrCreate(
            ['nama_file' => 'log-uji-'.$kode.'-'.$jumlah],
            ['status' => 'berhasil', 'total_baris' => 0, 'admin_id' => $this->admin->id],
        );

        for ($i = 0; $i < $jumlah; $i++) {
            LogAktivitas::create([
                'import_batch_id' => $batch->id,
                'occurred_at' => now(),
                'actor_name' => 'Budi Santoso',
                'actor_identifier' => '3312501013',
                'event_name' => 'Log report viewed',
                'kode_mata_kuliah' => $kode,
                'sidik_jari' => hash('sha256', $batch->id.'-'.$kode.'-'.$i.'-'.uniqid()),
            ]);
        }
    }
}
