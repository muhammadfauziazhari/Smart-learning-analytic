<?php

namespace Tests\Feature;

use App\Models\MataKuliah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Perintah `akun:password-bawaan`.
 *
 * Tujuannya: semua akun Dosen/Kaprodi bisa masuk dengan password yang sama
 * dan mudah diingat (keputusan pengguna, 18 Okt 2026), lalu mereka sendiri
 * yang menggantinya lewat tab Settings.
 *
 * Dua hal yang WAJIB dijaga perintah ini:
 *   1. Akun admin TIDAK boleh ikut disentuh.
 *   2. Penugasan, izin mata kuliah, dan data lain tidak boleh berubah.
 */
class PasswordBawaanTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $role, string $email, string $password = 'lama-kGobelan'): User
    {
        return User::create([
            'nama' => 'Nama '.strtoupper($role),
            'nomor_induk' => '1986'.substr(md5($email), 0, 14),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_semua_dosen_dan_kaprodi_diset_ke_password_bawaan(): void
    {
        $dosen = $this->buat('dosen', 'dosen@uji.local');
        $kaprodi = $this->buat('kaprodi', 'kaprodi@uji.local');

        $this->artisan('akun:password-bawaan')
            ->assertSuccessful();

        $this->assertTrue(Hash::check(User::PASSWORD_BAWAAN, $dosen->refresh()->password));
        $this->assertTrue(Hash::check(User::PASSWORD_BAWAAN, $kaprodi->refresh()->password));
    }

    public function test_akun_admin_tidak_ikut_diubah(): void
    {
        $admin = $this->buat('admin', 'admin@uji.local', 'rahasia-admin');

        $this->artisan('akun:password-bawaan')->assertSuccessful();

        $this->assertTrue(
            Hash::check('rahasia-admin', $admin->refresh()->password),
            'Password admin tidak boleh diubah perintah ini.',
        );
    }

    public function test_dry_run_tidak_mengubah_apa_pun(): void
    {
        $dosen = $this->buat('dosen', 'dosen@uji.local');

        $this->artisan('akun:password-bawaan', ['--dry-run' => true])
            ->expectsOutputToContain(User::PASSWORD_BAWAAN)
            ->assertSuccessful();

        $this->assertTrue(Hash::check('lama-kGobelan', $dosen->refresh()->password));
    }

    public function test_data_lain_tetap_utuh(): void
    {
        $dosen = $this->buat('dosen', 'dosen@uji.local');
        $mataKuliah = MataKuliah::firstOrCreate(
            ['kode' => 'IF209'],
            ['nama' => 'Jaringan Komputer', 'sks' => 3],
        );

        $dosen->mataKuliahAkses()->attach($mataKuliah->id);

        $this->artisan('akun:password-bawaan')->assertSuccessful();

        $dosen->refresh();

        $this->assertTrue($dosen->is_active);
        $this->assertSame('dosen@uji.local', $dosen->email);
        $this->assertTrue($dosen->mataKuliahAkses->contains($mataKuliah));
    }

    public function test_peran_tidak_dikenal_ditolak(): void
    {
        $this->artisan('akun:password-bawaan', ['--peran' => ['admin']])
            ->assertFailed();
    }

    public function test_peran_bisa_dibatasi(): void
    {
        $dosen = $this->buat('dosen', 'dosen@uji.local');
        $kaprodi = $this->buat('kaprodi', 'kaprodi@uji.local');

        $this->artisan('akun:password-bawaan', ['--peran' => ['dosen']])
            ->assertSuccessful();

        $this->assertTrue(Hash::check(User::PASSWORD_BAWAAN, $dosen->refresh()->password));
        $this->assertTrue(Hash::check('lama-kGobelan', $kaprodi->refresh()->password));
    }

    public function test_akun_yang_sudah_pakai_password_bawaan_tidak_diubah_lagi(): void
    {
        $dosen = $this->buat('dosen', 'dosen@uji.local', User::PASSWORD_BAWAAN);
        $hashSebelum = $dosen->password;

        $this->artisan('akun:password-bawaan')->assertSuccessful();

        // Hashnya harus tetap sama: tidak ada gunanya di-hash ulang berkali-kali.
        $this->assertSame($hashSebelum, $dosen->refresh()->password);
    }
}
