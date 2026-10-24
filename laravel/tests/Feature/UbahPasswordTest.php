<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ganti password sendiri lewat tab Settings (route /akun/password).
 *
 * Ini yang membuat keputusan "semua akun Dosen/Kaprodi mulai dari
 * password123" bisa diterima: begitu masuk, setiap pemilik akun bisa
 * menggantinya sendiri tanpa perlu bantuan Admin.
 */
class UbahPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $role = 'dosen', string $password = 'password123'): User
    {
        return User::create([
            'nama' => 'Pemilik Akun',
            'nomor_induk' => '198602192014042001',
            'email' => $role.'@uji.local',
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_dosen_bisa_mengganti_passwordnya_sendiri(): void
    {
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->post('/akun/password', [
                'password_lama' => 'password123',
                'password_baru' => 'RahasiaPribadiKuat',
                'password_baru_confirmation' => 'RahasiaPribadiKuat',
            ])
            // Kembali ke tab Settings: pesannya ada di panel itu. Kalau
            // tidak, panelnya tersembunyi dan pengguna mengira gagal diam-diam.
            ->assertRedirect(route('dosen.dashboard', ['tab' => 'settings']))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('RahasiaPribadiKuat', $dosen->refresh()->password));
        // Sesi harus tetap hidup: orangnya tidak pantas dilempar keluar.
        $this->assertAuthenticatedAs($dosen);
    }

    public function test_kaprodi_juga_bisa(): void
    {
        $kaprodi = $this->buat('kaprodi');

        $this->actingAs($kaprodi)
            ->post('/akun/password', [
                'password_lama' => 'password123',
                'password_baru' => 'RahasiaPribadiKuat',
                'password_baru_confirmation' => 'RahasiaPribadiKuat',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('RahasiaPribadiKuat', $kaprodi->refresh()->password));
    }

    public function test_password_lama_salah_ditolak(): void
    {
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->post('/akun/password', [
                'password_lama' => 'salah-sekali',
                'password_baru' => 'RahasiaPribadiKuat',
                'password_baru_confirmation' => 'RahasiaPribadiKuat',
            ])
            // Pesan error juga harus terlihat, jadi tetap ke tab Settings.
            ->assertRedirect(route('dosen.dashboard', ['tab' => 'settings']))
            ->assertSessionHasErrors('password_lama');

        $this->assertTrue(Hash::check('password123', $dosen->refresh()->password));
    }

    public function test_konfirmasi_harus_sama(): void
    {
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->post('/akun/password', [
                'password_lama' => 'password123',
                'password_baru' => 'RahasiaPribadiKuat',
                'password_baru_confirmation' => 'RahasiaPribadiKuatLain',
            ])
            ->assertSessionHasErrors('password_baru');

        $this->assertTrue(Hash::check('password123', $dosen->refresh()->password));
    }

    public function test_password_baru_terlalu_pendek_ditolak(): void
    {
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->post('/akun/password', [
                'password_lama' => 'password123',
                'password_baru' => 'pendek',
                'password_baru_confirmation' => 'pendek',
            ])
            ->assertSessionHasErrors('password_baru');
    }

    public function test_password_baru_tidak_boleh_sama_dengan_lama(): void
    {
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->post('/akun/password', [
                'password_lama' => 'password123',
                'password_baru' => 'password123',
                'password_baru_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors('password_baru');
    }

    public function test_tamu_tidak_bisa_mengganti_password(): void
    {
        $this->post('/akun/password', [
            'password_lama' => 'password123',
            'password_baru' => 'RahasiaPribadiKuat',
            'password_baru_confirmation' => 'RahasiaPribadiKuat',
        ])->assertRedirect('/login');
    }

    public function test_password_tidak_boleh_kosong(): void
    {
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->post('/akun/password', [])
            ->assertSessionHasErrors(['password_lama', 'password_baru']);
    }

    public function test_parameter_tab_ngawur_diabaikan(): void
    {
        // ?tab= berisi apa pun harus tetap membuka tab yang benar, bukan
        // membuat panel sembunyi semua atau membuat halaman error.
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->get('/dosen/dashboard?tab=ngawur-total')
            ->assertOk()
            ->assertSee('window.tabAwal = "dashboard"', escape: false);

        $this->actingAs($dosen)
            ->get('/dosen/dashboard?tab=settings')
            ->assertOk()
            ->assertSee('window.tabAwal = "settings"', escape: false);
    }

    // --------------------------------------------------------- tampilannya

    public function test_tab_settings_dosen_menampilkan_form_ganti_password(): void
    {
        $dosen = $this->buat();

        $this->actingAs($dosen)
            ->get('/dosen/dashboard')
            ->assertOk()
            ->assertSee('Ganti Password', escape: false)
            ->assertSee('action="'.route('akun.password.ubah').'"', escape: false)
            ->assertSee('name="password_lama"', escape: false)
            ->assertSee('name="password_baru"', escape: false)
            ->assertSee('name="password_baru_confirmation"', escape: false)
            ->assertSee($dosen->email);
    }

    public function test_tab_settings_kaprodi_menampilkan_form_ganti_password(): void
    {
        $kaprodi = $this->buat('kaprodi');

        $this->actingAs($kaprodi)
            ->get('/kaprodi/dashboard')
            ->assertOk()
            ->assertSee('Ganti Password', escape: false)
            ->assertSee('action="'.route('akun.password.ubah').'"', escape: false)
            ->assertSee($kaprodi->email);
    }

    public function test_password_lama_tidak_pernah_ditampilkan_di_halaman(): void
    {
        $dosen = $this->buat('dosen', 'rahasia-yang-tak-boleh-bocor');

        $html = $this->actingAs($dosen)->get('/dosen/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('rahasia-yang-tak-boleh-bocor', $html);
        $this->assertStringNotContainsString($dosen->password, $html);
    }
}
