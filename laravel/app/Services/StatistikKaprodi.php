<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Data statistik analitik untuk dashboard Kaprodi (FR-21 / FRK-15).
 *
 * Perhitungannya sendiri ada di StatistikKelas karena Dosen (FR-20)
 * memakai angka yang sama persis. Yang membedakan hanya cakupan kelas
 * dan cara presentation-nya, jadi kelas ini hanya:
 *
 *   1. mengambil kelas sesuai cakupan Kaprodi,
 *   2. menghitung statistik tiap kelas,
 *   3. merangkumnya.
 *
 * Cakupan: Kaprodi melihat SELURUH kelas semua prodi (keputusan pengguna,
 * 18 Okt 2026). Aksesnya lewat `Kelas::scopeUntukPengguna()`, bukan lewat
 * `prodi_id` miliknya sendiri.
 */
class StatistikKaprodi
{
    public function __construct(private StatistikKelas $statistikKelas) {}

    /**
     * Susun seluruh data dashboard Kaprodi.
     *
     * @return array<string, mixed>
     */
    public function untuk(User $kaprodi): array
    {
        $kelasList = $this->kelasTerlihat($kaprodi);

        $daftarKelas = [];

        // Semua kelas dihitung dalam satu panggilan supaya agregasi log dan
        // layanan analytics tidak diulang untuk tiap kelas.
        foreach ($this->statistikKelas->untukBanyak($kelasList) as $data) {
            $daftarKelas[] = $data;
        }

        return $this->statistikKelas->ringkasan($kelasList, $daftarKelas) + [
            'kelas' => $daftarKelas,
        ];
    }

    /**
     * Kelas milik Kaprodi, urut dari kode mata kuliah.
     *
     * @return Collection<int, Kelas>
     */
    private function kelasTerlihat(User $kaprodi)
    {
        return Kelas::query()
            ->untukPengguna($kaprodi)
            ->with(['mataKuliah'])
            ->orderBy('kode')
            ->get()
            ->sortBy(fn (Kelas $k) => (string) ($k->mataKuliah?->kode ?? ''))
            ->values();
    }
}
