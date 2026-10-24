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
use App\Services\RekapTugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tab "Pengumpulan Tugas" (matriks mahasiswa x minggu).
 *
 * Aturan warna yang diuji:
 *   hijau  = mengumpulkan paling lambat 7 hari setelah tugas dibuat
 *   kuning = mengumpulkan setelah tenggat
 *   merah  = ada tugas, tidak mengumpulkan
 *   abu    = minggu tanpa tugas
 *
 * Data dibuat dengan nama tugas dan tanggal pembuatan yang sama pola dengan
 * log Moodle nyata ("Pengumpulan Praktikum 7 IF 2A Pagi").
 */
class RekapTugasTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Prodi $prodi;

    private int $batchId;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika',
            'fakultas_id' => Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer'])->id,
        ]);

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

    private function tugasDibuat(string $nama, string $tanggal): void
    {
        LogAktivitas::create([
            'import_batch_id' => $this->batchId,
            'occurred_at' => $tanggal,
            'actor_name' => 'Dosen',
            'event_name' => LogAktivitas::EVENT_TUGAS_DIBUAT,
            'context_type' => LogAktivitas::KONTEKS_TUGAS,
            'context_name' => $nama,
            'kode_mata_kuliah' => 'IF209',
            'periode' => substr($tanggal, 0, 7),
            'sidik_jari' => uniqid('sj', true),
        ]);
    }

    private function kumpulkan(string $namaTugas, string $nim, string $tanggal): void
    {
        LogAktivitas::create([
            'import_batch_id' => $this->batchId,
            'occurred_at' => $tanggal,
            'actor_name' => 'Mahasiswa '.$nim,
            'actor_identifier' => $nim,
            'actor_role' => LogAktivitas::PERAN_MAHASISWA,
            'event_name' => LogAktivitas::EVENT_TUGAS_DIKUMPULKAN,
            'context_type' => LogAktivitas::KONTEKS_TUGAS,
            'context_name' => $namaTugas,
            'kode_mata_kuliah' => 'IF209',
            'periode' => substr($tanggal, 0, 7),
            'sidik_jari' => uniqid('sj', true),
        ]);
    }

    private function rekap(): array
    {
        return app(RekapTugas::class)->untuk($this->dosen);
    }

    private function sel(array $data, string $nim, int $minggu): array
    {
        foreach ($data['baris'] as $baris) {
            if ($baris['nim'] === $nim) {
                return $baris['sel'][$minggu];
            }
        }

        $this->fail("NIM {$nim} tidak ada di hasil rekap.");
    }

    // ------------------------------------------------------------- test

    public function test_pengumpulan_tepat_waktu_hijau_terlambat_kuning_tidak_mengumpulkan_merah(): void
    {
        $this->mhs('3312501001', 'Ririn');
        $this->mhs('3312501002', 'Andi');
        $this->mhs('3312501003', 'Bunga');

        // Tugas dibuat 2 Mar 2026, jadi tenggat 9 Mar 2026.
        $nama = 'Pengumpulan Praktikum 7 IF 2A Pagi';
        $this->tugasDibuat($nama, '2026-03-02 08:00:00');

        $this->kumpulkan($nama, '3312501001', '2026-03-05 10:00:00'); // tepat waktu
        $this->kumpulkan($nama, '3312501002', '2026-03-11 10:00:00'); // terlambat
        // 3312501003 tidak mengumpulkan sama sekali.

        $data = $this->rekap();

        $this->assertFalse($data['kosong']);
        $this->assertSame(1, $data['ringkasan']['tugas']);

        $minggu = $data['minggu'][0]['minggu'];

        $this->assertSame(RekapTugas::TEPAT_WAKTU, $this->sel($data, '3312501001', $minggu)['status']);
        $this->assertSame(RekapTugas::TERLAMBAT, $this->sel($data, '3312501002', $minggu)['status']);
        $this->assertSame(RekapTugas::TIDAK_MENGUMPULKAN, $this->sel($data, '3312501003', $minggu)['status']);

        $this->assertSame(1, $data['ringkasan']['tepat']);
        $this->assertSame(1, $data['ringkasan']['terlambat']);
        $this->assertSame(1, $data['ringkasan']['tidak']);
    }

    public function test_tenggat_tepat_tujuh_hari_setelah_dibuat(): void
    {
        $this->mhs('3312501001', 'Ririn');

        $nama = 'Pengumpulan Praktikum 7 IF 2A Pagi';
        $this->tugasDibuat($nama, '2026-03-02 08:00:00');
        // Tepat pada menit ke-7 hari, harus masih dihitung tepat waktu.
        $this->kumpulkan($nama, '3312501001', '2026-03-09 08:00:00');

        $data = $this->rekap();
        $minggu = $data['minggu'][0]['minggu'];

        $this->assertSame(RekapTugas::TEPAT_WAKTU, $this->sel($data, '3312501001', $minggu)['status']);
    }

    public function test_minggu_tanpa_tugas_berwarna_abu_bukan_merah(): void
    {
        $this->mhs('3312501001', 'Ririn');

        // Tugas di minggu ke-6 dan ke-10 semester; minggu 7..9 kosong.
        $this->tugasDibuat('Pengumpulan Praktikum 1 Kelas IF A Pagi', '2026-03-02 08:00:00');
        $this->tugasDibuat('Pengumpulan Praktikum 5 Kelas IF A Pagi', '2026-04-13 08:00:00');

        // Kedua tugas dikumpulkan tepat waktu.
        $this->kumpulkan('Pengumpulan Praktikum 1 Kelas IF A Pagi', '3312501001', '2026-03-03 08:00:00');
        $this->kumpulkan('Pengumpulan Praktikum 5 Kelas IF A Pagi', '3312501001', '2026-04-14 08:00:00');

        $data = $this->rekap();
        $mingguAda = array_column($data['minggu'], 'minggu');

        $this->assertGreaterThanOrEqual(5, count($mingguAda), 'Minggu kosong di tengah harus tetap jadi kolom.');

        foreach ($data['minggu'] as $minggu) {
            $sel = $this->sel($data, '3312501001', $minggu['minggu']);

            $this->assertSame(
                $minggu['tugas'] === 0 ? RekapTugas::TANPA_TUGAS : RekapTugas::TEPAT_WAKTU,
                $sel['status'],
                'Minggu tanpa tugas tidak boleh dihitung sebagai mahasiswa menunggak.',
            );
        }

        // Minggu kosong tidak boleh masuk hitungan "tidak mengumpulkan".
        $this->assertSame(0, $data['ringkasan']['tidak']);
    }

    public function test_hanya_tugas_yang_menyebut_kelas_yang_dipakai(): void
    {
        $this->mhs('3312501001', 'Ririn');

        $this->tugasDibuat('Pengumpulan Praktikum 7 IF 2A Pagi', '2026-03-02 08:00:00');
        // Tugas milik kelas lain dan tugas bersama: tidak boleh ikut tampil.
        $this->tugasDibuat('Pengumpulan Praktikum 7 IF 2C Malam', '2026-03-02 08:00:00');
        $this->tugasDibuat('Pengumpulan Praktikum 7 IF Batamindo', '2026-03-02 08:00:00');
        $this->tugasDibuat('Upload Sertifikat Pelatihan AI', '2026-03-02 08:00:00');

        $data = $this->rekap();

        $this->assertSame(1, $data['ringkasan']['tugas'], 'Hanya tugas kelas terpilih yang boleh dihitung.');
    }

    public function test_kelas_lain_tidak_bisa_dilihat_oleh_dosen(): void
    {
        $mk = MataKuliah::create([
            'kode' => 'IF207', 'nama' => 'Proyek', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);

        $kelasLain = Kelas::create([
            'kode' => 'B Pagi', 'nama' => 'Kelas B Pagi', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 2,
            'asal_kelas' => 'B', 'jenis_kelas' => 'Pagi',
        ]);

        Enrollment::create(['kelas_id' => $kelasLain->id, 'nim' => '3312501999', 'nama' => 'Orang Lain']);

        $data = $this->rekap();

        $this->assertCount(1, $data['kelas']);
        foreach ($data['baris'] as $baris) {
            $this->assertNotSame('3312501999', $baris['nim']);
        }
    }

    public function test_pencarian_menyaring_mahasiswa(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');
        $this->mhs('3312501002', 'Andi Pratama');

        $data = app(RekapTugas::class)->untuk($this->dosen, ['q' => 'Ririn']);

        $this->assertCount(1, $data['baris']);
        $this->assertSame('Ririn Anisa', $data['baris'][0]['nama']);

        $nim = app(RekapTugas::class)->untuk($this->dosen, ['q' => '3312501002']);
        $this->assertSame('Andi Pratama', $nim['baris'][0]['nama']);
    }

    public function test_mahasiswa_tersortir_yang_paling_banyak_menunggak(): void
    {
        $this->mhs('3312501001', 'Rajin');
        $this->mhs('3312501002', 'Telat');

        $a = 'Pengumpulan Praktikum 1 Kelas IF A Pagi';
        $b = 'Pengumpulan Praktikum 5 Kelas IF A Pagi';
        $this->tugasDibuat($a, '2026-03-02 08:00:00');
        $this->tugasDibuat($b, '2026-04-13 08:00:00');

        $this->kumpulkan($a, '3312501001', '2026-03-03 08:00:00');
        $this->kumpulkan($b, '3312501001', '2026-04-14 08:00:00');
        $this->kumpulkan($a, '3312501002', '2026-03-03 08:00:00');

        $data = $this->rekap();

        $this->assertSame('Telat', $data['baris'][0]['nama'], 'Yang menunggak harus tampil paling atas.');
    }

    public function test_halaman_menampilkan_matriks_dan_legenda(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');
        // Mahasiswa kedua tidak mengumpulkan, supaya pratinjau pesan muncul.
        $this->mhs('3312501002', 'Andi Pratama');

        $this->tugasDibuat('Pengumpulan Praktikum 7 IF 2A Pagi', '2026-03-02 08:00:00');
        $this->kumpulkan('Pengumpulan Praktikum 7 IF 2A Pagi', '3312501001', '2026-03-03 08:00:00');

        $this->actingAs($this->dosen)
            ->get('/dosen/dashboard?tab=pengumpulan-tugas')
            ->assertOk()
            ->assertSee('Pengumpulan Tugas')
            ->assertSee('Tepat waktu')
            ->assertSee('Terlambat')
            ->assertSee('Tidak mengumpulkan')
            ->assertSee('Ririn Anisa')
            ->assertSee('Andi Pratama')
            ->assertSee('Minggu ', escape: false)
            // Pratinjau pesan, bukan pengiriman.
            ->assertSee('Contoh pesan yang bisa dikirim')
            ->assertSee('belum mengirim pesan apa pun');
    }

    public function test_tidak_ada_pratinjau_pesan_bila_semua_tepat_waktu(): void
    {
        $this->mhs('3312501001', 'Ririn Anisa');
        $this->tugasDibuat('Pengumpulan Praktikum 7 IF 2A Pagi', '2026-03-02 08:00:00');
        $this->kumpulkan('Pengumpulan Praktikum 7 IF 2A Pagi', '3312501001', '2026-03-03 08:00:00');

        $this->actingAs($this->dosen)
            ->get('/dosen/dashboard?tab=pengumpulan-tugas')
            ->assertOk()
            ->assertDontSee('Contoh pesan yang bisa dikirim');
    }

    public function test_kosong_bila_tidak_ada_kelas_yang_bisa_diakses(): void
    {
        $dosenLain = User::create([
            'nama' => 'Tanpa Kelas', 'email' => 'baru-'.uniqid().'@test.local',
            'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $data = app(RekapTugas::class)->untuk($dosenLain);

        $this->assertTrue($data['kosong']);
        $this->assertSame([], $data['baris']);
    }

    public function test_baris_dibatasi_tapi_jumlah_mahasiswa_dinyatakan(): void
    {
        // Melewati batas berarti HTML matriks membengkak: 245 mahasiswa x
        // 22 minggu = 5.390 sel dan halaman dashboard jadi 5,7 MB.
        $total = RekapTugas::BATAS_MAHASISWA + 25;

        for ($i = 1; $i <= $total; $i++) {
            $this->mhs('33125'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'Mahasiswa '.$i);
        }

        $this->tugasDibuat('Pengumpulan Praktikum 7 IF 2A Pagi', '2026-03-02 08:00:00');

        $data = $this->rekap();

        $this->assertCount(RekapTugas::BATAS_MAHASISWA, $data['baris']);
        $this->assertSame($total, $data['jumlahMahasiswa']);

        // Yang dipotong harus yang paling akhir, bukan yang paling awal.
        $namaTerakhir = $data['baris'][count($data['baris']) - 1]['nama'];
        $this->assertNotSame('Mahasiswa 1', $namaTerakhir);
    }

    /**
     * Keputusan pengguna 21 Okt 2026: satu minggu satu tugas praktikum.
     * Kolom tidak lagi dihitung dari tanggal tugas dibuat, tapi dari nomor
     * yang tertulis di nama tugas.
     */
    public function test_kolom_mengikuti_nomor_yang_tertulis_di_nama_tugas(): void
    {
        $this->mhs('3312501001', 'Ririn');

        // Tugas "Praktikum 7" dibuat 2 Mar 2026 (minggu semester ke-6), tetapi
        // kolomnya harus Minggu 7 sesuai nomor di namanya.
        $nama = 'Pengumpulan Praktikum 7 IF 2A Pagi';
        $this->tugasDibuat($nama, '2026-03-02 08:00:00');
        $this->kumpulkan($nama, '3312501001', '2026-03-05 10:00:00');

        $data = $this->rekap();

        $this->assertSame([7], array_column($data['minggu'], 'minggu'));
        $this->assertSame('Minggu 7', $data['minggu'][0]['label']);
        // Rentang tanggal tetap ditampilkan (Senin minggu ke-7 semester).
        $this->assertSame('2026-03-09', $data['minggu'][0]['mulai']);
        $this->assertSame(RekapTugas::TEPAT_WAKTU, $this->sel($data, '3312501001', 7)['status']);
    }

    /** Tugas tanpa nomor (ATS, AAS) tetap tampil, di minggu saat tugas dibuat. */
    public function test_tugas_tanpa_nomor_masuk_kolom_minggu_tanggal_dibuat(): void
    {
        $this->mhs('3312501001', 'Ririn');

        $ats = 'Pengumpulkan ATS Pratikum - IF 2A Pagi';
        $this->tugasDibuat($ats, '2026-05-11 08:00:00');
        $this->kumpulkan($ats, '3312501001', '2026-05-12 10:00:00');

        $data = $this->rekap();

        // 11 Mei 2026 jatuh di minggu semester ke-16 (Senin minggu awal 26 Jan).
        $this->assertSame([16], array_column($data['minggu'], 'minggu'));
        $this->assertSame(RekapTugas::TEPAT_WAKTU, $this->sel($data, '3312501001', 16)['status']);
    }

    /**
     * AAS menulis nama kelas "2C Pagi" setelah kata "Praktikum"; angka "2"
     * itu BUKAN nomor minggu dan tidak boleh membuat AAS jatuh di Minggu 2.
     * Karena tidak punya nomor, AAS ikut kolom minggu tanggalnya dibuat.
     */
    public function test_angka_dalam_nama_kelas_tidak_dianggap_nomor_minggu(): void
    {
        $this->mhs('3312501001', 'Ririn');

        $aas = 'Pengumpulan AAS Teori dan Praktikum - IF 2A Pagi';
        $this->tugasDibuat($aas, '2026-07-17 08:00:00');
        $this->kumpulkan($aas, '3312501001', '2026-07-20 10:00:00');

        $data = $this->rekap();

        // 17 Juli 2026 jatuh di minggu ke-25 — bukan Minggu 2 dari "2A Pagi".
        $this->assertSame([25], array_column($data['minggu'], 'minggu'));
        $this->assertSame(RekapTugas::TEPAT_WAKTU, $this->sel($data, '3312501001', 25)['status']);
    }

    /** Minggu yang nomornya tidak ada tugasnya tidak dibuat-buat: langsung kosong. */
    public function test_minggu_yang_tidak_ada_tugasnya_dilewati_bukan_digeser(): void
    {
        $this->mhs('3312501001', 'Ririn');

        // "Praktikum 4" tidak pernah ada di log; hanya 5 dan 7.
        $a = 'Pengumpulan Praktikum 5 Kelas IF A Pagi';
        $b = 'Pengumpulan Praktikum 7 IF 2A Pagi';
        $this->tugasDibuat($a, '2026-04-10 08:00:00');
        $this->tugasDibuat($b, '2026-04-22 08:00:00');
        $this->kumpulkan($a, '3312501001', '2026-04-11 10:00:00');
        $this->kumpulkan($b, '3312501001', '2026-04-23 10:00:00');

        $data = $this->rekap();

        $this->assertSame([5, 6, 7], array_column($data['minggu'], 'minggu'));
        // Minggu 6 tanpa tugas: kolom tetap ada, tapi tidak punya tugas apa pun.
        $this->assertSame(0, $data['minggu'][1]['tugas']);
        $this->assertSame(RekapTugas::TANPA_TUGAS, $this->sel($data, '3312501001', 6)['status']);
        $this->assertSame(0, $data['ringkasan']['tidak']);
    }

    /** Dua tugas asli yang menabrak minggu yang sama digabung di satu sel, bukan dibuang. */
    public function test_dua_tugas_nomor_sama_digabung_di_satu_sel(): void
    {
        $this->mhs('3312501001', 'Ririn');

        $a = 'Pengumpulan Praktikum 5 Kelas IF A Pagi';
        $b = 'Pengumpulan Praktikum Minggu 5 Kelas IF 2A Pagi';
        $this->tugasDibuat($a, '2026-04-06 08:00:00');
        $this->tugasDibuat($b, '2026-04-08 08:00:00');
        $this->kumpulkan($a, '3312501001', '2026-04-07 10:00:00');
        $this->kumpulkan($b, '3312501001', '2026-04-09 10:00:00');

        $data = $this->rekap();

        $this->assertSame([5], array_column($data['minggu'], 'minggu'));
        $this->assertSame(2, $data['minggu'][0]['tugas']);

        $sel = $this->sel($data, '3312501001', 5);
        $this->assertSame(RekapTugas::TEPAT_WAKTU, $sel['status']);
        $this->assertCount(2, $sel['detail'], 'Kedua tugas tetap tercantum di rincian sel.');
    }

    /** Dua tugas seminggu, salah satunya telat: sel gabungan memakai status terburuk. */
    public function test_gabungan_sel_memakai_status_terburuk(): void
    {
        $this->mhs('3312501001', 'Tertanda Telat');

        $a = 'Pengumpulan Praktikum 5 Kelas IF A Pagi';
        $b = 'Pengumpulan Praktikum Minggu 5 Kelas IF 2A Pagi';
        $this->tugasDibuat($a, '2026-04-06 08:00:00');
        $this->tugasDibuat($b, '2026-04-08 08:00:00');
        $this->kumpulkan($a, '3312501001', '2026-04-07 10:00:00'); // tepat
        $this->kumpulkan($b, '3312501001', '2026-04-20 10:00:00'); // terlambat (tenggat 15 Apr)

        $data = $this->rekap();

        $this->assertSame(RekapTugas::TERLAMBAT, $this->sel($data, '3312501001', 5)['status']);
    }
}
