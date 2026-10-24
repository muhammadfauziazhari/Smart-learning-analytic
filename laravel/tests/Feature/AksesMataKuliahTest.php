<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Izin akses Dosen per mata kuliah (FR-03/FR-05).
 *
 * Skenario yang dijaga: Admin mengimpor log mata kuliah baru, lalu cukup
 * mencentang mata kuliah itu di baris dosen yang concernée. Semua kelas
 * mata kuliah tersebut — termasuk yang dibuat setelahnya — harus ikut
 * terlihat, tanpa perlu ditandai satu per satu.
 */
class AksesMataKuliahTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dosen;

    private Prodi $prodi;

    private MataKuliah $mkWeb;

    private MataKuliah $mkJaringan;

    private Kelas $kelasWeb;

    private Kelas $kelasJaringan;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);
        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);

        $this->admin = User::create([
            'nama' => 'Admin', 'email' => 'admin@uji.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);

        $this->dosen = User::create([
            'nama' => 'Sukma', 'nomor_induk' => '125360',
            'email' => 'sukma@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->mkWeb = MataKuliah::create([
            'kode' => 'IF221', 'nama' => 'Pemrograman Web', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);
        $this->mkJaringan = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'prodi_id' => $this->prodi->id,
        ]);

        $this->kelasWeb = $this->buatKelas($this->mkWeb, 'A');
        $this->kelasJaringan = $this->buatKelas($this->mkJaringan, 'A');
    }

    private function buatKelas(MataKuliah $mk, string $kode): Kelas
    {
        return Kelas::create([
            'kode' => $kode, 'nama' => "Kelas {$kode}", 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 1,
        ]);
    }

    /** @param  array<int, int>  $ids  */
    private function berikanAkses(User $dosen, array $ids): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/akun/{$dosen->id}/akses-mata-kuliah", ['mata_kuliah' => $ids])
            ->assertRedirect();
    }

    public function test_admin_bisa_berikan_izin_mata_kuliah_ke_dosen(): void
    {
        $this->berikanAkses($this->dosen, [$this->mkWeb->id]);

        // `id` di sini adalah mata_kuliahs.id; kolom pivot selalu berawalan
        // "pivot_" jadi tidak bentrok.
        $ids = $this->dosen->mataKuliahAkses->pluck('id')->all();

        $this->assertSame([$this->mkWeb->id], $ids);
    }

    public function test_dosen_melihat_semua_kelas_dari_mata_kuliah_yang_diberi_izin(): void
    {
        // Satu mata kuliah bisa punya beberapa kelas.
        $kelasWebB = $this->buatKelas($this->mkWeb, 'B');

        $this->berikanAkses($this->dosen, [$this->mkWeb->id]);

        $terlihat = Kelas::untukPengguna($this->dosen)->pluck('id');

        $this->assertTrue($terlihat->contains($this->kelasWeb->id));
        $this->assertTrue($terlihat->contains($kelasWebB->id), 'Kelas B ikut otomatis');
        $this->assertFalse($terlihat->contains($this->kelasJaringan->id));
    }

    public function test_kelas_yang_baru_dibuat_otomatis_ikut_terlihat(): void
    {
        $this->berikanAkses($this->dosen, [$this->mkWeb->id]);

        // Log mata kuliah baru masuk, Admin membuat kelas tambahan weeks kemudian.
        $kelasBaru = $this->buatKelas($this->mkWeb, 'C');

        $terlihat = Kelas::untukPengguna($this->dosen)->pluck('id');

        $this->assertTrue($terlihat->contains($kelasBaru->id), 'Kelas baru tidak perlu ditandai ulang');
    }

    public function test_mencabut_izin_langsung_menutup_akses(): void
    {
        $this->berikanAkses($this->dosen, [$this->mkWeb->id]);
        $this->assertTrue($this->dosen->can('view', $this->kelasWeb));

        // Tidak ada yang dicentang.
        $this->berikanAkses($this->dosen, []);

        $terlihat = Kelas::untukPengguna($this->dosen)->pluck('id');

        $this->assertFalse($terlihat->contains($this->kelasWeb->id));
        $this->assertFalse($this->dosen->can('view', $this->kelasWeb));
    }

    public function test_izin_mengganti_izin_sebelumnya(): void
    {
        $this->berikanAkses($this->dosen, [$this->mkWeb->id]);
        $this->berikanAkses($this->dosen, [$this->mkJaringan->id]);

        $terlihat = Kelas::untukPengguna($this->dosen)->pluck('id');

        $this->assertFalse($terlihat->contains($this->kelasWeb->id));
        $this->assertTrue($terlihat->contains($this->kelasJaringan->id));
        $this->assertSame(1, $this->dosen->mataKuliahAkses()->count());
    }

    public function test_izin_tidak_berlaku_untuk_kaprodi(): void
    {
        $kaprodi = User::create([
            'nama' => 'Agus', 'nomor_induk' => '197901132021211003',
            'email' => 'kaprodi@uji.local', 'password' => 'rahasia123', 'role' => 'kaprodi',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/akun/{$kaprodi->id}/akses-mata-kuliah", ['mata_kuliah' => [$this->mkWeb->id]])
            ->assertSessionHas('error');

        // Kaprodi tetap melihat semuanya tanpa perlu izin.
        $this->assertSame(2, Kelas::untukPengguna($kaprodi)->count());
    }

    public function test_dosen_lain_tidak_bisa_mengubah_izin(): void
    {
        $dosenLain = User::create([
            'nama' => 'Lain', 'nomor_induk' => '125361',
            'email' => 'lain@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->actingAs($dosenLain)
            ->put("/admin/akun/{$this->dosen->id}/akses-mata-kuliah", ['mata_kuliah' => [$this->mkWeb->id]])
            ->assertForbidden();
    }

    public function test_mata_kuliah_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/akun/{$this->dosen->id}/akses-mata-kuliah", ['mata_kuliah' => [9999]])
            ->assertSessionHasErrors('mata_kuliah.0');
    }

    public function test_izin_duplicate_tidak_menggandakan_baris(): void
    {
        $this->berikanAkses($this->dosen, [$this->mkWeb->id, $this->mkWeb->id]);

        $this->assertSame(1, $this->dosen->mataKuliahAkses()->count());
    }

    public function test_penugasan_pengampu_masih_berlaku(): void
    {
        // Jalur lama (halaman Penugasan) tidak ikut hilang: dosen yang
        // ditunjuk pengampu di satu kelas tetap boleh melihat kelas itu.
        $this->kelasWeb->dosen()->attach($this->dosen->id, ['peran' => 'pengampu']);

        $terlihat = Kelas::untukPengguna($this->dosen)->pluck('id');

        $this->assertTrue($terlihat->contains($this->kelasWeb->id));
        $this->assertTrue($this->dosen->can('view', $this->kelasWeb));
        $this->assertFalse($terlihat->contains($this->kelasJaringan->id));
    }

    public function test_halaman_akun_menampilkan_pilihan_mata_kuliah(): void
    {
        $this->berikanAkses($this->dosen, [$this->mkWeb->id]);

        $this->actingAs($this->admin)
            ->get('/admin/akun')
            ->assertOk()
            ->assertSee('Mata Kuliah Terpantau')
            ->assertSee('Pemrograman Web')
            ->assertSee('Jaringan Komputer')
            ->assertSee('1 mata kuliah');
    }
}
