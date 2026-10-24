<?php

namespace App\Http\Middleware;

use App\Models\Kelas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak akses ke kelas di luar cakupan pengguna (FR-05).
 *
 * Dipakai pada route yang punya parameter kelas, misalnya
 * Route::get('/kelas/{kelas}', ...)->middleware('kelas.akses');
 *
 * Tanpa middleware ini, Dosen cukup menebak angka ID kelas milik
 *ampilkan lain dan membukanya langsung lewat URL.
 */
class CegahAksesKelas
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();
        $kelas = $request->route('kelas');

        if ($pengguna === null || $kelas === null) {
            return $next($request);
        }

        // Terima juga instance Kelas, bukan hanya ID.
        if (! $kelas instanceof Kelas) {
            $kelas = Kelas::find($kelas);
        }

        if ($kelas === null) {
            abort(404);
        }

        abort_unless($pengguna->can('view', $kelas), 403, 'Anda tidak punya akses ke kelas ini.');

        return $next($request);
    }
}
