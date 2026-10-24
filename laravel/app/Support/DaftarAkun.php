<?php

namespace App\Support;

use App\Models\MataKuliah;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Data untuk tab "Kelola Akun".
 *
 * Dipakai AkunController dan view composer, bukan hanya controller,
 * karena semua tab Admin berada di satu halaman. Kalau query-nya hanya
 * ada di controller, membuka /admin/dashboard lalu klik "Kelola Akun"
 * akan menampilkan tab kosong — persis fitur yang diminta hilang.
 */
class DaftarAkun
{
    /**
     * @return array<string, mixed>
     */
    public static function data(Request $request): array
    {
        return [
            'akun' => self::query($request)->paginate(15)->withQueryString(),
            'q' => $request->string('q')->trim()->value(),
            'role' => $request->string('role')->value(),
            'totalDosen' => User::where('role', 'dosen')->count(),
            'totalKaprodi' => User::where('role', 'kaprodi')->count(),
            'tanpaNomorInduk' => User::where('role', 'dosen')
                ->where(fn ($query) => $query
                    ->whereNull('nomor_induk')
                    ->orWhere('nomor_induk', ''))
                ->count(),

            // Daftar mata kuliah untuk kolom centang "mata kuliah terpantau".
            'mataKuliah' => MataKuliah::with('prodi')
                ->withCount('kelas')
                ->orderBy('kode')
                ->get(),
        ];
    }

    /**
     * Query daftar akun.
     *
     * Penyaring hanya dipakai kalau request memang datang ke tab ini
     * (/admin/akun). Kalau tidak, panel lain yang ikut terender tidak
     * ikut tersaring — search belong ke URL-nya masing-masing.
     */
    public static function query(Request $request): Builder
    {
        $cari = $request->string('q')->trim();
        $peran = $request->string('role');
        $saring = $request->routeIs('admin.akun');

        return User::query()
            ->withCount('kelasDiampu')
            ->withCount('mataKuliahAkses')
            // Dipakai kolom centang "mata kuliah terpantau", supaya tidak
            // terjadi query per baris saat Blade merender.
            ->with('mataKuliahAkses')
            ->when($saring && $cari->isNotEmpty(), function ($query) use ($cari) {
                $query->where(function ($inner) use ($cari) {
                    $inner->where('nama', 'like', "%{$cari}%")
                        ->orWhere('email', 'like', "%{$cari}%")
                        ->orWhere('nomor_induk', 'like', "%{$cari}%");
                });
            })
            ->when($saring && $peran->isNotEmpty(), fn ($query) => $query->where('role', $peran))
            ->orderBy('role')
            ->orderBy('nama');
    }
}
