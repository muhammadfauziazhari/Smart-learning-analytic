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
use App\Services\StatistikAktivitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji kartu aktivitas dashboard Dosen (FR-20).
 *
 * Tiga keputusan pengguna (22 Okt 2026) yang dijaga di sini:
 *
 *   1. "Login" = mahasiswa berbeda yang beraktivitas per hari. Log Moodle
 *      tidak punya event login, jadi ini proxy yang disepakati. Rata-rata
 *      hanya atas hari yang ada aktivitasnya.
 *   2. "Persentase akses materi" = rata-rata persentase per kelas,
 *      bukan persentase gabungan seluruh mahasiswa.
 *   3. "Clustering aktif/sedang/at risk" = level risiko analytics
 *      (low/medium/high). Mahasiswa tanpa skor masuk "belum", bukan "aktif".
 *
 * Ditambah cakupan materi: hanya nama berawalan "Modul"/"Materi".
 */
class StatistikAktivitasTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    private int $batchId;

    private MataKuliah $mk;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);
        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);

        $admin = User::create([
            'nama' => 'Admin', 'email' => 'admin-'.uniqid().'@test.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);

        $this->batchId = ImportBatch::create([
            'admin_id' => $admin->id,
            'nama_file' => 'log-lms.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ])->id;

        $this->mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer',
            'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);
    }

    // ------------------------------------------------------------ helper

    /**
     * @param  array<string, string>  $mahasiswa  nim => nama
     */
    private function kelas(string $kode, array $mahasiswa): Kelas
    {
        $kelas = Kelas::create([
            'kode' => $kode,
            'nama' => 'Kelas '.$kode,
            'mata_kuliah_id' => $this->mk->id,
            'prodi_id' => $this->prodi->id,
            'tahun_akademik' => '2025/2026',
            'semester' => 2,
        ]);

        foreach ($mahasiswa as $nim => $nama) {
            Enrollment::create([
                'kelas_id' => $kelas->id,
                'nim' => (string) $nim,
                'nama' => $nama,
            ]);
        }

        return $kelas;
    }

    /**
     * @param  array<string, string>  $opsi
     */
    private function log(string $nim, array $opsi = []): void
    {
        LogAktivitas::create([
            'import_batch_id' => $this->batchId,
            'occurred_at' => $opsi['tanggal'] ?? '2026-03-02 09:00:00',
            'periode' => $opsi['periode'] ?? '2026-03',
            'actor_name' => $nim,
            'actor_identifier' => $nim,
            'actor_role' => $opsi['role'] ?? LogAktivitas::PERAN_MAHASISWA,
            'event_name' => $opsi['event'] ?? LogAktivitas::EVENT_MODUL_DILIHAT,
            'context_type' => $opsi['konteks'] ?? 'File',
            'context_name' => $opsi['nama'] ?? 'Modul Praktikum 1',
            'kode_mata_kuliah' => $opsi['kode'] ?? 'IF209',
            'sidik_jari' => uniqid('sj', true),
        ]);
    }

    /**
     * Bentuk data yang biasa diterima StatistikAktivitas dari StatistikDosen.
     *
     * @param  array<int, Kelas>  $kelas
     * @param  array<string, ?string>  $level  nim => level risiko
     * @return list<array<string, mixed>>
     */
    private function dataKelas(array $kelas, array $level = []): array
    {
        $hasil = [];

        foreach ($kelas as $k) {
            $mahasiswa = Enrollment::where('kelas_id', $k->id)
                ->get()
                ->map(fn ($e) => ['nim' => (string) $e->nim, 'level' => $level[(string) $e->nim] ?? null])
                ->all();

            $hasil[] = [
                'id' => $k->id,
                'kode' => 'IF209',
                'periodeAwal' => '2026-02',
                'periodeAkhir' => '2026-07',
                'mahasiswa' => $mahasiswa,
            ];
        }

        return $hasil;
    }

    // ------------------------------------------------------------- test

    public function test_login_dihitung_dari_mahasiswa_berbeda_per_hari_aktif(): void
    {
        $kelas = $this->kelas('A', [
            '1111111111' => 'Ani', '2222222222' => 'Budi', '3333333333' => 'Cici',
        ]);

        // 2 Maret: 3 mahasiswa; 3 Maret: 1; 4 Maret: 2.
        foreach (['1111111111', '2222222222', '3333333333'] as $nim) {
            $this->log($nim, ['event' => 'Course viewed', 'tanggal' => '2026-03-02 08:00:00']);
        }
        $this->log('1111111111', ['event' => 'Course viewed', 'tanggal' => '2026-03-03 08:00:00']);
        $this->log('1111111111', ['event' => 'Course viewed', 'tanggal' => '2026-03-04 08:00:00']);
        $this->log('2222222222', ['event' => 'Course viewed', 'tanggal' => '2026-03-04 09:00:00']);

        // Di luar jendela semester: tidak boleh menambah hari aktif.
        foreach (['1111111111', '2222222222', '3333333333'] as $nim) {
            $this->log($nim, [
                'event' => 'Course viewed', 'tanggal' => '2026-09-01 08:00:00', 'periode' => '2026-09',
            ]);
        }

        // Dosen yang membuka course bukan "mahasiswa login".
        $this->log('1111111111', [
            'event' => 'Course viewed', 'tanggal' => '2026-03-05 08:00:00', 'role' => LogAktivitas::PERAN_DOSEN,
        ]);

        $data = app(StatistikAktivitas::class)->untuk($this->dataKelas([$kelas]));

        $this->assertSame(3, $data['login']['maks']);
        $this->assertSame(1, $data['login']['min']);
        $this->assertSame(2.0, $data['login']['rata']);
        $this->assertSame(3, $data['login']['hari']);
    }

    public function test_persen_akses_materi_dirata_ratakan_per_kelas(): void
    {
        // Kelas A: 1 dari 2 mahasiswa membuka materi = 50%.
        $kelasA = $this->kelas('A', ['1111111111' => 'Ani', '2222222222' => 'Budi']);
        // Kelas B: 2 dari 2 mahasiswa membuka materi = 100%.
        $kelasB = $this->kelas('B', ['3333333333' => 'Cici', '4444444444' => 'Dodi']);

        $this->log('1111111111');
        $this->log('3333333333');
        $this->log('4444444444');

        // Forum bukan materi; aktivitas Budi tidak boleh dihitung sebagai akses materi.
        $this->log('2222222222', ['nama' => 'Forum Diskusi', 'konteks' => 'Forum']);

        $data = app(StatistikAktivitas::class)->untuk($this->dataKelas([$kelasA, $kelasB]));

        // (50 + 100) / 2 = 75, bukan 3/4 mahasiswa = 75 (kebetulan sama di
        // data ini, jadi rentangnya yang membuktikan perhitungan per kelas).
        $this->assertSame(75.0, $data['materi']['persen']);
        $this->assertSame(50.0, $data['materi']['min']);
        $this->assertSame(100.0, $data['materi']['maks']);
        $this->assertSame(2, $data['materi']['jumlahKelas']);
    }

    public function test_daftar_materi_hanya_modul_dan_materi_diurutkan_dengan_persen(): void
    {
        $kelas = $this->kelas('A', ['1111111111' => 'Ani']);

        // 12 materi dengan akses menurun, total 120 akses.
        $akses = [24, 21, 18, 15, 12, 9, 6, 5, 4, 3, 2, 1];
        foreach ($akses as $i => $jumlah) {
            for ($n = 0; $n < $jumlah; $n++) {
                $this->log('1111111111', ['nama' => 'Modul Praktikum '.($i + 1)]);
            }
        }

        // Tidak ikut: soal ujian, video, dan nama materi di konteks forum.
        for ($n = 0; $n < 50; $n++) {
            $this->log('1111111111', ['nama' => 'Video - ARP Operation']);
        }
        for ($n = 0; $n < 40; $n++) {
            $this->log('1111111111', ['nama' => 'Soal AAS Teori dan Praktikum']);
        }
        for ($n = 0; $n < 30; $n++) {
            $this->log('1111111111', ['nama' => 'Modul Praktikum 1', 'konteks' => 'Forum']);
        }

        $data = app(StatistikAktivitas::class)->untuk($this->dataKelas([$kelas]));

        $this->assertSame(12, $data['jumlahMateri']);
        $this->assertSame(120, $data['totalAkses']);

        $teratas = $data['materiSering'][0];
        $this->assertSame('Modul Praktikum 1', $teratas['nama']);
        $this->assertSame(24, $teratas['akses']);
        $this->assertSame(20.0, $teratas['persen']);
        $this->assertSame(100, $teratas['lebar']);

        $this->assertCount(5, $data['materiSering']);
        $this->assertSame('Modul Praktikum 5', $data['materiSering'][4]['nama']);

        $terbawah = $data['materiJarang'][0];
        $this->assertSame('Modul Praktikum 12', $terbawah['nama']);
        $this->assertSame(1, $terbawah['akses']);
        $this->assertSame(0.8, $terbawah['persen']);
        $this->assertSame(20, $terbawah['lebar']);

        $this->assertCount(5, $data['materiJarang']);
        $this->assertSame('Modul Praktikum 8', $data['materiJarang'][4]['nama']);

        $nama = collect($data['materiSering'])->pluck('nama')
            ->merge(collect($data['materiJarang'])->pluck('nama'))
            ->implode(' | ');
        $this->assertStringNotContainsString('Video', $nama);
        $this->assertStringNotContainsString('Soal AAS', $nama);
    }

    public function test_sebaran_risiko_memisahkan_yang_belum_dianalisis(): void
    {
        $kelas = $this->kelas('A', ['1111111111' => 'Ani']);

        $daftar = [[
            'id' => 1,
            'kode' => 'IF209',
            'periodeAwal' => '2026-02',
            'periodeAkhir' => '2026-07',
            'mahasiswa' => [
                ['nim' => '1111111111', 'level' => 'low'],
                ['nim' => '2222222222', 'level' => 'medium'],
                ['nim' => '3333333333', 'level' => 'high'],
                ['nim' => '4444444444', 'level' => null],
            ],
        ]];

        $data = app(StatistikAktivitas::class)->untuk($daftar);

        $this->assertSame([
            'aktif' => 1, 'sedang' => 1, 'atRisk' => 1, 'belum' => 1, 'total' => 4,
        ], $data['risiko']);
    }

    public function test_dashboard_menampilkan_kartu_aktivitas_dan_daftar_materi(): void
    {
        $dosen = User::create([
            'nama' => 'Yeni Rokhayati',
            'email' => 'dosen-'.uniqid().'@test.local',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);

        $kelas = $this->kelas('A', ['1111111111' => 'Ani', '2222222222' => 'Budi']);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        $this->log('1111111111', ['event' => 'Course viewed']);
        $this->log('2222222222', ['event' => 'Course viewed', 'tanggal' => '2026-03-03 09:00:00']);
        $this->log('1111111111');
        $this->log('2222222222');

        Http::fake(['*/analyze' => Http::response([
            'batch_id' => 1,
            'period' => '2026-03..2026-03',
            'results' => [
                [
                    'nim' => '1111111111', 'kelas_id' => $kelas->id,
                    'engagement_rate' => 0.8, 'cluster' => 0, 'cluster_label' => 'Sangat Aktif',
                    'risk_score' => 0.1, 'risk_level' => 'low',
                ],
                [
                    'nim' => '2222222222', 'kelas_id' => $kelas->id,
                    'engagement_rate' => 0.4, 'cluster' => 1, 'cluster_label' => 'Cukup',
                    'risk_score' => 0.5, 'risk_level' => 'medium',
                ],
            ],
        ])]);

        $respons = $this->actingAs($dosen)->get('/dosen/dashboard');

        $respons->assertOk();
        $respons->assertSee('Mahasiswa Login per Hari');
        $respons->assertSee('Akses Materi');
        $respons->assertSee('Clustering Kelas');
        $respons->assertSee('Analytics Materi');
        $respons->assertSee('Materi Pembelajaran yang Paling Sering Diakses');
        $respons->assertSee('Materi Pembelajaran yang Jarang Diakses');
        $respons->assertSee('Modul Praktikum 1');
        $respons->assertSee('Aktif (risiko rendah)');
        $respons->assertSee('Sedang (risiko sedang)');
        $respons->assertSee('At Risk (risiko tinggi)');
    }
}
