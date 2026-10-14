<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // Pastikan Model User di-import

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

        // Jika email atau password salah
        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    // --- TAMBAHAN UNTUK REGISTER ---

    // 3. Menampilkan halaman form register
    public function showRegisterForm()
    {
        return view('Auth.Register');
    }

    // 4. Memproses submit form register
    public function register(Request $request)
    {
        // Validasi input register (nama, email, password, dan role)
        $validatedData = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,dosen,kaprodi'],
        ]);

        // Simpan data user baru ke database dengan password yang di-hash
        $user = User::create([
            'nama' => $validatedData['nama'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => $validatedData['role'],
        ]);

        // Akun baru harus login manual, jangan langsung di-login-kan
        return redirect()
            ->route('login')
            ->with('success', 'Akun berhasil dibuat. Silakan masuk dengan email '.$user->email.'.');
    }

    // 5. Memproses logout
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
