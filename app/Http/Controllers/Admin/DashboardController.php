<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Menampilkan dashboard utama berdasarkan role pengguna yang sedang login.
     */
    public function index()
    {
        $user = Auth::user();

        // Arahkan ke view atau logika masing-masing role
        if ($user->role === 'admin') {
            return $this->adminDashboard();
        } elseif ($user->role === 'kaprodi') {
            return $this->kaprodiDashboard();
        } elseif ($user->role === 'dosen') {
            return $this->dosenDashboard();
        }

        // Jika role tidak dikenali, lakukan logout
        Auth::logout();

        return redirect()->route('login')->withErrors(['email' => 'Role akun tidak valid.']);
    }

    // Method khusus per role
    public function adminDashboard()
    {
        return view('Admin.AdminDashboard', ['user' => Auth::user()]);
    }

    public function kaprodiDashboard()
    {
        return view('Kaprodi.Dashboard', ['user' => Auth::user()]);
    }

    public function dosenDashboard()
    {
        return view('Dosen.Dashboard', ['user' => Auth::user()]);
    }
}
