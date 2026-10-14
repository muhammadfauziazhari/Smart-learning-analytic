<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_register_tampil(): void
    {
        $this->get('/register')->assertOk()->assertViewIs('Auth.Register');
    }

    public function test_berhasil_register_menampilkan_alert_dan_mengarah_ke_login(): void
    {
        $response = $this->post('/register', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);

        // Redirect ke halaman login
        $response->assertRedirect('/login');

        // Pesan sukses tersimpan di session
        $response->assertSessionHas('success');

        // Akun tersimpan di database
        $this->assertDatabaseHas('users', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'role' => 'dosen',
        ]);

        // Password di-hash, bukan disimpan polos
        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_setelah_register_user_belum_login(): void
    {
        $this->post('/register', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);

        $this->assertGuest();

        // Halaman dashboard tetap terproteksi
        $this->get('/dosen/dashboard')->assertRedirect('/login');
    }

    public function test_alert_berhasil_tampil_di_halaman_login(): void
    {
        $this->post('/register', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);

        // Alert harus muncul di halaman login tujuan redirect
        $this->get('/login')
            ->assertOk()
            ->assertSee('Akun berhasil dibuat')
            ->assertSee('budi@example.com')
            ->assertSee('js-flash-alert', escape: false);
    }

    public function test_register_gagal_jika_email_bentrok(): void
    {
        User::create([
            'nama' => 'Sudah Ada',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);

        $response = $this->from('/register')->post('/register', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('success');

        // Akun tidak dibuat dua kali
        $this->assertSame(1, User::where('email', 'budi@example.com')->count());
    }

    public static function invalidPayloadProvider(): array
    {
        return [
            'nama kosong' => [['nama' => ''], 'nama'],
            'email tidak valid' => [['email' => 'bukan-email'], 'email'],
            'password terlalu pendek' => [['password' => '123'], 'password'],
            'role tidak dikenal' => [['role' => 'pengajar'], 'role'],
        ];
    }

    #[DataProvider('invalidPayloadProvider')]
    public function test_register_menolak_data_tidak_valid(array $override, string $expectedErrorField): void
    {
        $response = $this->from('/register')->post('/register', array_merge([
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ], $override));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors($expectedErrorField);
        $response->assertSessionMissing('success');
        $this->assertGuest();
    }
}
