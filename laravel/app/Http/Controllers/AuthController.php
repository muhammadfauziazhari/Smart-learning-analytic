<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // 1. Menampilkan halaman form login
    public function showLoginForm()
    {
        return view('Auth.Login');
    }

    // 2. Memproses submit form login
    public function login(Request $request)
    {
        // Validasi input
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // `is_active` ikut jadi syarat. Tanpa baris ini, akun yang
        // dinonaktifkan Admin lewat Kelola Akun masih bisa masuk.
        $credentials['is_active'] = true;

        // Cek apakah email dan password cocok
        if (Auth::attempt($credentials, $request->remember)) {
            // Regenerasi session untuk keamanan (mencegah session fixation)
            $request->session()->regenerate();

            $user = Auth::user();

            // Pengecekan role dan redirect ke route masing-masing
            if ($user->role === 'admin') {
                return redirect()->intended(route('admin.dashboard'));
            } elseif ($user->role === 'kaprodi') {
                return redirect()->intended(route('kaprodi.dashboard'));
            } elseif ($user->role === 'dosen') {
                return redirect()->intended(route('dosen.dashboard'));
            }

            // Jika role tidak dikenali
            Auth::logout();

            return back()->withErrors(['email' => 'Role akun tidak valid.']);
        }

        // Password salah ATAU akun dinonaktifkan.
        //
        // Pesannya sengaja sama: membedaikan "password salah" dari "akun
        // dinonaktifkan" memberi attackers informasi tentang akun mana yang
        // ada di sistem.
        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    // Pendaftaran mandiri dihapus (keputusan pengguna, 18 Okt 2026).
    //
    // Dulu ada showRegisterForm() dan register() di sini: keduanya terbuka
    // untuk publik dan menerima role 'admin', sehingga siapa pun bisa
    // membuat akun admin lewat /register. Akun Dosen dan Kaprodi sekarang
    // hanya dibuat Admin melalui menu Kelola Akun (/admin/akun).

    // Melakukan logout
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
