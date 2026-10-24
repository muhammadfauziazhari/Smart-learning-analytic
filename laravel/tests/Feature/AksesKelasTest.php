<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Uji batas akses berbasis peran dan cakupan (FR-05).
 *
 * Ini pengujian yang paling penting di proyek: tanpa itu, Dosen bisa
 * membuka kelas milik Dosen lain hanya dengan menebak ID di URL.
 */
class AksesKelasTest extends TestCase
{
    use RefreshDatabase;

    private Fakultas $fakultas;

    private Prodi $prodi;

    private Prodi $prodiLain;

    private MataKuliah $mk;

    private MataKuliah $mkLain;

    /** Kelas A: diajar Dosen Satu. */
    private Kelas $kelasA;

    /** Kelas B: diajar Dosen Dua. */
    private Kelas $kelasB;

    /** Kelas C: di prodi lain. */
    private Kelas $kelasC;

    private User $admin;

    private User $dosenSatu;

    private User $dosenDua;

    private User $kaprodi;

    private User $kaprodiLain;

    /** NIM mahasiswa di kelas A. Tidak punya akun, hanya identitas. */
    private string $nimA = '3312501013';

    private string $nimB = '3312501014';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);

        $this->prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika',
            'fakultas_id' => $this->fakultas->id,
        ]);

        $this->prodiLain = Prodi::create([
            'kode' => 'DK', 'nama' => 'Doktor Teknik Komputer',
            'fakultas_id' => $this->fakultas->id,
        ]);

        $this->mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer',
            'sks' => 3, 'semester' => 5, 'prodi_id' => $this->prodi->id,
        ]);
        $this->mkLain = MataKuliah::create([
            'kode' => 'DK11', 'nama' => 'Riset Terapan', 'sks' => 3,
            'semester' => 5, 'prodi_id' => $this->prodiLain->id,
        ]);

        $this->kelasA = $this->buatKelas($this->mk, '2A', $this->prodi);
        $this->kelasB = $this->buatKelas($this->mk, '2B', $this->prodi);
        $this->kelasC = $this->buatKelas($this->mkLain, '1A', $this->prodiLain);

        $this->admin = $this->buatUser('admin', null);

        $this->dosenSatu = $this->buatUser('dosen', $this->prodi, '225364');
        $this->dosenDua = $this->buatUser('dosen', $this->prodi, '317258');
        $this->kaprodi = $this->buatUser('kaprodi', $this->prodi, '1506');
        $this->kaprodiLain = $this->buatUser('kaprodi', $this->prodiLain, '38557');

        $this->kelasA->dosen()->attach($this->dosenSatu, ['peran' => 'pengampu']);
        $this->kelasB->dosen()->attach($this->dosenDua, ['peran' => 'pengampu']);

        // Mahasiswa tidak punya akun, jadi yang dicatat hanya NIM.
        Enrollment::create(['kelas_id' => $this->kelasA->id, 'nim' => $this->nimA, 'nama' => 'Mhs A']);
        Enrollment::create(['kelas_id' => $this->kelasB->id, 'nim' => $this->nimB, 'nama' => 'Mhs B']);
    }

    private function buatKelas(MataKuliah $mk, string $kode, Prodi $prodi): Kelas
    {
        return Kelas::create([
            'kode' => $kode,
            'nama' => $kode.' Pagi',
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $prodi->id,
            'tahun_akademik' => '2026/2027',
            'semester' => 1,
        ]);
    }

    private function buatUser(string $role, ?Prodi $prodi, ?string $nomorInduk = null): User
    {
        return User::create([
            'nama' => $role.'-'.$prodi?->kode.'-'.$nomorInduk,
            'email' => uniqid().'@test.local',
            'password' => 'rahasia123',
            'role' => $role,
            'nomor_induk' => $nomorInduk,
            'prodi_id' => $prodi?->id,
        ]);
    }

    // ------------------------------------------------------------- scope query

    public function test_admin_melihat_semua_kelas(): void
    {
        $terlihat = Kelas::untukPengguna($this->admin)->pluck('id');
        $this->assertCount(3, $terlihat);
    }

    public function test_dosen_hanya_melihat_kelas_yang_diampu(): void
    {
        $terlihat = Kelas::untukPengguna($this->dosenSatu)->pluck('id');

        $this->assertTrue($terlihat->contains($this->kelasA->id));
        $this->assertFalse($terlihat->contains($this->kelasB->id), 'Kelas dosen lain tidak boleh ikut');
        $this->assertFalse($terlihat->contains($this->kelasC->id));
        $this->assertCount(1, $terlihat);
    }

    public function test_kaprodi_melihat_semua_kelas_semua_prodi(): void
    {
        // Keputusan pengguna (18 Okt 2026): Kaprodi tidak dibatasi prodi.
        $terlihat = Kelas::untukPengguna($this->kaprodi)->pluck('id');

        $this->assertTrue($terlihat->contains($this->kelasA->id));
        $this->assertTrue($terlihat->contains($this->kelasB->id));
        $this->assertTrue($terlihat->contains($this->kelasC->id), 'Kelas prodi lain ikut terlihat');
        $this->assertCount(3, $terlihat);
    }

    public function test_kaprodi_tanpa_prodi_tetap_melihat_semua_kelas(): void
    {
        // Dulu prodi kosong membuat Kaprodi melihat nol kelas (WHERE 1 = 0).
        // Sekarang prodi tidak lagi membatasi apa pun.
        $tanpaProdi = $this->buatUser('kaprodi', null);

        $this->assertCount(3, Kelas::untukPengguna($tanpaProdi)->get());
    }

    public function test_database_menolak_role_yang_tidak_dikenal(): void
    {
        // Role dibatasi enum di level database, bukan cuma dicek di PHP.
        // Ini pertahanan berlapis: jalur kode lain yang menulis role
        // sembarangan tetap ditolak.
        $this->expectException(QueryException::class);

        $this->buatUser('peran-ngawur', $this->prodi);
    }

    public function test_tidak_ada_akun_mahasiswa(): void
    {
        // Mahasiswa tidak pernah masuk ke sistem ini, jadi tidak boleh
        // ada peran 'mahasiswa' di tabel users sama sekali.
        $this->expectException(QueryException::class);

        $this->buatUser('mahasiswa', $this->prodi, '3312501013');
    }

    // ------------------------------------------------------------- policy

    public function test_dosen_tidak_boleh_membuka_kelas_milik_dosen_lain(): void
    {
        $this->assertTrue($this->dosenSatu->can('view', $this->kelasA));
        $this->assertFalse($this->dosenSatu->can('view', $this->kelasB));
        $this->assertFalse($this->dosenSatu->can('view', $this->kelasC));
    }

    public function test_kaprodi_boleh_membuka_kelas_prodi_lain(): void
    {
        $this->assertTrue($this->kaprodi->can('view', $this->kelasA));
        $this->assertTrue($this->kaprodi->can('view', $this->kelasC));
    }

    public function test_admin_boleh_membuka_semua_kelas(): void
    {
        foreach ([$this->kelasA, $this->kelasB, $this->kelasC] as $kelas) {
            $this->assertTrue($this->admin->can('view', $kelas));
        }
    }

    public function test_dosen_hanya_melihat_mahasiswa_di_kelasnya(): void
    {
        // Mahasiswa di kelas yang ia ampu: boleh.
        // Yang diperiksa NIM-nya, karena mahasiswa tidak punya akun.
        $this->assertTrue(
            $this->dosenSatu->can('lihatMahasiswa', [$this->kelasA, $this->nimA])
        );

        // Mahasiswa di kelas orang lain: TIDAK boleh.
        $this->assertFalse(
            $this->dosenSatu->can('lihatMahasiswa', [$this->kelasB, $this->nimB])
        );

        // NIM yang tidak terdaftar di kelas manapaun juga tidak boleh.
        $this->assertFalse(
            $this->dosenSatu->can('lihatMahasiswa', [$this->kelasA, '3312509999'])
        );
    }

    public function test_kaprodi_boleh_melihat_mahasiswa_kelas_manapun(): void
    {
        $this->assertTrue(
            $this->kaprodi->can('lihatMahasiswa', [$this->kelasA, $this->nimA])
        );

        // Kelas prodi lain juga boleh: Kaprodi oversee semua mata kuliah.
        $this->assertTrue(
            $this->kaprodi->can('lihatMahasiswa', [$this->kelasC, '3312502000'])
        );
    }

    public function test_tidak_ada_halaman_untuk_mahasiswa(): void
    {
        // Karena mahasiswa tidak punya akun, satu-satunya peran yang ada
        // adalah orang yang benar-benar login ke sistem.
        $peran = User::query()->distinct()->pluck('role');

        $this->assertEqualsCanonicalizing(
            ['admin', 'dosen', 'kaprodi'],
            $peran->all()
        );
    }

    public function test_hanya_admin_yang_bisa_kelola(): void
    {
        $this->assertTrue($this->admin->can('kelola', $this->mk));
        $this->assertFalse($this->dosenSatu->can('kelola', $this->mk));
        $this->assertFalse($this->kaprodi->can('kelola', $this->mk));
    }

    // ------------------------------------------------------------- middleware

    /**
     * Middleware diuji lewat route sungguhan, bukan dengan memanggil
     * kelasnya secara langsung, supaya yang teruji benar-benar
     * jalur request yang dipakai pengguna.
     */
    private function daftarRouteUji(): void
    {
        Route::middleware(['web', 'auth', 'kelas.akses'])
            ->get('/uji-kelas/{kelas}', fn (Kelas $kelas) => 'ok')
            ->name('uji.kelas');
    }

    public function test_middleware_mengizinkan_kelas_yaman(): void
    {
        $this->daftarRouteUji();

        $this->actingAs($this->dosenSatu)
            ->get('/uji-kelas/'.$this->kelasA->id)
            ->assertOk();
    }

    public function test_middleware_menolak_kelas_di_luar_cakupan(): void
    {
        $this->daftarRouteUji();

        // Dosen Satu mencoba membuka kelas milik Dosen Dua.
        $this->actingAs($this->dosenSatu)
            ->get('/uji-kelas/'.$this->kelasB->id)
            ->assertForbidden();
    }

    public function test_middleware_mengizinkan_kelas_prodi_lain_untuk_kaprodi(): void
    {
        $this->daftarRouteUji();

        $this->actingAs($this->kaprodi)
            ->get('/uji-kelas/'.$this->kelasC->id)
            ->assertOk();

        $this->actingAs($this->kaprodi)
            ->get('/uji-kelas/'.$this->kelasA->id)
            ->assertOk();
    }

    public function test_admin_boleh_membuka_kelas_apa_pun_lewat_middleware(): void
    {
        $this->daftarRouteUji();

        foreach ([$this->kelasA, $this->kelasB, $this->kelasC] as $kelas) {
            $this->actingAs($this->admin)
                ->get('/uji-kelas/'.$kelas->id)
                ->assertOk();
        }
    }

    public function test_middleware_menolak_kelas_tidak_ada(): void
    {
        $this->daftarRouteUji();

        $this->actingAs($this->admin)
            ->get('/uji-kelas/999999')
            ->assertNotFound();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->daftarRouteUji();

        $this->get('/uji-kelas/'.$this->kelasA->id)->assertRedirect('/login');
    }

    // ------------------------------------------------------------- relasi

    public function test_relasi_dosen_dan_mahasiswa_terhubung(): void
    {
        $this->assertTrue($this->kelasA->dosen->contains($this->dosenSatu));
        $this->assertCount(1, $this->dosenSatu->kelasDiampu);

        // Mahasiswa adalah Enrollment, bukan User.
        $this->assertSame($this->nimA, $this->kelasA->mahasiswa->first()->nim);
        $this->assertTrue($this->kelasA->punyaMahasiswa($this->nimA));
        $this->assertFalse($this->kelasA->punyaMahasiswa($this->nimB));
    }

    public function test_kaprodi_tidak_punya_relasi_kelas(): void
    {
        // "Seluruh kelas" bukan sebuah relasi pivot, jadi relasi
        // kelasTerlihat() dihapus. Satu-satunya jalan yang benar adalah
        // scope, yang dipakai semua layar.
        $this->assertFalse(method_exists($this->kaprodi, 'kelasTerlihat'));

        $ids = Kelas::untukPengguna($this->kaprodi)->pluck('kelas.id');

        $this->assertTrue($ids->contains($this->kelasA->id));
        $this->assertTrue($ids->contains($this->kelasC->id));
    }

    public function test_scope_kaprodi_tidak_mengulang_kelas_yang_banyak_pengampu(): void
    {
        foreach (['Dosen 1', 'Dosen 2', 'Dosen 3', 'Dosen 4'] as $nama) {
            $dosen = User::create([
                'nama' => $nama,
                'email' => str($nama)->slug().'@uji.local',
                'password' => 'rahasia123',
                'role' => 'dosen',
            ]);

            $this->kelasA->dosen()->attach($dosen->id, ['peran' => 'pengampu']);
        }

        $ids = Kelas::untukPengguna($this->kaprodi)->pluck('kelas.id');

        $this->assertSame(
            1,
            $ids->filter(fn ($id) => (int) $id === $this->kelasA->id)->count(),
            'Kelas dengan banyak pengampu harus muncul satu kali saja.',
        );
    }

    public function test_admin_melihat_semua_kelas_lewat_scope(): void
    {
        $ids = Kelas::untukPengguna($this->admin)->pluck('kelas.id');

        $this->assertTrue($ids->contains($this->kelasA->id));
        $this->assertTrue($ids->contains($this->kelasB->id));
        $this->assertTrue($ids->contains($this->kelasC->id), 'Admin melihat kelas semua prodi');
    }

    public function test_label_kelas_siap_tampil(): void
    {
        $this->assertSame('Jaringan Komputer (IF209) - 2A Pagi', $this->kelasA->label());
    }
}
