<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Akun milik pengguna sendiri: mengganti password.
 *
 * Ini yang membuat keputusan "semua akun Dosen/Kaprodi memakai password
 * bawaan" masuk akal. Tanpa halaman ini, password bawaan itu jadi kunci
 * permanen yang tidak pernah bisa diganti.
 *
 * Satu route untuk semua peran (Admin, Dosen, Kaprodi). Halaman Settings
 * tiap peran memanggil form yang sama.
 */
class ProfilController extends Controller
{
    /**
     * Ganti password sendiri.
     *
     * Password lama wajib dimasukkan. Kalau tidak, siapa pun yang mendapat
     * sesi (atau memo opens browser) bisa mengunci orang lain keluar hanya
     * dengan membuka tab Settings.
     */
    public function ubahPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password_baru' => ['required', 'string', 'min:8', 'confirmed', 'different:password_lama'],
        ], [
            'password_lama.required' => 'Masukkan password Anda yang sekarang.',
            'password_baru.min' => 'Password baru minimal 8 karakter.',
            'password_baru.confirmed' => 'Pengulangan password baru tidak sama.',
            'password_baru.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['password_lama'], $user->password)) {
            return $this->kembaliKeSettings($user)
                ->withErrors(['password_lama' => 'Password lama salah.']);
        }

        $user->update(['password' => $data['password_baru']]);

        // Sesi tetap aktif supaya pengguna tidak langsung terlempar keluar,
        // tapi id sesinya diganti agar token lama tidak lagi berlaku.
        $request->session()->regenerate();

        return $this->kembaliKeSettings($user)
            ->with('success', 'Password berhasil diubah. Jangan lupa mengingat password barumu.');
    }

    /**
     * Kembali ke dashboard peran pengguna, dengan tab Settings terbuka.
     *
     * Seluruh isi dashboard ada di satu halaman sebagai tab. Kalau pesannya
     * dikembalikan ke tab Dashboard, pengguna akan mengira tidak terjadi
     * apa-apa karena panel Settings masih tersembunyi.
     */
    private function kembaliKeSettings(User $user): RedirectResponse
    {
        $dashboard = match ($user->role) {
            'admin' => 'admin.dashboard',
            'kaprodi' => 'kaprodi.dashboard',
            default => 'dosen.dashboard',
        };

        return redirect()->route($dashboard, ['tab' => 'settings']);
    }
}
