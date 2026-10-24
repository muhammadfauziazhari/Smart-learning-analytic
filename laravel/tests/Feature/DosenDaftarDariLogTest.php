<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\ImportBatch;
use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Perintah `dosen:daftar-dari-log` (FR-03: penetapan dosen pengampu).
 *
 * Yang dijaga di sini: akun hanya dibuat untuk identitas yang punya
 * bukti bertindak sebagai pengajar, dan penugasan pengampu hanya boleh
 * menunjuk akun yang benar-benar ada.
 */
class DosenDaftarDariLogTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    private ImportBatch $batch;

    /** Jumlah akun sebelum perintah dijalankan (admin dari setUp). */
    private int $akunAwal;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);

        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);

        $admin = User::create([
            'nama' => 'Admin', 'email' => 'admin@uji.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);

        $this->batch = ImportBatch::create([
            'admin_id' => $admin->id,
            'nama_file' => 'log-uji.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ]);

        $this->akunAwal = User::count();
    }

    private function log(string $kodeMk, string $identitas, string $nama, string $event, string $peran = LogAktivitas::PERAN_MAHASISWA): void
    {
        LogAktivitas::create([
            'import_batch_id' => $this->batch->id,
            'occurred_at' => '2026-05-10 09:00:00',
            'periode' => '2026-05',
            'actor_name' => $nama.' '.$identitas,
            'actor_identifier' => $identitas,
            'actor_role' => $peran,
            'event_name' => $event,
            'kode_mata_kuliah' => $kodeMk,
            'sidik_jari' => uniqid('sj', true),
        ]);
    }

    private function kelas(string $kodeMk): Kelas
    {
        $mk = MataKuliah::create([
            'kode' => $kodeMk, 'nama' => 'Jaringan Komputer', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);

        return Kelas::create([
            'kode' => 'A', 'nama' => 'Kelas A', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 2,
        ]);
    }

    public function test_membuat_akun_untuk_pengajar_di_log(): void
    {
        $this->log('IF209', '225364', 'SyaprillaDonataAMdKom', 'Course module created');
        $this->log('IF209', '225364', 'SyaprillaDonataAMdKom', 'Course module created', LogAktivitas::PERAN_MAHASISWA);
        // Mahasiswa biasa tidak boleh dapat akun.
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $dosen = User::where('nomor_induk', '225364')->first();

        $this->assertNotNull($dosen);
        $this->assertSame('dosen', $dosen->role);
        $this->assertSame('SyaprillaDonataAMdKom', $dosen->nama, 'Nomor induk harus dibuang dari nama');
        $this->assertSame($this->prodi->id, $dosen->prodi_id);
        $this->assertSame($this->akunAwal + 1, User::count(), 'Hanya pengajar yang dapat akun');

        // Password harus tersimpan dalam bentuk hash, bukan polos.
        $this->assertStringStartsWith('$2y$', $dosen->password);
    }

    public function test_password_baru_ditampilkan_satu_kali(): void
    {
        $this->log('IF209', '225364', 'SyaprillaDonataAMdKom', 'Course module created');

        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI'])
            ->expectsOutputToContain('Password')
            ->assertSuccessful();

        $dosen = User::where('nomor_induk', '225364')->firstOrFail();

        // Yang tersimpan hanya hash-nya; password polos tidak ikut.
        $this->assertTrue(Hash::isHashed($dosen->password));
    }

    public function test_dry_run_tidak_membuat_akun(): void
    {
        $this->log('IF209', '225364', 'SyaprillaDonataAMdKom', 'Course module created');

        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI', '--dry-run' => true])
            ->expectsOutputToContain('akan dibuat')
            ->assertSuccessful();

        $this->assertSame($this->akunAwal, User::count());
    }

    public function test_pengelola_tidak_didaftarkan_kecuali_diminta(): void
    {
        $this->log('IF209', '3312311020', 'Pengelola LMS', 'User unenrolled from course');

        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI'])->assertSuccessful();
        $this->assertNull(User::where('nomor_induk', '3312311020')->first());

        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI', '--sertakan-pengelola' => true])
            ->assertSuccessful();
        $this->assertNotNull(User::where('nomor_induk', '3312311020')->first());
    }

    public function test_akun_yang_sudah_ada_tidak_do(): void
    {
        $lama = User::create([
            'nama' => 'Yeni Rokhayati', 'nomor_induk' => '198602192014042001',
            'email' => 'dosen@gmail.com', 'password' => 'rahasia123',
            'role' => 'dosen', 'prodi_id' => $this->prodi->id,
        ]);

        $this->log('IF209', '198602192014042001', 'Yeni Rokhayati', 'Course module created');

        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI'])
            ->expectsOutputToContain('akun ada: dosen@gmail.com')
            ->assertSuccessful();

        $this->assertSame($this->akunAwal + 1, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'yeni-rokhayati@edutrack.local']);
    }

    public function test_dua_dosen_bernama_sama_tetap_punya_akun_terpisah(): void
    {
        $this->log('IF209', '225364', 'Andri Albertha Pratama', 'Course module created');
        $this->log('IF209', '198512052021211003', 'Andri Albertha Pratama', 'Course module created');

        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $this->assertSame($this->akunAwal + 2, User::count());
        $this->assertSame($this->akunAwal + 2, User::distinct()->count('email'));
    }

    public function test_pengampu_kelas_hanya_untuk_akun_yang_ada(): void
    {
        $kelas = $this->kelas('IF209');

        $ada = User::create([
            'nama' => 'Yeni Rokhayati', 'nomor_induk' => '198602192014042001',
            'email' => 'dosen@gmail.com', 'password' => 'rahasia123',
            'role' => 'dosen', 'prodi_id' => $this->prodi->id,
        ]);

        $kaprodi = User::create([
            'nama' => 'Agus Fatulloh', 'nomor_induk' => '197901132021211003',
            'email' => 'kaprodi@gmail.com', 'password' => 'rahasia123',
            'role' => 'kaprodi', 'prodi_id' => $this->prodi->id,
        ]);

        // Pengajar yang aktif di kelas ini.
        $this->log('IF209', '198602192014042001', 'Yeni Rokhayati', 'Course module created');
        $this->log('IF209', '197901132021211003', 'Agus Fatulloh', 'Course module created');
        // Pengajar lain, tidak ada akunnya.
        $this->log('IF209', '225364', 'SyaprillaDonataAMdKom', 'Course module created');

        $this->artisan('dosen:daftar-dari-log', ['--pengampu' => true])->assertSuccessful();

        // Yeni pengampu, Agus pembantu.
        $this->assertSame('pengampu', DB::table('kelas_dosen')->where('user_id', $ada->id)->value('peran'));
        $this->assertSame('pembantu', DB::table('kelas_dosen')->where('user_id', $kaprodi->id)->value('peran'));
        $this->assertSame(1, $ada->kelasDiampu()->where('kelas.id', $kelas->id)->count());

        // Akun Syaprilla dibuat pada langkah pertama, dan penugasan diambil
        // setelah itu — jadi dia langsung tercatat pengampu tanpa perlu
        // perintah kedua.
        $syaprilla = User::where('nomor_induk', '225364')->first();

        $this->assertNotNull($syaprilla);
        $this->assertSame(3, DB::table('kelas_dosen')->count());
        $this->assertSame(
            'pengampu',
            DB::table('kelas_dosen')->where('user_id', $syaprilla->id)->value('peran'),
        );
    }

    public function test_pengampu_bisa_dijalankan_berulang_tanpa_duplikat(): void
    {
        $this->kelas('IF209');

        User::create([
            'nama' => 'Yeni Rokhayati', 'nomor_induk' => '198602192014042001',
            'email' => 'dosen@gmail.com', 'password' => 'rahasia123',
            'role' => 'dosen', 'prodi_id' => $this->prodi->id,
        ]);

        $this->log('IF209', '198602192014042001', 'Yeni Rokhayati', 'Course module created');

        $this->artisan('dosen:daftar-dari-log', ['--pengampu' => true])->assertSuccessful();
        $this->artisan('dosen:daftar-dari-log', ['--pengampu' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('kelas_dosen')->count());
    }

    public function test_log_kosong_tidak_membuat_apa_pun(): void
    {
        $this->artisan('dosen:daftar-dari-log', ['--prodi' => 'TI'])
            ->expectsOutputToContain('Tidak ada identitas pengajar')
            ->assertSuccessful();

        $this->assertSame($this->akunAwal, User::count());
    }
}
