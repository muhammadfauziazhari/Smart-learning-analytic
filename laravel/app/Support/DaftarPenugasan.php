<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\MataKuliah;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data untuk tab "Penugasan Akademik".
 *
 * Sama seperti DaftarAkun: dipakai controller sekaligus view composer
 * supaya tab ini tetap terisi walau halaman dibuka dari tab lain.
 */
class DaftarPenugasan
{
    /**
     * @return array<string, mixed>
     */
    public static function data(Request $request): array
    {
        return [
            'kelas' => self::query($request)->paginate(10)->withQueryString(),
            'pilihanMataKuliah' => MataKuliah::query()
                ->with('prodi')
                ->orderBy('kode')
                ->get(),

            // NIM yang sudah tercatat di kelas lain, supaya form bisa memberi
            // tahu sebelum disimpan.
            'nimTercatat' => self::nimTercatat(),
        ];
    }

    /**
     * Query daftar kelas beserta relasinya.
     *
     * Penyaring hanya aktif untuk request ke /admin/penugasan, supaya
     * pencarian tab ini tidak ikut menyaring daftar akun di tab lain.
     */
    public static function query(Request $request)
    {
        $cari = $request->string('q')->trim();
        $mataKuliah = $request->integer('mata_kuliah');
        $saring = $request->routeIs('admin.penugasan');

        return Kelas::query()
            ->with(['mataKuliah', 'dosen', 'mahasiswa'])
            ->when($saring && $cari->isNotEmpty(), function ($query) use ($cari) {
                $query->where(function ($inner) use ($cari) {
                    $inner->where('nama', 'like', "%{$cari}%")
                        ->orWhere('kode', 'like', "%{$cari}%")
                        ->orWhereHas('mataKuliah', fn ($q) => $q
                            ->where('nama', 'like', "%{$cari}%")
                            ->orWhere('kode', 'like', "%{$cari}%"));
                });
            })
            ->when($saring && $mataKuliah > 0, fn ($query) => $query->where('mata_kuliah_id', $mataKuliah))
            ->orderBy('mata_kuliah_id')
            ->orderBy('kode');
    }

    /**
     * NIM per kode kelas, dikelompokkan per NIM.
     *
     * @return Collection<string, Collection>
     */
    private static function nimTercatat(): Collection
    {
        return DB::table('kelas_mahasiswa')
            ->join('kelas', 'kelas.id', '=', 'kelas_mahasiswa.kelas_id')
            ->get(['kelas_mahasiswa.nim', 'kelas.kode'])
            ->groupBy('nim');
    }
}
