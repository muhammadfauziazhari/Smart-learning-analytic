<?php

namespace App\View\Composers;

use App\Models\MataKuliah;
use App\Support\DaftarAkun;
use App\Support\DaftarPenugasan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mengisi data untuk semua tab di halaman dashboard Admin.
 *
 * Kenapa bukan controller? Karena /admin/dashboard, /admin/akun, dan
 * /admin/penugasan merender cangkang yang sama, sedangkan tiap tab
 * butuh datanya sendiri. Kalau datanya hanya diisi controller, membuka
 * halaman lewat tab lain membuat tab yang tidak aktif kosong — padahal
 * sidebar harus bisa berpindah ke sana.
 *
 * Nilai yang sudah dikirim controller tidak ditimpa, jadi membuka
 * /admin/akun langsung tidak mengambil daftar akun dua kali.
 */
class PanelAdminComposer
{
    public function compose(View $view): void
    {
        $request = request();

        $data = array_merge(
            $this->ringkasan(),
            DaftarAkun::data($request),
            DaftarPenugasan::data($request),
        );

        // Yang sudah diisi controller (mis. $akun di /admin/akun) dipakai
        // apa adanya supaya tidak ada query ganda.
        $sudah = array_intersect_key($view->getData(), $data);

        $view->with(array_diff_key($data, $sudah));
    }

    /**
     * Angka ringkas untuk tab Dashboard.
     *
     * @return array<string, mixed>
     */
    private function ringkasan(): array
    {
        // PENTING: agregasi ditulis dengan selectRaw + get(), bukan
        // pluck('alias', ...). pluck menimpa daftar kolom dengan nama
        // alias itu sendiri, sehingga MySQL melapor "Unknown column 'j'"
        // sementara SQLite menerimanya tanpa error. Karena test berjalan di
        // SQLite, kesalahan seperti ini baru terlihat di browser.
        $jumlahLog = DB::table('log_aktivitas')
            ->selectRaw('kode_mata_kuliah, count(*) as jumlah')
            ->whereNotNull('kode_mata_kuliah')
            ->where('kode_mata_kuliah', '<>', '')
            ->groupBy('kode_mata_kuliah')
            ->get();

        $logPerMataKuliah = [];

        foreach ($jumlahLog as $baris) {
            $logPerMataKuliah[$baris->kode_mata_kuliah] = (int) $baris->jumlah;
        }

        $daftar = [];

        foreach (MataKuliah::query()->with('kelas')->orderBy('kode')->get() as $mk) {
            $daftar[] = [
                'kode' => $mk->kode,
                'nama' => $mk->nama,
                'kelas' => $mk->kelas->count(),
                'mahasiswa' => DB::table('kelas_mahasiswa')
                    ->whereIn('kelas_id', $mk->kelas->pluck('id'))
                    ->distinct()
                    ->count('nim'),
                'dosen' => DB::table('kelas_dosen')
                    ->whereIn('kelas_id', $mk->kelas->pluck('id'))
                    ->distinct()
                    ->count('user_id'),
                'log' => $logPerMataKuliah[$mk->kode] ?? 0,
            ];
        }

        return [
            'ringkasan' => [
                'mataKuliah' => count($daftar),
                'kelas' => array_sum(array_column($daftar, 'kelas')),
                'mahasiswa' => (int) DB::table('kelas_mahasiswa')->distinct()->count('nim'),
                'log' => array_sum($logPerMataKuliah),
                'mataKuliahTerdaftar' => $daftar,
            ],
        ];
    }
}
