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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perintah `kelas:susun-dari-log`.
 *
 * Yang diuji adalah aturan yang menjaga angka statistik tetap jujur:
 * identitas yang terbukti berperilaku pengajar atau pengelola tidak boleh
 * dihitung sebagai mahasiswa, dan prodi tujuan tidak boleh ditebak.
 */
class SusunKelasDariLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Prodi $prodi;

    private ImportBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);

        $this->admin = User::create([
            'nama' => 'Admin',
            'email' => 'admin@uji.local',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);

        $this->batch = ImportBatch::create([
            'admin_id' => $this->admin->id,
            'nama_file' => 'log-uji.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ]);
    }

    private function log(
        string $kodeMk,
        string $nim,
        string $nama,
        string $event,
        string $tanggal = '2026-05-10 09:00:00',
        string $peran = LogAktivitas::PERAN_MAHASISWA,
    ): void {
        LogAktivitas::create([
            'import_batch_id' => $this->batch->id,
            'occurred_at' => $tanggal,
            'periode' => substr($tanggal, 0, 7),
            'actor_name' => $nama.' '.$nim,
            'actor_identifier' => $nim,
            'actor_role' => $peran,
            'event_name' => $event,
            'kode_mata_kuliah' => $kodeMk,
            'nama_mata_kuliah' => 'Jaringan Komputer',
            'sidik_jari' => uniqid('sj', true),
        ]);
    }

    /** Akun yang benar-benar ada di sistem dan muncul sebagai pengajar di log. */
    private function akunPengajar(string $nomorInduk, string $email, ?int $prodiId = null): User
    {
        return User::create([
            'nama' => 'Pengajar',
            'email' => $email,
            'password' => 'rahasia123',
            'role' => 'dosen',
            'nomor_induk' => $nomorInduk,
            'prodi_id' => $prodiId ?? $this->prodi->id,
        ]);
    }

    public function test_membuat_kelas_dan_daftar_mahasiswa_dari_log(): void
    {
        $this->akunPengajar('225364', 'dosen1@uji.local');
        // Pengajar ini benar-benar muncul di log, jadi prodi bisa dipastikan.
        $this->log('IF209', '225364', 'SyaprillaDonata', 'Course module created', '2026-05-10 08:00:00', LogAktivitas::PERAN_DOSEN);

        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed', '2026-05-11 09:00:00');
        $this->log('IF209', '3312501008', 'Yohana Abigail Napitu', 'Course viewed');

        $this->artisan('kelas:susun-dari-log')->assertSuccessful();

        $mk = MataKuliah::where('kode', 'IF209')->first();
        $this->assertNotNull($mk);
        $this->assertSame('Jaringan Komputer', $mk->nama);
        $this->assertSame($this->prodi->id, $mk->prodi_id);

        $kelas = Kelas::where('mata_kuliah_id', $mk->id)->first();
        $this->assertNotNull($kelas);
        $this->assertSame($this->prodi->id, $kelas->prodi_id);
        $this->assertSame(2, Enrollment::where('kelas_id', $kelas->id)->count());

        // Nama harus bersih dari nomor yang menempel.
        $nama = Enrollment::where('nim', '3312501013')->value('nama');
        $this->assertSame('Ririn Anisa Sihite', $nama);
    }

    public function test_pengajar_dan_pengelola_tidak_dimasukkan_ke_daftar_mahasiswa(): void
    {
        // Pengajar: membuat modul course. Tidak mungkin mahasiswa.
        $this->log('IF209', '225364', 'SyaprillaDonata', 'Course module created');
        // Pengelola: membatalkan pendaftaran orang lain.
        $this->log('IF209', '3312311020', 'Pengelola', 'User unenrolled from course');
        // Mahasiswa biasa.
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $kelas = Kelas::first();

        $this->assertSame(1, Enrollment::where('kelas_id', $kelas->id)->count());
        $this->assertNotNull(Enrollment::where('nim', '3312501013')->first());
        $this->assertNull(Enrollment::where('nim', '225364')->first(), 'Pengajar tidak boleh jadi mahasiswa');
        $this->assertNull(Enrollment::where('nim', '3312311020')->first(), 'Pengelola tidak boleh jadi mahasiswa');
    }

    public function test_bukti_penilai_masih_dianggap_mahasiswa(): void
    {
        // Bukti lemah: bukan bukti pengajar, jadi tetap ikut dihitung.
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Grading table viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $this->assertSame(1, Enrollment::count());
    }

    public function test_prodi_diambil_dari_akun_pengajar_yang_terdaftar(): void
    {
        $this->akunPengajar('197901132021211003', 'kaprodi-uji@uji.local');
        $this->log('IF209', '197901132021211003', 'Agus Fatulloh', 'Course module created', '2026-05-10 08:00:00', LogAktivitas::PERAN_KAPRODI);
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log')
            ->expectsOutputToContain('Prodi diambil dari 1 akun pengajar')
            ->assertSuccessful();

        $this->assertSame($this->prodi->id, Kelas::first()->prodi_id);
    }

    public function test_prodi_ambigu_ditolak_tanpa_menebak(): void
    {
        $fakultasLain = Fakultas::create(['kode' => 'FEB', 'nama' => 'Ekonomi']);
        $prodiLain = Prodi::create([
            'kode' => 'AK', 'nama' => 'Akuntansi', 'fakultas_id' => $fakultasLain->id,
        ]);

        $this->akunPengajar('1111111111', 'dosen-a@uji.local');
        $this->akunPengajar('2222222222', 'dosen-b@uji.local', $prodiLain->id);

        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log')
            ->expectsOutputToContain('Prodi tujuan tidak bisa dipastikan')
            ->assertFailed();

        $this->assertSame(0, Kelas::count());
        $this->assertSame(0, Enrollment::count());
    }

    public function test_prodi_yang_tidak_ada_ditolak(): void
    {
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'ZZ'])
            ->expectsOutputToContain('Prodi ZZ tidak ada')
            ->assertFailed();
    }

    public function test_dry_run_tidak_menyimpan_apa_pun(): void
    {
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, MataKuliah::count());
        $this->assertSame(0, Kelas::count());
        $this->assertSame(0, Enrollment::count());
    }

    public function test_tahun_akademik_dan_semester_bisa_ditetapkan_admin(): void
    {
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', [
            '--prodi' => 'TI',
            '--tahun-akademik' => '2024/2025',
            '--semester' => '1',
        ])->assertSuccessful();

        $kelas = Kelas::first();
        $this->assertSame('2024/2025', $kelas->tahun_akademik);
        $this->assertSame(1, $kelas->semester);
    }

    public function test_tahun_akademik_diturunkan_dari_bulan_paling_ramai(): void
    {
        // Padat di Juli 2026 -> semester genap 2025/2026.
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed', '2026-07-31 10:00:00');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $kelas = Kelas::first();
        $this->assertSame('2025/2026', $kelas->tahun_akademik);
        $this->assertSame(2, $kelas->semester);
    }

    public function test_baris_sisipan_terakhir_tidak_mengalihkan_semester(): void
    {
        // padat di Juli 2026, tapi ada satu baris nyasar 14 Oktober 2026.
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed', '2026-07-15 10:00:00');
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed', '2026-07-16 10:00:00');
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed', '2026-07-17 10:00:00');
        $this->log('IF209', '3312501008', 'Yohana Abigail Napitu', 'Course viewed', '2026-10-14 23:50:00');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])
            ->expectsOutputToContain('bulan paling ramai 2026-07')
            ->assertSuccessful();

        $kelas = Kelas::first();
        $this->assertSame('2025/2026', $kelas->tahun_akademik);
        $this->assertSame(2, $kelas->semester);
    }

    public function test_tahun_akademik_ganjil_dari_bulan_agustus(): void
    {
        // Padat di Oktober 2026 -> semester ganjil 2026/2027.
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed', '2026-10-05 10:00:00');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $kelas = Kelas::first();
        $this->assertSame('2026/2027', $kelas->tahun_akademik);
        $this->assertSame(1, $kelas->semester);
    }

    public function test_nomor_yang_bukan_nim_dikecualikan_dari_daftar(): void
    {
        // Nomor 4 digit: "Ketua Program Studi Informatika 1506". Tidak
        // mungkin NIM, meski aktivitasnya sedikit dan tidak ada bukti
        // Course module created.
        $this->log('IF209', '1506', 'Ketua Program Studi Informatika', 'Course module viewed');
        $this->log('IF209', '225364', 'SyaprillaDonataAMdKom', 'Course module viewed');
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $kelas = Kelas::first();

        $this->assertSame(1, Enrollment::where('kelas_id', $kelas->id)->count());
        $this->assertNull(Enrollment::where('nim', '1506')->first());
        $this->assertNull(Enrollment::where('nim', '225364')->first());
        $this->assertNotNull(Enrollment::where('nim', '3312501013')->first());
    }

    public function test_nim_prodi_lain_dikecualikan_kecuali_awalan_ditambahkan(): void
    {
        // Keputusan pengguna (18 Okt 2026): mahasiswa prodi ini berawalan
        // 3312. "Saparudin 4342501079" punya 10 digit, tapi bukan NIM prodi ini.
        $this->log('IF209', '4342501079', 'Saparudin', 'Course viewed');
        $this->log('IF209', '4312201010', 'Prayoga', 'Course viewed');
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $this->assertSame(1, Enrollment::count());
        $this->assertNotNull(Enrollment::where('nim', '3312501013')->first());
        $this->assertNull(Enrollment::where('nim', '4342501079')->first());
        $this->assertNull(Enrollment::where('nim', '4312201010')->first());

        // Admin boleh menambah awalan untuk kelas yang juga diikuti
        // mahasiswa prodi lain. "Saparudin 4342501079" tetap dikeluarkan
        // karena awalan 4342 bukan salah satu yang diakui.
        Enrollment::query()->delete();

        $this->artisan('kelas:susun-dari-log', [
            '--prodi' => 'TI',
            '--awalan-nim' => '3312,4312',
        ])->assertSuccessful();

        $this->assertSame(2, Enrollment::count());
        $this->assertNotNull(Enrollment::where('nim', '4312201010')->first());
        $this->assertNull(Enrollment::where('nim', '4342501079')->first());
    }

    public function test_laporan_menyebut_kalau_ada_yang_dikecualikan(): void
    {
        $this->log('IF209', '1506', 'Ketua Program Studi Informatika', 'Course module viewed');
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])
            ->expectsOutputToContain('bukan_nim')
            ->assertSuccessful();
    }

    public function test_nomor_nim_lain_tetap_diterima(): void
    {
        // 18 digit adalah NIP, dan sudah tercakup bukti perilaku; NIM
        // 10 digit tetap aman.
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');
        $this->log('IF209', '1506', 'Ketua Program Studi Informatika', 'Course module viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $this->assertSame(1, Enrollment::count());
        $this->assertNotNull(Enrollment::where('nim', '3312501013')->first());
    }

    public function test_satu_mahasiswa_boleh_ada_di_dua_kelas(): void
    {
        $this->log('IF209', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');
        $this->log('IF207', '3312501013', 'Ririn Anisa Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        // Dua kelas, dan mahasiswa yang sama terdaftar di keduanya.
        $this->assertSame(2, Kelas::count());
        $this->assertSame(2, Enrollment::count());
        $this->assertSame(2, Enrollment::where('nim', '3312501013')->count());
    }

    public function test_nama_berlebihan_spasi_dan_nomor_lengket_dirapikan(): void
    {
        $this->log('IF209', '225364', 'SyaprillaDonataAMdKom', 'Course module created');
        $this->log('IF209', '3312501013', 'Ririn  Anisa   Sihite', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $kelas = Kelas::first();
        $nama = Enrollment::where('kelas_id', $kelas->id)->value('nama');

        $this->assertSame('Ririn Anisa Sihite', $nama);
    }

    public function test_nama_tanpa_identitas_tetap_dipakai(): void
    {
        $this->log('IF209', '3312501013', 'Ririn', 'Course viewed');

        $this->artisan('kelas:susun-dari-log', ['--prodi' => 'TI'])->assertSuccessful();

        $kelas = Kelas::first();
        $this->assertSame('Ririn', Enrollment::where('kelas_id', $kelas->id)->value('nama'));
    }
}
