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
use App\Services\StatistikKaprodi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji dashboard statistik Kaprodi (FR-21 / FRK-15).
 *
 * Kode MK dan nama event yang dipakai diambil dari file log nyata:
 * IF209 (Jaringan Komputer), IF207 (Proyek Pembuatan Prototipe), dan
 * event "A submission has been submitted." sebagai tanda tugas
 * benar-benar dikumpulkan.
 */
class KaprodiDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);
        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);
        $this->prodiLain = Prodi::create([
            'kode' => 'SI', 'nama' => 'Sistem Informasi', 'fakultas_id' => $fakultas->id,
        ]);

        // Batch import sungguhan: FK log_aktivitas.import_batch_id
        // menuntut baris induk yang ada.
        $admin = User::create([
            'nama' => 'Admin', 'email' => 'admin-'.uniqid().'@test.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);
        $this->batchId = ImportBatch::create([
            'admin_id' => $admin->id,
            'nama_file' => 'log-lms.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ])->id;
    }

    private Prodi $prodi;

    private Prodi $prodiLain;

    private int $batchId;

    // ------------------------------------------------------------ helper

    private function kaprodi(?string $prodiId): User
    {
        return User::create([
            'nama' => 'Kaprodi Uji',
            'email' => 'kaprodi-'.uniqid().'@test.local',
            'password' => 'rahasia123',
            'role' => 'kaprodi',
            'prodi_id' => $prodiId,
        ]);
    }

    /**
     * Buat kelas lengkap: mata kuliah, daftar mahasiswa, dan baris log.
     *
     * @param  array<string, string>  $mahasiswa  nim => nama
     * @param  array<int, array<string, mixed>>  $logTambahan
     */
    private function kelasLengkap(Prodi $prodi, string $kodeMk, string $namaMk, array $mahasiswa, array $logTambahan = []): Kelas
    {
        $mk = MataKuliah::create([
            'kode' => $kodeMk, 'nama' => $namaMk,
            'sks' => 3, 'prodi_id' => $prodi->id,
        ]);

        $kelas = Kelas::create([
            'kode' => 'A', 'nama' => 'Kelas A',
            'mata_kuliah_id' => $mk->id, 'prodi_id' => $prodi->id,
            'tahun_akademik' => '2025/2026', 'semester' => 2,
        ]);

        foreach ($mahasiswa as $nim => $nama) {
            Enrollment::create([
                'kelas_id' => $kelas->id,
                'nim' => (string) $nim,
                'nama' => $nama,
            ]);
        }

        foreach ($mahasiswa as $nim => $nama) {
            $this->log($kodeMk, $nim, (string) $nama, $logTambahan);
        }

        return $kelas;
    }

    /**
     * Baris log dengan format yang sama seperti hasil parser CSV nyata.
     */
    private function log(string $kodeMk, ?string $nim, ?string $nama, array $opsi = []): void
    {
        LogAktivitas::create([
            'import_batch_id' => $this->batchId,
            'occurred_at' => '2026-'.($opsi['tanggal'] ?? '05-10 09:00:00'),
            'periode' => $opsi['periode'] ?? '2026-05',
            'actor_name' => $nim === null ? (string) $nama : $nama.' '.$nim,
            'actor_identifier' => $nim,
            'actor_role' => $opsi['role'] ?? LogAktivitas::PERAN_MAHASISWA,
            'event_name' => $opsi['event'] ?? 'Course viewed',
            'kode_mata_kuliah' => $kodeMk,
            'sidik_jari' => uniqid('sj', true),
        ]);
    }

    // ------------------------------------------------------------- test

    public function test_kaprodi_melihat_statistik_mentah_dua_kelas_prodi_nya(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);

        // Layanan analytics dimatikan: dashboard HARUS tetap menampilkan
        // statistik mentah (FR-12a), bukan halaman rusak.
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn Anisa Sihite',
            '3312501008' => 'Yohana Abigail Napitu',
        ], ['periode' => '2026-05']);

        $this->kelasLengkap($this->prodi, 'IF207', 'Proyek Pembuatan Prototipe', [
            '3312511002' => 'Aliya Putri Ramadhani',
        ], ['periode' => '2026-03']);

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard');

        $respons->assertOk();
        $respons->assertSee('Statistik Analitik Mahasiswa');
        $respons->assertSee('Ririn Anisa Sihite');
        $respons->assertSee('Aliya Putri Ramadhani');
        $respons->assertSee('Jaringan Komputer');
        $respons->assertSee('Proyek Pembuatan Prototipe');
        $respons->assertSee('FR-12a');
    }

    public function test_jendela_analisis_melingkupi_seluruh_semester_kelas(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        // Kelas dibuat semester genap 2025/2026, jadi jendela analisisnya
        // Februari s.d. Juli 2026 — bukan satu bulan.
        $kelas = $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
        ], ['periode' => '2026-02']);

        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-02', 'tanggal' => '02-05 08:00:00']);
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-05', 'tanggal' => '05-20 08:00:00']);
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-06', 'tanggal' => '06-11 08:00:00']);
        // Di luar semester: harus TIDAK ikut dihitung.
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-08', 'tanggal' => '08-14 08:00:00']);

        $data = app(StatistikKaprodi::class)->untuk($kaprodi);
        $baris = collect($data['kelas'])->firstWhere('id', $kelas->id);

        $this->assertSame('2026-02', $baris['periodeAwal']);
        $this->assertSame('2026-06', $baris['periodeAkhir']);
        // 1 (helper) + 1 (Feb) + 1 (Mei) + 1 (Jun) = 4. Baris Agustus di luar.
        $this->assertSame(4, $baris['totalEvent']);

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard');
        $respons->assertOk();
        $respons->assertSee('Feb 2026 - Jun 2026');
        $respons->assertSee('semester genap 2025/2026');
    }

    public function test_semester_ganjil_mengambil_agustus_sampai_januari(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);
        $kelas = Kelas::create([
            'kode' => 'A', 'nama' => 'Kelas A', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 1,
        ]);
        Enrollment::create(['kelas_id' => $kelas->id, 'nim' => '3312501013', 'nama' => 'Ririn']);

        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2025-09', 'tanggal' => '09-10 08:00:00']);
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-01', 'tanggal' => '01-20 08:00:00']);
        // Februari sudah masuk semester genap.
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-02', 'tanggal' => '02-20 08:00:00']);

        $data = app(StatistikKaprodi::class)->untuk($kaprodi);
        $baris = collect($data['kelas'])->firstWhere('id', $kelas->id);

        $this->assertSame('2025-09', $baris['periodeAwal']);
        $this->assertSame('2026-01', $baris['periodeAkhir']);
        $this->assertSame(2, $baris['totalEvent']);
    }

    public function test_kelas_tanpa_periode_dipakai_seluruh_data_dan_disebut_di_label(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);
        // Tahun akademiknya tidak terbaca, jadi jendela tidak bisa ditebak
        // dari metadata kelas dan harus memakai seluruh data.
        $kelas = Kelas::create([
            'kode' => 'A', 'nama' => 'Kelas A', 'mata_kuliah_id' => $mk->id, 'prodi_id' => $this->prodi->id,
            'tahun_akademik' => 'belum-ditetapkan', 'semester' => 2,
        ]);
        Enrollment::create(['kelas_id' => $kelas->id, 'nim' => '3312501013', 'nama' => 'Ririn']);

        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-02', 'tanggal' => '02-05 08:00:00']);
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-10', 'tanggal' => '10-14 08:00:00']);

        $data = app(StatistikKaprodi::class)->untuk($kaprodi);
        $baris = collect($data['kelas'])->firstWhere('id', $kelas->id);

        $this->assertSame(2, $baris['totalEvent']);
        $this->assertStringContainsString('seluruh periode data', $baris['periode']);
    }

    public function test_sebaran_bulanan_dikirim_ke_tampilan(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
        ], ['periode' => '2026-02']);

        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-03', 'tanggal' => '03-10 08:00:00']);
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-03', 'tanggal' => '03-11 08:00:00']);

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard');

        $respons->assertOk();
        $respons->assertSee('Mar 2026');
        $respons->assertSee('Feb 2026');
    }

    private function ambilEvent(array $data, int $kelasId): int
    {
        return (int) collect($data['kelas'])
            ->firstWhere('id', $kelasId)['totalEvent'];
    }

    public function test_query_periode_lama_tidak_lagi_mengubah_angka(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
        ], ['periode' => '2026-02']);
        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-03', 'tanggal' => '03-02 08:00:00']);

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard?periode=2026-03');

        $respons->assertOk();
        // Jendela analisis dikunci ke semester kelas; parameter periode
        // yang terluput tidak boleh diam-diam memotong angkanya.
        $data = app(StatistikKaprodi::class)->untuk($kaprodi);
        $this->assertSame('2026-02', collect($data['kelas'])->first()['periodeAwal']);
        $this->assertSame(2, collect($data['kelas'])->first()['totalEvent']);
    }

    public function test_kaprodi_melihat_kelas_semua_prodi(): void
    {
        // Keputusan pengguna (18 Okt 2026): Kaprodi oversee semua mata
        // kuliah, jadi kelas prodi lain ikut tampil.
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', ['3312501013' => 'Ririn']);
        $this->kelasLengkap($this->prodiLain, 'SI101', 'Interaksi Manusia Komputer', ['3319999999' => 'Orang Prodi Lain']);

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard');

        $respons->assertOk();
        $respons->assertSee('Jaringan Komputer');
        $respons->assertSee('Orang Prodi Lain');
        $respons->assertSee('Interaksi Manusia Komputer');
    }

    public function test_kaprodi_tanpa_kelas_melihat_pesan_kosong(): void
    {
        // Prodi tidak lagi membatasi apa pun, jadi satu-satunya kondisi
        // dashboard kosong adalah memang belum ada kelas sama sekali.
        $kaprodi = $this->kaprodi(null);

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', ['3312501013' => 'Ririn']);

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard');

        $respons->assertOk();
        $respons->assertSee('Jaringan Komputer', escape: false);
    }

    public function test_belum_ada_kelas_mana_pun_menampilkan_pesan_kosong(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);

        $this->actingAs($kaprodi)
            ->get('/kaprodi/dashboard')
            ->assertOk()
            ->assertSee('Belum ada kelas');
    }

    public function test_mahasiswa_di_luar_daftar_ditandai_bukan_disembunyikan(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
        ]);
        // 3312501999 beraktivitas di kelas ini tapi tidak terdaftar.
        $this->log('IF209', '3312501999', 'Jika Bayangan');

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard');
        $respons->assertOk();
        $respons->assertSee('di luar daftar');
        // Tidak boleh masuk ke tabel sebagai mahasiswa kelas ini.
        $respons->assertDontSee('Jika Bayangan');
    }

    public function test_hasil_analitik_tampil_saat_layanan_hidup(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
            '3312501008' => 'Yohana',
        ]);

        $responsApi = [
            'batch_id' => 1,
            'period' => '2026-02..2026-05',
            'results' => [
                [
                    'nim' => '3312501013', 'kelas_id' => 1,
                    'engagement_rate' => 0.75, 'engagement_score' => 75.0,
                    'komponen' => ['aktifitas' => 1.0],
                    'cluster' => 0, 'cluster_label' => 'Sangat Aktif',
                    'risk_score' => 0.12, 'risk_level' => 'low', 'confidence' => 0.9,
                ],
                [
                    'nim' => '3312501008', 'kelas_id' => 1,
                    'engagement_rate' => 0.1, 'engagement_score' => 10.0,
                    'komponen' => ['aktifitas' => 0.1],
                    'cluster' => 1, 'cluster_label' => 'Sangat Rendah',
                    'risk_score' => 0.88, 'risk_level' => 'high', 'confidence' => 0.9,
                ],
            ],
            'clusters' => [],
            'meta' => [
                'api_version' => '3.1', 'algorithm' => 'kmeans',
                'jumlah_mahasiswa' => 2, 'silhouette_score' => 0.5,
                'fallback_used' => false, 'warning' => null, 'kalibrasi' => [],
            ],
        ];

        Http::fake([
            '*/analyze' => Http::response($responsApi, 200),
        ]);

        $respons = $this->actingAs($kaprodi)->get('/kaprodi/dashboard');

        $respons->assertOk();
        $respons->assertSee('Layanan analytics aktif');
        $respons->assertSee('Sangat Aktif');
        $respons->assertSee('75%');
        $respons->assertSee('high');
        $respons->assertSee('12%');
        $respons->assertSee('88%');
    }

    public function test_ambang_kalibrasi_per_mata_kuliah_dikirim(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
        ], ['periode' => '2026-02']);

        config(['services.analytics.kalibrasi.IF209' => [
            'total_event' => 600, 'hari_aktif' => 45,
            'tugas_dikumpulkan' => 18, 'jenis_event' => 28,
        ]]);

        Http::fake(['*/analyze' => Http::response([
            'batch_id' => 1, 'period' => '2026-02..2026-02',
            'results' => [], 'clusters' => [],
            'meta' => [
                'api_version' => '3.1', 'algorithm' => 'kmeans',
                'jumlah_mahasiswa' => 1, 'silhouette_score' => 0.0,
                'fallback_used' => false, 'warning' => null,
                'kalibrasi' => ['total_event' => 600.0],
            ],
        ], 200)]);

        $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk();

        Http::assertSent(function ($request) {
            $params = $request->data()['params'] ?? [];

            return ($params['batas']['total_event'] ?? null) === 600.0
                && ($params['batas']['hari_aktif'] ?? null) === 45.0;
        });
    }

    public function test_mata_kuliah_tanpa_kalibrasi_tidak_mengirim_ambang(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);

        $this->kelasLengkap($this->prodi, 'IF999', 'Mata Kuliah Baru', [
            '3312501013' => 'Ririn',
        ], ['periode' => '2026-02']);

        config(['services.analytics.kalibrasi' => []]);

        Http::fake(['*/analyze' => Http::response([
            'batch_id' => 1, 'period' => '2026-02..2026-02',
            'results' => [], 'clusters' => [],
            'meta' => [
                'api_version' => '3.1', 'algorithm' => 'kmeans',
                'jumlah_mahasiswa' => 1, 'silhouette_score' => 0.0,
                'fallback_used' => false, 'warning' => null,
                'kalibrasi' => ['total_event' => 600.0],
            ],
        ], 200)]);

        $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk();

        // Tanpa kalibrasi, params dikirim kosong ({} setelah JSON) dan
        // layanan memakai ambang bawaannya.
        Http::assertSent(function ($request) {
            $params = $request->data()['params'] ?? null;

            return $params instanceof \stdClass && (array) $params === [];
        });
    }

    public function test_periode_yang_dikirim_ke_layanan_adalah_rentang_semester(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
        ], ['periode' => '2026-02']);

        $this->log('IF209', '3312501013', 'Ririn', ['periode' => '2026-07', 'tanggal' => '07-01 08:00:00']);

        Http::fake([
            '*/analyze' => Http::response([
                'batch_id' => 1,
                'period' => '2026-02..2026-07',
                'results' => [],
                'clusters' => [],
                'meta' => [
                    'api_version' => '3.1', 'algorithm' => 'kmeans',
                    'jumlah_mahasiswa' => 1, 'silhouette_score' => 0.0,
                    'fallback_used' => false, 'warning' => null, 'kalibrasi' => [],
                ],
            ], 200),
        ]);

        $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk();

        Http::assertSent(function ($request) {
            $isi = $request->data();

            return $isi['period'] === '2026-02..2026-07'
                && $isi['students'][0]['nim'] === '3312501013';
        });
    }

    public function test_tugas_dikumpulkan_dihitung_dari_event_nyata(): void
    {
        $kaprodi = $this->kaprodi($this->prodi->id);
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));

        $this->kelasLengkap($this->prodi, 'IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn',
        ]);

        // Dua pengumpulan + satu tampilan form + satu akses kuis ditolak.
        foreach ([1, 2] as $n) {
            $this->log('IF209', '3312501013', 'Ririn', [
                'event' => 'A submission has been submitted.',
                'tanggal' => '05-1'.$n.' 08:00:00',
            ]);
        }
        $this->log('IF209', '3312501013', 'Ririn', [
            'event' => 'Submission form viewed.',
            'tanggal' => '05-13 08:00:00',
        ]);
        $this->log('IF209', '3312501013', 'Ririn', [
            'event' => 'Quiz access was prevented',
            'tanggal' => '05-14 08:00:00',
        ]);

        $data = app(StatistikKaprodi::class)->untuk($kaprodi);
        $baris = collect($data['kelas'])->first()['mahasiswa'];

        $this->assertSame(5, $baris[0]['event']);
        $this->assertSame(2, $baris[0]['tugas']);
        $this->assertSame(1, $baris[0]['kuis']);
        // 4 jenis: Course viewed (bawaan helper), submission, form, kuis.
        $this->assertSame(4, $baris[0]['jenis']);
    }
}
