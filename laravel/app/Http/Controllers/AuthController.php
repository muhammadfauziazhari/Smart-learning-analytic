<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const BATAS_PERCOBAAN = 5;
    private const JEDA_PERCOBAAN_DETIK = 60;

    /**
     * Menampilkan halaman form login.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            $rute = $this->ruteDashboard(Auth::user());

            if ($rute !== null) {
                return redirect()->route($rute);
            }
        }

        return view('Auth.Login');
    }

    /**
     * Memproses submit form login.
     */
    public function login(Request $request)
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email terlalu panjang.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $kunci = 'login|'.Str::lower($kredensial['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PERCOBAAN)) {
            $detik = RateLimiter::availableIn($kunci);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$detik} detik.",
            ]);
        }

        $kredensial['is_active'] = true;

        if (Auth::attempt($kredensial, $request->boolean('remember'))) {
            RateLimiter::clear($kunci);

            $request->session()->regenerate();

            $user = Auth::user();
            $rute = $this->ruteDashboard($user);

            if ($rute === null) {
                Auth::logout();

                return back()->withErrors(['email' => 'Role akun tidak valid.'])->onlyInput('email');
            }

            return redirect()->intended(route($rute));
        }

        RateLimiter::hit($kunci, self::JEDA_PERCOBAAN_DETIK);

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    /**
     * Melakukan logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Anda telah keluar dari sistem.');
    }

    /**
     * Nama route dashboard untuk peran pengguna.
     */
    private function ruteDashboard(?User $user): ?string
    {
        return match ($user?->role) {
            'admin' => 'admin.dashboard',
            'kaprodi' => 'kaprodi.dashboard',
            'dosen' => 'dosen.dashboard',
            default => null,
        };
    }
}
