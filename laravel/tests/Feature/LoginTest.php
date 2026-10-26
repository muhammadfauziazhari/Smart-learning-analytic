<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Uji form dan logika login: koneksi form ke backend, pengalihan sesi,
 * validasi, cookie "Ingat saya", batas percobaan, dan pesan logout.
 *
 * Login berhasil untuk tiap peran juga diuji DashboardAccessTest; di sini
 * yang ditekankan adalah perilaku yang tidak terlihat dari markup saja.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role = 'dosen'): User
    {
        return User::create([
            'nama' => 'Pengguna '.$role,
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    public static function peranProvider(): array
    {
        return [
            'admin' => ['admin', '/admin/dashboard'],
            'kaprodi' => ['kaprodi', '/kaprodi/dashboard'],
            'dosen' => ['dosen', '/dosen/dashboard'],
        ];
    }

    public function test_tamu_melihat_form_login_yang_terhubung_ke_backend(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Selamat Datang!')
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('Lupa password?')
            // Pemilih peran dan dashboard demo tidak boleh ada lagi: peran
            // ditentukan dari akun, bukan dari pilihan di form.
            ->assertDontSee('Masuk sebagai')
            ->assertDontSee('Demo UI')
            ->assertDontSee('id="dashboardPage"', false);
    }

    public function test_password_salah_menampilkan_pesan_dan_mempertahankan_email(): void
    {
        $user = $this->buatUser();

        $this->followingRedirects()
            ->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password-salah',
            ])
            ->assertSee('Email atau password yang Anda masukkan salah.')
            ->assertSee('value="'.$user->email.'"', false);
    }

    #[DataProvider('peranProvider')]
    public function test_pengguna_yang_sudah_masuk_diarahkan_ke_dashboard(string $role, string $rute): void
    {
        $user = $this->buatUser($role);

        $this->actingAs($user)->get('/login')->assertRedirect($rute);
    }

    public function test_validasi_login_memakai_pesan_bahasa_indonesia(): void
    {
        $this->post('/login', [
            'email' => 'bukan-email',
            'password' => '',
        ])->assertSessionHasErrors([
            'email' => 'Format email tidak valid.',
            'password' => 'Password wajib diisi.',
        ]);
    }

    public function test_ingat_saya_membuat_cookie_remember(): void
    {
        $user = $this->buatUser();

        $respons = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => '1',
        ]);

        $respons->assertRedirect('/dosen/dashboard');
        $this->assertAuthenticatedAs($user);

        $namaCookie = collect($respons->headers->getCookies())
            ->map(fn ($cookie) => $cookie->getName());

        $this->assertTrue(
            $namaCookie->contains(fn (string $nama) => str_starts_with($nama, 'remember_web_')),
            'Cookie remember_web_ tidak ditemukan pada respons login.',
        );
    }

    public function test_percobaan_login_dibatasi_setelah_lima_kali_gagal(): void
    {
        $user = $this->buatUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password-salah',
            ])->assertSessionHasErrors('email');
        }

        // Percobaan keenam ditolak walau passwordnya benar.
        $respons = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $respons->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            session('errors')->first('email'),
        );
    }

    public function test_login_berhasil_menghapus_hitungan_percobaan(): void
    {
        $user = $this->buatUser();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password-salah',
            ]);
        }

        $kunci = 'login|'.strtolower($user->email).'|127.0.0.1';
        $this->assertSame(4, RateLimiter::attempts($kunci));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/dosen/dashboard');

        $this->assertSame(0, RateLimiter::attempts($kunci));
    }

    public function test_logout_mengakhiri_sesi_dan_menampilkan_pesan(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/login')->assertSee('Anda telah keluar dari sistem.');
    }
}
