<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Fakultas;
use App\Models\ImportBatch;
use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use App\Services\StatistikKeaktifan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tab "Keaktifan": angka aktivitas per mahasiswa.
 *
 * Yang diuji:
 *   - pemetaan kolom ke `context_type` di log (Course / materi / Forum)
 *   - penyaringan rentang tanggal
 *   - status EWS sementara: belum dihitung TIDAK boleh tampil sebagai aman
 *   - kelas milik dosen lain tidak boleh bocor
 */
class StatistikKeaktifanTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Prodi $prodi;

    private int $batchId;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);

        $this->prodi = Prodi::create(['kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id]);

        $admin = User::create([
            'nama' => 'Admin', 'email' => 'admin-'.uniqid().'@test.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);

        $this->batchId = ImportBatch::create([
            'admin_id' => $admin->id,
            'nama_file' => 'log.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ])->id;

        $this->dosen = User::create([
            'nama' => 'Yeni Rokhayati', 'email' => 'dosen-'.uniqid().'@test.local',
            'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);

        $this->kelas = Kelas::create([
            'kode' => 'A Pagi', 'nama' => 'Kelas A Pagi', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 2,
            'asal_kelas' => 'A', 'jenis_kelas' => 'Pagi',
        ]);

        $this->kelas->dosen()->attach($this->dosen->id, ['peran' => 'pengampu']);
    }

    private function mhs(string $nim, string $nama): void
    {
        Enrollment::create(['kelas_id' => $this->kelas->id, 'nim' => $nim, 'nama' => $nama]);
    }

    /**
     * @param  array<string, mixed>  $tambahan
     */
    private function log(string $waktu, string $nim, array $tambahan = []): void
    {
        LogAktivitas::create(array_merge([
            'import_batch_id' => $this->batchId,
            'occurred_at' => $waktu,
            'actor_name' => 'Mahasiswa '.$nim,
            'actor_identifier' => $nim,
            'actor_role' => LogAktivitas::PERAN_MAHASISWA,
            'kode_mata_kuliah' => 'IF209',
            'periode' => substr($waktu, 0, 7),
            'sidik_jari' => uniqid('sj', true),
        ], $tambahan));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $peta
     * @return array<string, mixed>
     */
    private function keaktifan(array $filter = [], array $peta = []): array
    {
        return app(StatistikKeaktifan::class)->untuk($this->dosen, $filter, $peta);
    }

    private function baris(array $data, string $nim): array
    {
        foreach ($data['baris'] as $baris) {
            if ($baris['nim'] === $nim) {
                return $baris;
            }
        }

        $this->fail("NIM {$nim} tidak ada di hasil keaktifan.");
    }

    // ------------------------------------------------------------- test

    public function test_kolom_aktivitas_memetakan_konteks_log(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');

        $this->log('2026-03-02 08:00:00', '3312501001', [
            'event_name' => 'Course viewed', 'context_type' => 'Course',
        ]);
        $this->log('2026-03-02 08:05:00', '3312501001', [
            'event_name' => 'Section viewed', 'context_type' => 'Course',
        ]);
        $this->log('2026-03-03 09:00:00', '3312501001', [
            'event_name' => 'Course module viewed', 'context_type' => 'File',
        ]);
        $this->log('2026-03-03 09:30:00', '3312501001', [
            'event_name' => 'Course module viewed', 'context_type' => 'Folder',
        ]);
        $this->log('2026-03-04 10:00:00', '3312501001', [
            'event_name' => 'Course module viewed', 'context_type' => 'URL',
        ]);
        $this->log('2026-03-05 11:00:00', '3312501001', [
            'event_name' => 'Course module viewed', 'context_type' => 'Forum',
        ]);
        // Kuis bukan salah satu kolom dan tidak boleh ikut terhitung.
        $this->log('2026-03-06 12:00:00', '3312501001', [
            'event_name' => 'Quiz attempt viewed', 'context_type' => 'Quiz',
        ]);

        $baris = $this->baris($this->keaktifan(), '3312501001');

        $this->assertSame(2, $baris['course']);
        $this->assertSame(3, $baris['materi']);
        $this->assertSame(1, $baris['forum']);
        $this->assertSame(0, $baris['submission']);
        $this->assertSame(5, $baris['hari']);
    }

    public function test_rentang_tanggal_membuang_aktivitas_di_luar_jendela(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');

        $this->log('2026-03-02 08:00:00', '3312501001', [
            'event_name' => 'Course viewed', 'context_type' => 'Course',
        ]);
        // Oktober ada di dalam log, tapi di luar semester genap.
        $this->log('2026-10-02 08:00:00', '3312501001', [
            'event_name' => 'Course viewed', 'context_type' => 'Course',
        ]);

        $semester = $this->baris($this->keaktifan(), '3312501001');
        $this->assertSame(1, $semester['course'], 'Default harus mengikuti bulan semester.');

        $semua = $this->baris(
            $this->keaktifan(['dari' => '2026-01-01', 'sampai' => '2026-12-31']),
            '3312501001',
        );
        $this->assertSame(2, $semua['course']);
    }

    public function test_rentang_terbalik_ditukar_bukan_menghapus_baris(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');
        $this->log('2026-03-02 08:00:00', '3312501001', [
            'event_name' => 'Course viewed', 'context_type' => 'Course',
        ]);

        $data = $this->keaktifan(['dari' => '2026-03-31', 'sampai' => '2026-03-01']);

        $this->assertSame('2026-03-01', $data['rentang']['dari']);
        $this->assertSame('2026-03-31', $data['rentang']['sampai']);
        $this->assertSame(1, $this->baris($data, '3312501001')['course']);
    }

    public function test_tanggal_ngawur_diabaikan(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');

        $data = $this->keaktifan(['dari' => 'bukan-tanggal', 'sampai' => '2026-13-45']);

        $this->assertSame('2026-02-01', $data['rentang']['dari']);
        $this->assertSame('2026-07-31', $data['rentang']['sampai']);
        $this->assertTrue($data['rentang']['bawaan']);
    }

    public function test_tepat_waktu_dan_terlambat_mengikuti_aturan_tugas_yang_sama(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');
        $this->mhs('3312501002', 'Andi Pratama');

        LogAktivitas::create([
            'import_batch_id' => $this->batchId,
            'occurred_at' => '2026-03-02 08:00:00',
            'actor_name' => 'Dosen',
            'event_name' => LogAktivitas::EVENT_TUGAS_DIBUAT,
            'context_type' => LogAktivitas::KONTEKS_TUGAS,
            'context_name' => 'Pengumpulan Praktikum 7 IF 2A Pagi',
            'kode_mata_kuliah' => 'IF209',
            'periode' => '2026-03',
            'sidik_jari' => uniqid('sj', true),
        ]);

        // Tepat waktu: tenggat 9 Maret.
        $this->log('2026-03-05 10:00:00', '3312501001', [
            'event_name' => LogAktivitas::EVENT_TUGAS_DIKUMPULKAN,
            'context_type' => LogAktivitas::KONTEKS_TUGAS,
            'context_name' => 'Pengumpulan Praktikum 7 IF 2A Pagi',
        ]);
        // Terlambat.
        $this->log('2026-03-11 10:00:00', '3312501002', [
            'event_name' => LogAktivitas::EVENT_TUGAS_DIKUMPULKAN,
            'context_type' => LogAktivitas::KONTEKS_TUGAS,
            'context_name' => 'Pengumpulan Praktikum 7 IF 2A Pagi',
        ]);

        $data = $this->keaktifan();

        $ririn = $this->baris($data, '3312501001');
        $this->assertSame(1, $ririn['submission']);
        $this->assertSame(1, $ririn['tepat']);
        $this->assertSame(0, $ririn['terlambat']);

        $andi = $this->baris($data, '3312501002');
        $this->assertSame(0, $andi['tepat']);
        $this->assertSame(1, $andi['terlambat']);
    }

    public function test_status_ews_belum_dihitung_tidak_menjadi_aman(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');

        $data = $this->keaktifan();

        $this->assertSame('belum', $this->baris($data, '3312501001')['ews']['kode']);
        $this->assertSame(1, $data['ringkasan']['ews']['belum']);
        $this->assertSame(0, $data['ringkasan']['ews']['low']);
    }

    public function test_status_ews_dari_level_risiko_layanan(): void
    {
        $this->mhs('3312501001', 'Aman');
        $this->mhs('3312501002', 'Perhatian');
        $this->mhs('3312501003', 'Bahaya');

        $data = $this->keaktifan([], [
            '_3312501001' => ['engagement' => 0.8, 'level' => 'low', 'risk' => 0.1],
            '_3312501002' => ['engagement' => 0.4, 'level' => 'medium', 'risk' => 0.45],
            '_3312501003' => ['engagement' => 0.1, 'level' => 'high', 'risk' => 0.9],
        ]);

        $this->assertSame('hijau', $this->baris($data, '3312501001')['ews']['warna']);
        $this->assertSame('kuning', $this->baris($data, '3312501002')['ews']['warna']);
        $this->assertSame('merah', $this->baris($data, '3312501003')['ews']['warna']);
        $this->assertSame(1, $data['ringkasan']['ews']['high']);
        $this->assertSame(0, $data['ringkasan']['ews']['belum']);
    }

    public function test_status_ews_dipakai_skor_bila_level_tidak_ada(): void
    {
        $this->mhs('3312501001', 'Tanpa Level');

        $data = $this->keaktifan([], [
            '_3312501001' => ['engagement' => null, 'level' => null, 'risk' => 0.7],
        ]);

        $this->assertSame('high', $this->baris($data, '3312501001')['ews']['kode']);
    }

    public function test_urutan_mahasiswa_paling_berisiko_dulu(): void
    {
        $this->mhs('3312501001', 'Aman');
        $this->mhs('3312501002', 'Bahaya');

        $data = $this->keaktifan([], [
            '_3312501001' => ['engagement' => 0.9, 'level' => 'low', 'risk' => 0.05],
            '_3312501002' => ['engagement' => 0.1, 'level' => 'high', 'risk' => 0.95],
        ]);

        $this->assertSame('Bahaya', $data['baris'][0]['nama']);
    }

    public function test_mahasiswa_lengkap_nama_kelasnya(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');

        $baris = $this->baris($this->keaktifan(), '3312501001');

        $this->assertSame('Kelas A Pagi', $baris['kelas']);
    }

    public function test_kelas_milik_dosen_lain_tidak_bocor(): void
    {
        $mkLain = MataKuliah::create([
            'kode' => 'IF207', 'nama' => 'Proyek', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);

        $kelasLain = Kelas::create([
            'kode' => 'B Pagi', 'nama' => 'Kelas B Pagi', 'mata_kuliah_id' => $mkLain->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 2,
            'asal_kelas' => 'B', 'jenis_kelas' => 'Pagi',
        ]);

        Enrollment::create(['kelas_id' => $kelasLain->id, 'nim' => '3312501999', 'nama' => 'Orang Lain']);

        $data = $this->keaktifan();

        $this->assertCount(1, $data['kelas']);
        $this->assertCount(0, $data['baris']);
        $this->assertTrue($data['kosong']);
    }

    public function test_pencarian_menyaring_mahasiswa(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');
        $this->mhs('3312501002', 'Andi Pratama');

        $data = $this->keaktifan(['q' => '3312501002']);

        $this->assertCount(1, $data['baris']);
        $this->assertSame('Andi Pratama', $data['baris'][0]['nama']);
    }

    public function test_halaman_menampilkan_tabel_dan_catatan_keterbatasan(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');
        $this->log('2026-03-02 08:00:00', '3312501001', [
            'event_name' => 'Course viewed', 'context_type' => 'Course',
        ]);

        $this->actingAs($this->dosen)
            ->get('/dosen/dashboard?tab=keaktifan')
            ->assertOk()
            ->assertSee('Detail Keaktifan Mahasiswa')
            ->assertSee('Akses Course')
            ->assertSee('Akses Materi')
            ->assertSee('Submission')
            ->assertSee('Status EWS')
            ->assertSee('Engagement')
            ->assertSee('Ririn Anisa')
            ->assertSee('3312501001')
            // Dua keterbatasan yang harus terlihat, bukan disembunyikan.
            ->assertSee('Kolom Forum tidak bisa dinilai')
            ->assertSee('Status EWS masih sementara');
    }
}
