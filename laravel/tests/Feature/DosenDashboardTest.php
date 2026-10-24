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
use App\Services\StatistikDosen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji dashboard analitik Dosen (FR-20 / FRK-15).
 *
 * Fokus pengujiannya bukan rumus angkanya — itu milik StatistikKelas dan
 * sudah diuji lewat KaprodiDashboardTest — melainkan apa yang boleh dilihat
 * Dosen (FR-05) dan apa yang khas sisi Dosen: asal akses kelas dan daftar
 * kerjaan "perlu perhatian".
 *
 * Kode MK dan nama event mengikuti file log nyata: IF209 (Jaringan
 * Komputer), IF207 (Proyek Pembuatan Prototipe), dan "A submission has
 * been submitted." sebagai tanda tugas dikumpulkan.
 */
class DosenDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);

        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);

        // FK log_aktivitas.import_batch_id menuntut baris induk yang ada.
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

    // ------------------------------------------------------------ helper

    private function dosen(): User
    {
        return User::create([
            'nama' => 'Yeni Rokhayati',
            'email' => 'dosen-'.uniqid().'@test.local',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);
    }

    private function buatKelas(string $kodeMk, string $namaMk, array $mahasiswa): Kelas
    {
        $mk = MataKuliah::create([
            'kode' => $kodeMk, 'nama' => $namaMk,
            'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);

        $kelas = Kelas::create([
            'kode' => 'A', 'nama' => 'Kelas A', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 2,
        ]);

        foreach ($mahasiswa as $nim => $nama) {
            Enrollment::create([
                'kelas_id' => $kelas->id,
                'nim' => (string) $nim,
                'nama' => $nama,
            ]);

            $this->log($kodeMk, (string) $nim, $nama, ['periode' => '2026-05']);
        }

        return $kelas;
    }

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

    private function matikanAnalytics(): void
    {
        config(['services.analytics.url' => 'http://127.0.0.1:9/api']);
        Http::fake(fn () => throw new ConnectionException('layanan mati'));
    }

    /**
     * Kelas dengan asal (A/B/C/D) dan jenis (Pagi/Malam) yang terisi,
     * seperti hasil impor roster Excel.
     *
     * @param  array<string, string>  $mahasiswa
     */
    private function buatKelasAsal(string $kodeMk, string $kode, ?string $asal, ?string $jenis, array $mahasiswa): Kelas
    {
        $mk = MataKuliah::firstOrCreate(
            ['kode' => $kodeMk],
            ['nama' => 'Mata Kuliah '.$kodeMk, 'sks' => 3, 'prodi_id' => $this->prodi->id],
        );

        $kelas = Kelas::create([
            'kode' => $kode,
            'nama' => "Kelas {$kode}",
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id,
            'tahun_akademik' => '2025/2026',
            'semester' => 2,
            'asal_kelas' => $asal,
            'jenis_kelas' => $jenis,
        ]);

        foreach ($mahasiswa as $nim => $nama) {
            Enrollment::create([
                'kelas_id' => $kelas->id,
                'nim' => (string) $nim,
                'nama' => $nama,
            ]);

            $this->log($kodeMk, (string) $nim, $nama, ['periode' => '2026-05']);
        }

        return $kelas;
    }

    // ------------------------------------------------------------- test

    public function test_dosen_hanya_melihat_kelas_penugasan_dan_mata_kuliah_berizin(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        // (1) Ditugaskan sebagai pengampu.
        $kelasDiaampu = $this->buatKelas('IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn Anisa Sihite',
        ]);
        $kelasDiaampu->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        // (2) Mata kuliahnya diberi izin lewat Kelola Akun.
        $kelasBerizin = $this->buatKelas('IF207', 'Proyek Pembuatan Prototipe', [
            '3312511002' => 'Aliya Putri Ramadhani',
        ]);
        // Eloquent membaca atribut dengan nama kolom aslinya (snake_case).
        // `$kelas->mataKuliahId` TIDAK error, hanya diam-diam null, dan
        // pivot pun tidak pernah terisi.
        $dosen->mataKuliahAkses()->attach($kelasBerizin->mata_kuliah_id);

        // (3) Kelas lain: tidak ditugaskan, tidak diberi izin.
        $kelasLain = $this->buatKelas('SI101', 'Interaksi Manusia Komputer', [
            '3319999999' => 'Orang Prodi Lain',
        ]);

        $respons = $this->actingAs($dosen)->get('/dosen/dashboard');

        $respons->assertOk();
        $respons->assertSee('Jaringan Komputer');
        $respons->assertSee('Proyek Pembuatan Prototipe');
        $respons->assertSee('Ririn Anisa Sihite');
        $respons->assertSee('Aliya Putri Ramadhani');

        // FR-05: kelas di luar cakupan tidak boleh bocor ke halaman.
        $respons->assertDontSee('Interaksi Manusia Komputer');
        $respons->assertDontSee('Orang Prodi Lain');

        $data = app(StatistikDosen::class)->untuk($dosen);
        $idsKelas = array_column($data['kelas'], 'id');

        $this->assertContains($kelasDiaampu->id, $idsKelas);
        $this->assertContains($kelasBerizin->id, $idsKelas);
        $this->assertNotContains($kelasLain->id, $idsKelas);
    }

    public function test_asal_akses_kelas_ditandai(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        $pengampu = $this->buatKelas('IF209', 'Jaringan Komputer', ['3312501013' => 'Ririn']);
        $pengampu->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        $pembantu = $this->buatKelas('IF210', 'Basis Data', ['3312501014' => 'Siti']);
        $pembantu->dosen()->attach($dosen->id, ['peran' => 'pembantu']);

        $berizin = $this->buatKelas('IF207', 'Proyek Pembuatan Prototipe', ['3312511002' => 'Aliya']);
        $dosen->mataKuliahAkses()->attach($berizin->mata_kuliah_id);

        $data = app(StatistikDosen::class)->untuk($dosen);
        $sumber = collect($data['kelas'])->pluck('sumberAkses', 'kode');

        $this->assertSame('Pengampu', $sumber['IF209']);
        $this->assertSame('Pembantu', $sumber['IF210']);
        $this->assertSame('Izin mata kuliah', $sumber['IF207']);
    }

    public function test_statistik_mentah_tampil_saat_layanan_analytics_mati(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        $kelas = $this->buatKelas('IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn Anisa Sihite',
        ]);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', [
            'event' => 'A submission has been submitted.', 'tanggal' => '05-11 08:00:00',
        ]);

        $respons = $this->actingAs($dosen)->get('/dosen/dashboard');

        $respons->assertOk();
        $respons->assertSee('Ririn Anisa Sihite');
        $respons->assertSee('FR-12a');

        // Daftar kerjaan kosong karena skor tidak pernah dihitung. View WAJIB
        // membedakannya dari "tidak ada mahasiswa berisiko".
        $respons->assertSee('belum bisa disusun');
    }

    public function test_daftar_perlu_perhatian_menyaring_risiko_rendah_dan_terurut(): void
    {
        $dosen = $this->dosen();

        $kelas = $this->buatKelas('IF209', 'Jaringan Komputer', [
            '3312501013' => 'Ririn Anisa Sihite',
            '3312501008' => 'Yohana Abigail Napitu',
            '3312501020' => 'Ahmad Fauzi',
        ]);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        Http::fake(['*/analyze' => Http::response([
            'batch_id' => 1, 'period' => '2026-02..2026-05',
            'results' => [
                [
                    'nim' => '3312501013', 'kelas_id' => $kelas->id,
                    'engagement_rate' => 0.1, 'cluster' => 1, 'cluster_label' => 'Sangat Rendah',
                    'risk_score' => 0.88, 'risk_level' => 'high',
                ],
                [
                    'nim' => '3312501008', 'kelas_id' => $kelas->id,
                    'engagement_rate' => 0.35, 'cluster' => 1, 'cluster_label' => 'Pasif',
                    'risk_score' => 0.55, 'risk_level' => 'medium',
                ],
                [
                    // Risiko rendah: sengaja TIDAK boleh masuk daftar kerjaan.
                    'nim' => '3312501020', 'kelas_id' => $kelas->id,
                    'engagement_rate' => 0.92, 'cluster' => 0, 'cluster_label' => 'Sangat Aktif',
                    'risk_score' => 0.05, 'risk_level' => 'low',
                ],
            ],
            'clusters' => [],
            'meta' => [
                'api_version' => '3.1', 'algorithm' => 'kmeans',
                'jumlah_mahasiswa' => 3, 'silhouette_score' => 0.4,
                'fallback_used' => false, 'warning' => null, 'kalibrasi' => [],
            ],
        ], 200)]);

        $data = app(StatistikDosen::class)->untuk($dosen);

        $this->assertTrue($data['analisisAda']);
        $this->assertSame(2, $data['jumlahPerluPerhatian']);
        $this->assertSame('Ririn Anisa Sihite', $data['perluPerhatian'][0]['nama']);
        $this->assertSame('Yohana Abigail Napitu', $data['perluPerhatian'][1]['nama']);
        $this->assertSame('high', $data['perluPerhatian'][0]['level']);

        $respons = $this->actingAs($dosen)->get('/dosen/dashboard');
        $respons->assertOk();
        $respons->assertSee('Perlu perhatian');
        $respons->assertSee('2 mahasiswa berisiko sedang atau tinggi');
        // Nama mahasiswa berisiko rendah tetap ada di tabel kelas, tapi
        // tidak boleh masuk daftar kerjaan.
        $this->assertStringNotContainsString(
            'Ahmad Fauzi',
            $this->bagianPerluPerhatian($respons->getContent()),
        );
    }

    public function test_daftar_perlu_perhatian_dibatasi_sepuluh_namun_jumlahnya_utuh(): void
    {
        $dosen = $this->dosen();

        $mahasiswa = [];
        $hasil = [];
        // 12 mahasiswa: cukup untuk melebihi batas 10 dan membuktikan bahwa
        // yang dipangkas hanya tampilannya, bukan hitungannya.
        for ($i = 1; $i <= 12; $i++) {
            $nim = '33125010'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $mahasiswa[$nim] = 'Mahasiswa '.$i;
            $hasil[] = [
                'nim' => $nim, 'kelas_id' => 0,
                'engagement_rate' => 0.1, 'cluster' => 1, 'cluster_label' => 'Sangat Rendah',
                'risk_score' => 0.95 - ($i / 100), 'risk_level' => 'high',
            ];
        }

        $kelas = $this->buatKelas('IF209', 'Jaringan Komputer', $mahasiswa);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        foreach ($hasil as $baris) {
            $baris['kelas_id'] = $kelas->id;
        }

        Http::fake(['*/analyze' => Http::response([
            'batch_id' => 1, 'period' => '2026-02..2026-05',
            'results' => $hasil, 'clusters' => [],
            'meta' => [
                'api_version' => '3.1', 'algorithm' => 'kmeans',
                'jumlah_mahasiswa' => count($mahasiswa), 'silhouette_score' => 0.4,
                'fallback_used' => false, 'warning' => null, 'kalibrasi' => [],
            ],
        ], 200)]);

        $data = app(StatistikDosen::class)->untuk($dosen);

        $this->assertCount(10, $data['perluPerhatian']);
        $this->assertSame(10, $data['jumlahTampilPerluPerhatian']);
        $this->assertSame(12, $data['jumlahPerluPerhatian']);
    }

    public function test_dosen_tanpa_kelas_melihat_pesan_yang_menuntun_ke_admin(): void
    {
        $dosen = $this->dosen();

        // Ada kelas di sistem, tapi bukan miliknya: harus tetap kosong.
        $this->buatKelas('IF209', 'Jaringan Komputer', ['3312501013' => 'Ririn Anisa Sihite']);

        $respons = $this->actingAs($dosen)->get('/dosen/dashboard');

        $respons->assertOk();
        $respons->assertSee('Belum ada kelas yang bisa Anda lihat');
        $respons->assertSee('Penugasan Akademik');
        $respons->assertSee('Kelola Akun');
        $respons->assertDontSee('Ririn Anisa Sihite');
    }

    public function test_dosen_melihat_ambang_kalibrasi_mata_kuliah_yang_dipakai(): void
    {
        $dosen = $this->dosen();

        $kelas = $this->buatKelas('IF207', 'Proyek Pembuatan Prototipe', [
            '3312511002' => 'Aliya Putri Ramadhani',
        ]);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        config(['services.analytics.kalibrasi.IF207' => [
            'total_event' => 190, 'hari_aktif' => 36,
            'tugas_dikumpulkan' => 8, 'jenis_event' => 14,
        ]]);

        Http::fake(['*/analyze' => Http::response([
            'batch_id' => 1, 'period' => '2026-02..2026-05',
            'results' => [], 'clusters' => [],
            'meta' => [
                'api_version' => '3.1', 'algorithm' => 'kmeans',
                'jumlah_mahasiswa' => 1, 'silhouette_score' => 0.0,
                'fallback_used' => false, 'warning' => null, 'kalibrasi' => [],
            ],
        ], 200)]);

        $this->actingAs($dosen)->get('/dosen/dashboard')->assertOk();

        // Ambang IF207 tidak boleh terkirim sebagai ambang IF209.
        Http::assertSent(function ($request) {
            $batas = $request->data()['params']['batas'] ?? [];

            return ($batas['total_event'] ?? null) === 190.0;
        });
    }

    public function test_dosen_tidak_bisa_membuka_dashboard_peran_lain(): void
    {
        $dosen = $this->dosen();

        $this->actingAs($dosen)->get('/kaprodi/dashboard')->assertForbidden();
        $this->actingAs($dosen)->get('/admin/dashboard')->assertForbidden();
    }

    // --------------------------------------------------------- per kelas

    public function test_dua_kelas_satu_mata_kuliah_tidak_saling_menimpa_angka(): void
    {
        // Regresi: hasil analitik sengaja dihitung SEKALI untuk seluruh
        // kelas satu mata kuliah (hemat 1,8 detik), jadi harus dipotong
        // ke mahasiswa kelasnya sendiri. Kalau tidak, tiap blok kelas
        // melaporkan risiko dan klaster SELURUH mata kuliah, dan jumlah
        // risiko tinggi bisa melebihi jumlah mahasiswa yang ada.
        $dosen = $this->dosen();

        $aPagi = $this->buatKelasAsal('IF209', 'A Pagi', 'A', 'Pagi', [
            '3312501013' => 'Ririn', '3312501008' => 'Yohana',
        ]);
        $bPagi = $this->buatKelasAsal('IF209', 'B Pagi', 'B', 'Pagi', [
            '3312501014' => 'Siti', '3312501015' => 'Andi',
        ]);

        $dosen->mataKuliahAkses()->attach($aPagi->mata_kuliah_id);

        Http::fake(['*/analyze' => Http::response([
            'batch_id' => 1, 'period' => '2026-02..2026-05',
            'results' => [
                ['nim' => '3312501013', 'kelas_id' => $aPagi->id, 'engagement_rate' => 0.1,
                    'cluster' => 1, 'cluster_label' => 'Sangat Rendah', 'risk_score' => 0.9, 'risk_level' => 'high'],
                ['nim' => '3312501008', 'kelas_id' => $aPagi->id, 'engagement_rate' => 0.9,
                    'cluster' => 0, 'cluster_label' => 'Sangat Aktif', 'risk_score' => 0.05, 'risk_level' => 'low'],
                ['nim' => '3312501014', 'kelas_id' => $bPagi->id, 'engagement_rate' => 0.2,
                    'cluster' => 1, 'cluster_label' => 'Pasif', 'risk_score' => 0.7, 'risk_level' => 'high'],
                ['nim' => '3312501015', 'kelas_id' => $bPagi->id, 'engagement_rate' => 0.3,
                    'cluster' => 1, 'cluster_label' => 'Pasif', 'risk_score' => 0.6, 'risk_level' => 'medium'],
            ],
            'clusters' => [],
            'meta' => [
                'api_version' => '3.1', 'algorithm' => 'kmeans',
                'jumlah_mahasiswa' => 4, 'silhouette_score' => 0.4,
                'fallback_used' => false, 'warning' => null, 'kalibrasi' => [],
            ],
        ], 200)]);

        $data = app(StatistikDosen::class)->untuk($dosen);
        $kelas = collect($data['kelas'])->keyBy('kelasKode');

        // Hanya boleh satu permintaan ke layanan untuk dua kelas satu MK.
        Http::assertSentCount(1);

        $this->assertSame(1, $kelas['A Pagi']['risikoTinggi']);
        $this->assertSame(1, $kelas['B Pagi']['risikoTinggi']);
        $this->assertSame(2, $data['ringkasan']['risikoTinggi']);
        $this->assertSame(4, $data['ringkasan']['mahasiswaUnik']);

        // Klaster per kelas harus berjumlah sesuai anggota kelasnya, bukan
        // seluruh mata kuliah.
        $totalKlaster = fn (array $k) => array_sum(array_column($k['klaster'], 'jumlah'));
        $this->assertSame(2, $totalKlaster($kelas['A Pagi']));
        $this->assertSame(2, $totalKlaster($kelas['B Pagi']));
    }

    // ------------------------------------------------------------- filter

    public function test_filter_waktu_menampilkan_hanya_kelas_pagi(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        $pagi = $this->buatKelasAsal('IF209', 'A Pagi', 'A', 'Pagi', ['3312501013' => 'Ririn']);
        $malam = $this->buatKelasAsal('IF207', 'B Malam', 'B', 'Malam', ['3312511002' => 'Aliya']);

        $pagi->dosen()->attach($dosen->id, ['peran' => 'pengampu']);
        $dosen->mataKuliahAkses()->attach($malam->mata_kuliah_id);

        $data = app(StatistikDosen::class)->untuk($dosen, ['jenis' => 'Pagi']);

        $this->assertSame([$pagi->id], array_column($data['kelas'], 'id'));
        $this->assertSame('Pagi', $data['filter']['jenis']);
        $this->assertSame(['Malam', 'Pagi'], $data['filter']['pilihanJenis']);

        $html = $this->actingAs($dosen)->get('/dosen/dashboard?jenis=Pagi')->assertOk()->getContent();

        $this->assertStringContainsString('Ririn', $html);
        $this->assertStringNotContainsString('Aliya', $html);
        $this->assertStringContainsString('Menampilkan 1 dari 2 kelas', $html);
    }

    public function test_filter_kelas_asal_bisa_digabung_dengan_waktu(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        $aPagi = $this->buatKelasAsal('IF209', 'A Pagi', 'A', 'Pagi', ['3312501013' => 'Ririn']);
        $bPagi = $this->buatKelasAsal('IF207', 'B Pagi', 'B', 'Pagi', ['3312511002' => 'Aliya']);
        $aMalam = $this->buatKelasAsal('IF207', 'A Malam', 'A', 'Malam', ['3312501014' => 'Siti']);

        $dosen->mataKuliahAkses()->sync([
            $aPagi->mata_kuliah_id, $bPagi->mata_kuliah_id, $aMalam->mata_kuliah_id,
        ]);

        $data = app(StatistikDosen::class)->untuk($dosen, ['jenis' => 'Pagi', 'asal' => 'A']);

        $this->assertSame([$aPagi->id], array_column($data['kelas'], 'id'));
    }

    public function test_filter_ngawur_diabaikan_bukan_mengosongkan_dashboard(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        $kelas = $this->buatKelasAsal('IF209', 'A Pagi', 'A', 'Pagi', ['3312501013' => 'Ririn']);
        $dosen->mataKuliahAkses()->attach($kelas->mata_kuliah_id);

        $data = app(StatistikDosen::class)->untuk($dosen, ['jenis' => 'ngawur']);

        // Nilai di luar pilihan harus diabaikan. Kalau tidak, mengetik
        // ?jenis=ngawur di URL akan membuat dashboard kosong tanpa alasan.
        $this->assertNull($data['filter']['jenis']);
        $this->assertSame([$kelas->id], array_column($data['kelas'], 'id'));
    }

    public function test_filter_tidak_memperbesar_akses(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        $milik = $this->buatKelasAsal('IF209', 'A Pagi', 'A', 'Pagi', ['3312501013' => 'Ririn']);
        $bukanMilik = $this->buatKelasAsal('IF207', 'A Pagi', 'A', 'Pagi', ['3312511002' => 'Aliya']);

        $dosen->mataKuliahAkses()->attach($milik->mata_kuliah_id);

        // Menyaring dengan nilai yang sama persis tidak boleh membuat kelas
        // milik orang lain ikut terbawa.
        $data = app(StatistikDosen::class)->untuk($dosen, ['jenis' => 'Pagi', 'asal' => 'A']);

        $this->assertSame([$milik->id], array_column($data['kelas'], 'id'));
        $this->assertNotContains($bukanMilik->id, array_column($data['kelas'], 'id'));
    }

    public function test_kelas_tanpa_kelas_asal_tersembunyi_saat_filter_dipakai(): void
    {
        $dosen = $this->dosen();
        $this->matikanAnalytics();

        $aPagi = $this->buatKelasAsal('IF209', 'A Pagi', 'A', 'Pagi', ['3312501013' => 'Ririn']);
        $tanpa = $this->buatKelasAsal('IF207', 'Tanpa Kelas Asal', null, null, ['3312311034' => 'Rena']);

        $dosen->mataKuliahAkses()->sync([$aPagi->mata_kuliah_id, $tanpa->mata_kuliah_id]);

        $semua = app(StatistikDosen::class)->untuk($dosen);
        $this->assertCount(2, $semua['kelas']);

        $disaring = app(StatistikDosen::class)->untuk($dosen, ['jenis' => 'Pagi']);

        // Kelas tanpa asal/jenis ikut hilang begitu ada filter jenis,
        // karena memang tidak punya nilai itu.
        $this->assertSame([$aPagi->id], array_column($disaring['kelas'], 'id'));
    }

    /** Ambil hanya HTML bagian daftar "Perlu perhatian". */
    private function bagianPerluPerhatian(string $html): string
    {
        $mulai = strpos($html, 'Perlu perhatian');

        if ($mulai === false) {
            return '';
        }

        return substr($html, $mulai, 4000);
    }
}
