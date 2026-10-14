<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            return view('dashboard.admin', compact('user'));
        } elseif ($user->role === 'kaprodi') {
            return view('dashboard.kaprodi', compact('user'));
        } elseif ($user->role === 'dosen') {
            return view('dashboard.dosen', compact('user'));
        }

        // Jika role tidak dikenali, lakukan logout
        Auth::logout();
        return redirect()->route('login')->withErrors(['email' => 'Role akun tidak valid.']);
    }

    // (Opsional) Method khusus jika ingin memisahkan fungsi controller per role
    public function adminDashboard()
    {
        return view('dashboard.admin');
    }

    public function kaprodiDashboard()
    {
        return view('dashboard.kaprodi');
    }

    public function dosenDashboard()
    {
        return view('dashboard.dosen');
    }
}
