<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kelola Akun (FR-02).
 *
 * Yang dijaga: hanya Admin yang boleh membuat akun; akun yang dibuat
 * tidak boleh punya role admin lewat form ini; nomor induk wajib karena
 * itulah penghubung ke log LMS; password acak hanya tampil sekali.
 */
class AkunTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);
        $prodi = Prodi::create([
            'kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id,
        ]);

        $this->admin = User::create([
            'nama' => 'Admin', 'email' => 'admin@uji.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);

        $mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'prodi_id' => $prodi->id,
        ]);

        $this->kelas = Kelas::create([
            'kode' => 'A', 'nama' => 'Kelas A', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $prodi->id, 'tahun_akademik' => '2025/2026', 'semester' => 2,
        ]);
    }

    private Kelas $kelas;

    /** @return array<string, mixed> */
    private function isiAkun(array $ubah = []): array
    {
        return array_merge([
            'nama' => 'SyaprillaDonata',
            'email' => 'syaprilla@edutrack.local',
            'nomor_induk' => '225364',
            'role' => 'dosen',
        ], $ubah);
    }

    // ------------------------------------------------------------- akses

    public function test_admin_bisa_membuka_halaman_kelola_akun(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/akun')
            ->assertOk()
            ->assertSee('Kelola Akun')
            ->assertSee('Buat Akun Baru');
    }

    public function test_halaman_kelola_akun_menampilkan_daftar_akun(): void
    {
        User::create([
            'nama' => 'Yeni Rokhayati', 'nomor_induk' => '198602192014042001',
            'email' => 'dosen@gmail.com', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/akun')
            ->assertOk()
            ->assertSee('Yeni Rokhayati')
            ->assertSee('198602192014042001')
            ->assertSee('dosen@gmail.com');
    }

    public function test_dosen_dan_kaprodi_tidak_bisa_membuka_halaman_kelola_akun(): void
    {
        foreach (['dosen', 'kaprodi'] as $role) {
            $akun = User::create([
                'nama' => ucfirst($role), 'email' => "{$role}@uji.local",
                'password' => 'rahasia123', 'role' => $role,
            ]);

            $this->actingAs($akun)->get('/admin/akun')->assertForbidden();
            $this->actingAs($akun)
                ->post('/admin/akun', $this->isiAkun(["email_{$role}" => 'x']))
                ->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin/akun')->assertRedirect('/login');
    }

    public function test_pendaftaran_mandiri_sudah_dihapus(): void
    {
        // Dulu /register terbuka untuk publik dan bisa membuat akun admin.
        // Akun sekarang hanya dibuat Admin lewat halaman ini.
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'nama' => 'Penyusup', 'email' => 'penyusup@edutrack.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@edutrack.local']);
    }

    // -------------------------------------------------------------- buat

    public function test_admin_membuat_akun_dosen(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun())
            ->assertRedirect(route('admin.akun'))
            ->assertSessionHas('success');

        $akun = User::where('nomor_induk', '225364')->first();

        $this->assertNotNull($akun);
        $this->assertSame('dosen', $akun->role);
        $this->assertSame('SyaprillaDonata', $akun->nama);
        $this->assertTrue($akun->is_active);

        // Tidak ada prodi: akses dosen murni dari penugasan pengampu.
        $this->assertNull($akun->prodi_id);
    }

    public function test_admin_membuat_akun_kaprodi_tanpa_prodi(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun([
                'role' => 'kaprodi',
                'email' => 'kaprodi@edutrack.local',
                'nomor_induk' => '197901132021211003',
            ]))
            ->assertRedirect(route('admin.akun'));

        $kaprodi = User::where('role', 'kaprodi')->first();

        $this->assertNotNull($kaprodi);
        $this->assertNull($kaprodi->prodi_id, 'Kaprodi tidak dibatasi prodi');
    }

    public function test_role_admin_tidak_bisa_dipilih_lewat_form(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun(['role' => 'admin']))
            ->assertSessionHasErrors('role');

        $this->assertSame(1, User::where('role', 'admin')->count(), 'Tidak boleh ada admin baru');
    }

    public function test_nomor_induk_wajib(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun(['nomor_induk' => '']))
            ->assertSessionHasErrors('nomor_induk');
    }

    public function test_email_dan_nomor_induk_harus_unik(): void
    {
        User::create([
            'nama' => 'Lama', 'nomor_induk' => '225364',
            'email' => 'syaprilla@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun())
            ->assertSessionHasErrors(['email', 'nomor_induk']);

        $this->assertSame(1, User::where('role', 'dosen')->count());
    }

    public function test_password_kosong_memakai_password_bawaan_yang_mudah_diingat(): void
    {
        // Keputusan pengguna (18 Okt 2026): password awal semua akun
        // Dosen/Kaprodi sama dan mudah diingat, lalu pemilik akun sendiri
        // yang menggantinya lewat tab Settings.
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun())
            ->assertSessionHas('password_baru');

        $sesi = session('password_baru');
        $akun = User::where('email', $sesi['email'])->firstOrFail();

        $this->assertSame('syaprilla@edutrack.local', $sesi['email']);
        $this->assertSame(User::PASSWORD_BAWAAN, $sesi['password']);
        $this->assertTrue(Hash::isHashed($akun->password), 'password harus disimpan sebagai hash');
        $this->assertTrue(Hash::check(User::PASSWORD_BAWAAN, $akun->password));
    }

    public function test_akun_baru_bisa_langsung_login_dengan_password_bawaan(): void
    {
        $this->actingAs($this->admin)->post('/admin/akun', $this->isiAkun());

        $akun = User::where('email', 'syaprilla@edutrack.local')->firstOrFail();

        $this->post('/logout');

        $this->post('/login', [
            'email' => $akun->email,
            'password' => User::PASSWORD_BAWAAN,
        ])->assertRedirect(route('dosen.dashboard'));

        $this->assertAuthenticatedAs($akun);
    }

    public function test_password_yang_diberikan_admin_dipakai_apa_adanya(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun(['password' => 'RahasiaKuat123']))
            ->assertSessionMissing('password_baru');

        $akun = User::where('email', 'syaprilla@edutrack.local')->firstOrFail();

        $this->assertTrue(Hash::check('RahasiaKuat123', $akun->password));
    }

    public function test_password_pendek_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun(['password' => 'pendek']))
            ->assertSessionHasErrors('password');
    }

    // -------------------------------------------------------------- ubah

    public function test_admin_bisa_mengubah_data_akun(): void
    {
        $dosen = User::create([
            'nama' => 'Nama Lama', 'nomor_induk' => '225364',
            'email' => 'lama@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/akun/{$dosen->id}", [
                'nama' => 'Nama Baru',
                'email' => 'baru@edutrack.local',
                'nomor_induk' => '317258',
                'is_active' => '0',
            ])
            ->assertSessionHas('success');

        $dosen->refresh();

        $this->assertSame('Nama Baru', $dosen->nama);
        $this->assertSame('baru@edutrack.local', $dosen->email);
        $this->assertSame('317258', $dosen->nomor_induk);
        $this->assertFalse($dosen->is_active);
    }

    public function test_ubah_akun_menolak_email_yang_dipakai_akun_lain(): void
    {
        User::create([
            'nama' => 'Lain', 'nomor_induk' => '317258',
            'email' => 'lain@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $dosen = User::create([
            'nama' => 'Dosen', 'nomor_induk' => '225364',
            'email' => 'dosen@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/akun/{$dosen->id}", [
                'nama' => 'Dosen', 'email' => 'lain@edutrack.local', 'nomor_induk' => '225364',
            ])
            ->assertSessionHasErrors('email');
    }

    // -------------------------------------------------------- nonaktif & reset

    public function test_akun_bisa_dinonaktifkan_dan_diaktifkan_kembali(): void
    {
        $dosen = User::create([
            'nama' => 'Dosen', 'nomor_induk' => '225364',
            'email' => 'dosen@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->actingAs($this->admin)->post("/admin/akun/{$dosen->id}/aktif")->assertRedirect();
        $this->assertFalse($dosen->refresh()->is_active);

        $this->actingAs($this->admin)->post("/admin/akun/{$dosen->id}/aktif")->assertRedirect();
        $this->assertTrue($dosen->refresh()->is_active);
    }

    public function test_akun_yang_dimatikan_tidak_bisa_login(): void
    {
        $dosen = User::create([
            'nama' => 'Dosen', 'nomor_induk' => '225364',
            'email' => 'dosen@edutrack.local', 'password' => 'rahasia123',
            'role' => 'dosen', 'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $dosen->email,
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_reset_password_mengembalikan_ke_password_bawaan(): void
    {
        $dosen = User::create([
            'nama' => 'Dosen', 'nomor_induk' => '225364',
            'email' => 'dosen@edutrack.local', 'password' => 'rahasia123', 'role' => 'dosen',
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/akun/{$dosen->id}/password")
            ->assertSessionHas('password_baru');

        $sesi = session('password_baru');

        $this->assertSame('dosen@edutrack.local', $sesi['email']);
        $this->assertSame(User::PASSWORD_BAWAAN, $sesi['password']);
        $this->assertFalse(Hash::check('rahasia123', $dosen->refresh()->password));
        $this->assertTrue(Hash::check(User::PASSWORD_BAWAAN, $dosen->password));
    }

    // ------------------------------------------------------------- navigasi

    public function test_sidebar_admin_memuat_menu_kelola_akun(): void
    {
        // Semua menu Admin adalah tombol tab di dalam satu halaman, jadi
        // yang diperiksa adalah target tab, bukan URL.
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('data-target="akun"', escape: false)
            ->assertSee('onclick="switchTab(\'akun\')"', escape: false)
            ->assertSee('Kelola Akun')
            ->assertSee('id="content-akun"', escape: false);
    }

    public function test_halaman_akun_menandai_tabnya_aktif(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/akun')
            ->assertOk()
            ->getContent();

        // Tab akun terbuka, tombolnya disorot, dan tab lain disembunyikan.
        $this->assertMatchesRegularExpression(
            '/data-target="akun"[^>]*class="[^"]*\bbg-indigo-600\b/',
            $html,
        );
        $this->assertStringContainsString('id="content-akun" class="tab-content space-y-6"', $html);
        $this->assertStringContainsString('id="content-dashboard" class="tab-content space-y-6 hidden"', $html);
        $this->assertStringContainsString('window.tabAwal = "akun"', $html);
    }

    public function test_semua_tab_admin_bisa_dipindah_tanpa_memuat_ulang(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        // Empat menu, empat panel, urutan sama.
        // Menu dibaca dari bagian sidebar saja; seluruh HTML mengandung
        // [data-target="${tabId}"] di dalam JavaScript switchTab.
        preg_match_all('/data-target="([^"]+)"/', $this->sidebarHtml($html), $menu);
        preg_match_all('/id="content-([^"]+)"/', $html, $tab);

        $this->assertSame(['dashboard', 'uploadlog', 'akun', 'penugasan'], $menu[1]);
        $this->assertSame($menu[1], $tab[1]);

        // Tidak ada lagi menu yang berupa tautan ke halaman lain.
        $this->assertStringNotContainsString('href="http://localhost:8000/admin/penugasan"', $html);
    }

    public function test_setiap_tab_punya_isi_lagi_walau_dibuka_dari_tab_lain(): void
    {
        $dosen = User::create([
            'nama' => 'Yeni Rokhayati',
            'email' => 'yeni@uji.local',
            'password' => 'rahasia123',
            'role' => 'dosen',
            'nomor_induk' => '198602192014042001',
        ]);

        // Buka lewat tab Dashboard: tab lain tetap harus terisi, kalau tidak
        // sidebar yang bisa berpindah tidak ada gunanya.
        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($dosen->nama, $html, 'tab Kelola Akun kosong');
        $this->assertStringContainsString($this->admin->nama, $html);
        $this->assertStringContainsString('Mata Kuliah Terpantau', $html);
        $this->assertStringContainsString('Kelola Akun', $html);
        $this->assertStringContainsString('Penugasan Akademik', $html);
        $this->assertStringContainsString('Upload Log Aktivitas', $html);
        $this->assertStringContainsString('Ringkasan Dashboard', $html);
    }

    public function test_pesan_berhasil_hanya_muncul_di_tab_yang_aktif(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/akun', $this->isiAkun())
            ->assertRedirect(route('admin.akun'));

        $html = $this->get('/admin/akun')->assertOk()->getContent();

        // Semua tab dirender sekaligus. Kalau notifikasi tidak dibatasi,
        // pesan yang sama ikut muncul di tab Penugasan.
        $this->assertSame(1, substr_count($html, 'dibuat.'));
        $this->assertSame(1, substr_count($html, 'role="alert"'));
    }

    public function test_tab_awal_disetuskan_sebelum_tab_switcher_dijalankan(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/akun')
            ->assertOk()
            ->getContent();

        // switchTab langsung dipanggil saat skrip dibaca, jadi variabel
        // penentu tab awal harus muncul lebih dulu.
        $this->assertLessThan(
            strpos($html, 'function switchTab'),
            strpos($html, 'window.tabAwal = "akun"'),
            'window.tabAwal harus ditulis sebelum tab-switcher dipanggil.',
        );
    }

    /** Ambil bagian HTML sidebar, yaitu sebelum <main>. */
    private function sidebarHtml(string $html): string
    {
        return substr($html, 0, strpos($html, '<main'));
    }
}
