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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji penugasan akademik (FR-03).
 *
 * Fokusnya: admin bisa mencatat dosen dan mahasiswa, dan yang tercatat
 * hanya identitas, BUKAN akun.
 */
class PenugasanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dosen;

    private Prodi $prodi;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);
        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);

        $mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer',
            'sks' => 3, 'semester' => 5, 'prodi_id' => $this->prodi->id,
        ]);

        $this->kelas = Kelas::create([
            'kode' => '2A', 'nama' => '2A Pagi', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2026/2027', 'semester' => 1,
        ]);

        $this->admin = User::create([
            'nama' => 'Admin', 'email' => 'admin@test.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);

        $this->dosen = User::create([
            'nama' => 'Yeni Rokhayati', 'email' => 'dosen@test.local',
            'password' => 'rahasia123', 'role' => 'dosen',
            'nomor_induk' => '198602192014042001', 'prodi_id' => $this->prodi->id,
        ]);
    }

    // ------------------------------------------------------------- halaman

    public function test_halaman_penugasan_bisa_diakses_admin(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/penugasan')
            ->assertOk()
            // Penugasan kini jadi salah satu tab di halaman dashboard Admin,
            // bukan halaman tersendiri: URL-nya tetap sama, cangkangnya berbagi.
            ->assertViewIs('Admin.AdminDashboard')
            ->assertSee('window.tabAwal = "penugasan"', escape: false)
            ->assertSee($this->kelas->label());
    }

    public function test_dosen_tidak_bisa_membuka_halaman_penugasan(): void
    {
        $this->actingAs($this->dosen)
            ->get('/admin/penugasan')
            ->assertForbidden();
    }

    public function test_pencarian_menyaring_kelas(): void
    {
        $mkLain = MataKuliah::create([
            'kode' => 'IF207', 'nama' => 'Proyek Pembuatan Prototipe',
            'sks' => 4, 'semester' => 5, 'prodi_id' => $this->prodi->id,
        ]);
        Kelas::create([
            'kode' => '1A', 'nama' => '1A Pagi', 'mata_kuliah_id' => $mkLain->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2026/2027', 'semester' => 1,
        ]);

        $difilter = $this->actingAs($this->admin)->get('/admin/penugasan?q=Jaringan');
        $difilter->assertSee('Jaringan Komputer (IF209) - 2A Pagi');
        $difilter->assertDontSee('Proyek Pembuatan Prototipe (IF207) - 1A Pagi');
    }

    // ------------------------------------------------------------- dosen

    public function test_admin_boleh_mencatat_dosen_pengampu(): void
    {
        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/dosen",
            ['nomor_induk' => '198602192014042001', 'peran' => 'pengampu']
        )->assertSessionHas('success');

        $this->assertDatabaseHas('kelas_dosen', [
            'kelas_id' => $this->kelas->id,
            'user_id' => $this->dosen->id,
            'peran' => 'pengampu',
        ]);
    }

    public function test_dosen_dengan_nip_6_digit_diterima(): void
    {
        // Pada file log nyata, dosen pengampu punya identifier 6 digit.
        $dosen6 = User::create([
            'nama' => 'Syaprilla Donata', 'email' => 'd6@test.local',
            'password' => 'rahasia123', 'role' => 'dosen',
            'nomor_induk' => '225364', 'prodi_id' => $this->prodi->id,
        ]);

        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/dosen",
            ['nomor_induk' => '225364', 'peran' => 'pengampu']
        );

        $this->assertDatabaseHas('kelas_dosen', [
            'kelas_id' => $this->kelas->id, 'user_id' => $dosen6->id,
        ]);
    }

    public function test_nip_tidak_terdaftar_ditolak_dengan_pesan_jelas(): void
    {
        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/dosen",
            ['nomor_induk' => '999999999999', 'peran' => 'pengampu']
        )->assertSessionHas('error');

        $this->assertSame(0, DB::table('kelas_dosen')->count());
    }

    public function test_dosen_bisa_dicabut(): void
    {
        $this->kelas->dosen()->attach($this->dosen, ['peran' => 'pengampu']);

        $this->actingAs($this->admin)
            ->delete("/admin/penugasan/kelas/{$this->kelas->id}/dosen/{$this->dosen->id}");

        $this->assertDatabaseMissing('kelas_dosen', [
            'kelas_id' => $this->kelas->id, 'user_id' => $this->dosen->id,
        ]);
    }

    // ------------------------------------------------------------- mahasiswa

    public function test_nim_massa_didaftarkan_tanpa_membuat_akun(): void
    {
        $jumlahUserAwal = User::count();

        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa",
            ['nim' => "3312501013\n3312501014\n3312501015"]
        )->assertSessionHas('success');

        $this->assertSame(3, DB::table('kelas_mahasiswa')->count());

        // INI inti dari desain: tidak ada akun yang dibuat.
        $this->assertSame(
            $jumlahUserAwal,
            User::count(),
            'Mendaftarkan mahasiswa tidak boleh membuat akun apa pun.'
        );
    }

    public function test_tidak_ada_kolom_user_id_di_tabel_mahasiswa(): void
    {
        Enrollment::create([
            'kelas_id' => $this->kelas->id, 'nim' => '3312501013', 'nama' => 'Budi',
        ]);

        $kolom = array_keys((array) DB::table('kelas_mahasiswa')->first());

        $this->assertContains('nim', $kolom);
        $this->assertNotContains('user_id', $kolom, 'Mahasiswa tidak punya akun.');
    }

    public function test_nim_dengan_nama_direkam(): void
    {
        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa",
            ['nim' => "3312501013 - Budi Santoso\n3312501014 Siti Aminah"]
        );

        $this->assertDatabaseHas('kelas_mahasiswa', [
            'kelas_id' => $this->kelas->id, 'nim' => '3312501013', 'nama' => 'Budi Santoso',
        ]);
        $this->assertDatabaseHas('kelas_mahasiswa', [
            'kelas_id' => $this->kelas->id, 'nim' => '3312501014', 'nama' => 'Siti Aminah',
        ]);
    }

    public function test_nim_tanpa_nama_tetap_dicatat(): void
    {
        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa",
            ['nim' => '3312501013']
        );

        $this->assertDatabaseHas('kelas_mahasiswa', [
            'nim' => '3312501013', 'nama' => null,
        ]);
    }

    public function test_nim_kembar_diproses_satu_kali(): void
    {
        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa",
            ['nim' => "3312501013\n3312501013\n3312501013"]
        );

        $this->assertSame(1, DB::table('kelas_mahasiswa')->count());
    }

    public function test_nim_yang_sudah_terdaftar_dilewati(): void
    {
        Enrollment::create([
            'kelas_id' => $this->kelas->id, 'nim' => '3312501013', 'nama' => 'Budi',
        ]);

        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa",
            ['nim' => "3312501013\n3312501014"]
        )->assertSessionHas('success');

        $this->assertSame(2, DB::table('kelas_mahasiswa')->count());
    }

    public function test_mahasiswa_bisa_dikeluarkan(): void
    {
        $enrollment = Enrollment::create([
            'kelas_id' => $this->kelas->id, 'nim' => '3312501099', 'nama' => 'Mhs',
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa/{$enrollment->id}");

        $this->assertDatabaseMissing('kelas_mahasiswa', ['id' => $enrollment->id]);
    }

    public function test_nim_wajib_diisi(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa", ['nim' => ''])
            ->assertSessionHasErrors('nim');
    }

    public function test_mahasiswa_yg_juga_di_kelas_lain_ditandai(): void
    {
        $mkLain = MataKuliah::create([
            'kode' => 'IF207', 'nama' => 'Proyek Pembuatan Prototipe',
            'sks' => 4, 'semester' => 5, 'prodi_id' => $this->prodi->id,
        ]);
        $kelasLain = Kelas::create([
            'kode' => '2B', 'nama' => '2B Sore', 'mata_kuliah_id' => $mkLain->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2026/2027', 'semester' => 1,
        ]);

        Enrollment::create(['kelas_id' => $this->kelas->id, 'nim' => '3312501013', 'nama' => 'Budi']);
        Enrollment::create(['kelas_id' => $kelasLain->id, 'nim' => '3312501013', 'nama' => 'Budi']);

        $this->actingAs($this->admin)
            ->get('/admin/penugasan?q=Jaringan')
            ->assertOk()
            ->assertSee('juga di kelas 2B');
    }

    // ------------------------------------------------------------- dampak ke import

    public function test_import_menandai_mahasiswa_tanpa_perlu_akun(): void
    {
        // Menutup lingkaran: identitas cukup dari NIM, tanpa akun.
        $this->actingAs($this->admin)->post(
            "/admin/penugasan/kelas/{$this->kelas->id}/mahasiswa",
            ['nim' => '3312501013']
        );

        $this->importLog(['3312501013' => 'Ririn Anisa Sihite']);

        $baris = LogAktivitas::where('actor_identifier', '3312501013')->first();
        $this->assertSame('mahasiswa', $baris->actor_role);
        $this->assertNull($baris->actor_user_id, 'Mahasiswa tidak punya user_id.');
    }

    public function test_dosen_baru_didaftarkan_ikut_terdeteksi_di_log(): void
    {
        // Belum ada akun dengan NIP itu, jadi import pertama
        // menganggapnya mahasiswa.
        $this->importLog(['225364' => 'Syaprilla Donata'], '14/10/2026 14:50');

        $sebelum = LogAktivitas::where('actor_identifier', '225364')->firstOrFail();
        $this->assertSame('mahasiswa', $sebelum->actor_role);

        // Lalu didaftarkan sebagai dosen.
        $dosen6 = User::create([
            'nama' => 'Syaprilla Donata', 'email' => 'd6@test.local',
            'password' => 'rahasia123', 'role' => 'dosen',
            'nomor_induk' => '225364', 'prodi_id' => $this->prodi->id,
        ]);

        // Import kedua dengan waktu berbeda supaya tidak dianggap duplikat.
        $this->importLog(['225364' => 'Syaprilla Donata'], '15/10/2026 09:00');

        $sesudah = LogAktivitas::where('actor_identifier', '225364')
            ->where('occurred_at', '>', $sebelum->occurred_at)
            ->firstOrFail();

        $this->assertSame('dosen', $sesudah->actor_role);
        $this->assertSame($dosen6->id, $sesudah->actor_user_id);
    }

    public function test_identifier_kosong_ditandai_tak_diketahui(): void
    {
        $this->importLog(['' => 'Tidak Diketahui']);

        $this->assertSame('tak_diketahui', LogAktivitas::first()->actor_role);
    }

    public function test_jumlah_terknown_menghitung_mahasiswa_terdaftar(): void
    {
        Enrollment::create([
            'kelas_id' => $this->kelas->id, 'nim' => '3312501013', 'nama' => 'Budi',
        ]);

        $this->importLog(['3312501013' => 'Budi', '3312509999' => 'Tanpa Roster']);

        $batch = ImportBatch::first();

        // 3312501013 dikenal karena NIM-nya tercatat di kelas.
        // 3312509999 berperan mahasiswa, tapi tidak ada di kelas mana pun.
        $this->assertSame(1, $batch->jumlah_terkenal);
        $this->assertSame(1, $batch->jumlah_tak_dikenal);
    }

    /**
     * Import satu file log berisi satu baris per identifier.
     *
     * @param  array<string, string>  $peta  identifier => nama
     */
    private function importLog(array $peta, string $waktu = '14/10/2026 14:50'): void
    {
        $isi = "\xEF\xBB\xBFTime;User full name;Affected user;Event context;Component;Event name;Description;Origin;IP address\r\n";

        foreach ($peta as $identifier => $nama) {
            // Kunci array PHP yang isinya angka jadi integer, jadi
            // harus dikembalikan ke string sebelum ditulis ke CSV.
            $identifier = (string) $identifier;

            $isi .= sprintf(
                "%s;%s %s;-;Course: Jaringan Komputer/IF209;System;Course viewed;desc;web;1.2.3.4\r\n",
                $waktu,
                $nama,
                $identifier === '' ? '-' : $identifier
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'log').'.csv';
        file_put_contents($path, $isi);

        $this->actingAs($this->admin)->post('/admin/log-import', [
            'file_csv' => new UploadedFile($path, 'log.csv', 'text/csv', null, true),
        ]);
    }
}
