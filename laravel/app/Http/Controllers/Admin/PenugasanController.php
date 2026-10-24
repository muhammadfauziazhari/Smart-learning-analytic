<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Kelas;
use App\Models\User;
use App\Support\DaftarPenugasan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Penugasan akademik (FR-03).
 *
 * Dua hal yang dibangun di sini:
 *
 * 1. Dosen pengampu -> kelas. Inilah yang membuat batas akses Dosen
 *    bekerja (FR-05). Tanpa assignment ini, Dosen tidak punya kelas
 *    sama sekali.
 *
 * 2. Mahasiswa -> kelas. Cukup NIM dan nama. TIDAK ada akun, password,
 *    atau email untuk mahasiswa, karena mereka tidak pernah masuk ke
 *    sistem ini.
 */
class PenugasanController extends Controller
{
    /**
     * Tab "Penugasan Akademik" di dalam halaman dashboard Admin.
     *
     * Mengembalikan cangkang yang sama dengan tab lain; tab aktifnya yang
     * berbeda, supaya sidebar bisa berpindah tanpa memuat ulang halaman.
     */
    public function index(Request $request): View
    {
        return view('Admin.AdminDashboard', [
            'user' => Auth::user(),
            'tabAktif' => 'penugasan',
        ] + DaftarPenugasan::data($request));
    }

    /**
     * Catat satu dosen pengampu untuk sebuah kelas.
     */
    public function tambahDosen(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:32'],
            'peran' => ['required', 'in:pengampu,pembantu'],
        ]);

        $dosen = $this->cariPengguna($data['nomor_induk']);

        if ($dosen === null) {
            return back()->with(
                'error',
                "NIP {$data['nomor_induk']} belum terdaftar. Daftarkan dulu lewat menu Kelola Akun."
            );
        }

        $kelas->dosen()->syncWithoutDetaching([
            $dosen->id => ['peran' => $data['peran']],
        ]);

        return back()->with('success', "{$dosen->nama} dicatat sebagai {$data['peran']} kelas {$kelas->label()}.");
    }

    public function hapusDosen(Kelas $kelas, User $user): RedirectResponse
    {
        $kelas->dosen()->detach($user->id);

        return back()->with('success', "Akses {$user->nama} pada kelas ini dicabut.");
    }

    /**
     * Catat mahasiswa ke kelas dengan paste daftar NIM.
     *
     * TIDAK membuat akun. Mahasiswa tidak pernah masuk ke sistem ini.
     * Yang dicatat hanya NIM dan, kalau tersedia, nama.
     *
     * Bentuk yang diterima per baris:
     *   3312501013
     *   3312501013 - Budi Santoso
     *   3312501013 Budi Santoso
     */
    public function tambahMahasiswa(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $request->validate([
            'nim' => ['required', 'string', 'max:20000'],
        ], [], ['nim' => 'daftar NIM']);

        $daftar = $this->pecahNim($data['nim']);

        if ($daftar === []) {
            return back()->with('error', 'Tidak ada NIM yang bisa dibaca dari isian tersebut.');
        }

        $sudah = Enrollment::where('kelas_id', $kelas->id)
            ->whereIn('nim', array_column($daftar, 'nim'))
            ->pluck('nim')
            ->all();

        $baru = array_values(array_filter(
            $daftar,
            fn (array $item) => ! in_array($item['nim'], $sudah, true)
        ));

        foreach ($baru as $item) {
            Enrollment::create([
                'kelas_id' => $kelas->id,
                'nim' => $item['nim'],
                'nama' => $item['nama'],
                'terdaftar_pada' => now(),
            ]);
        }

        $pesan = sprintf('%d mahasiswa dicatat ke kelas %s.', count($baru), $kelas->label());

        if ($sudah !== []) {
            $pesan .= sprintf(' %d sudah ada sebelumnya dan dilewati.', count($sudah));
        }

        return back()->with('success', $pesan);
    }

    public function hapusMahasiswa(Kelas $kelas, Enrollment $enrollment): RedirectResponse
    {
        $nim = $enrollment->nim;
        $enrollment->delete();

        return back()->with('success', "Mahasiswa NIM {$nim} dikeluarkan dari kelas ini.");
    }

    /**
     * Pecah isian NIM menjadi daftar [{nim, nama}].
     *
     * Boleh dipisah baris baru, koma, atau titik koma. Nama diambil dari
     * sisa baris kalau ada.
     *
     * @return array<int, array{nim: string, nama: string|null}>
     */
    private function pecahNim(string $teks): array
    {
        $baris = preg_split('/[\r\n]+/', trim($teks), -1, PREG_SPLIT_NO_EMPTY);

        $hasil = [];

        foreach ($baris as $barisTeks) {
            foreach (preg_split('/[;,]+/', $barisTeks, -1, PREG_SPLIT_NO_EMPTY) as $potongan) {
                $potongan = trim($potongan);
                if (preg_match('/^(\d{6,18})(?<sisa>.*)$/u', $potongan, $m) !== 1) {
                    continue;
                }

                $sisa = trim($m['sisa']);
                // Buang pemisah di depan sisa: "3312501013 - Budi".
                $sisa = trim(preg_replace('/^[\s\-\x{2013}:|]+/u', '', $sisa) ?? '');

                // Sisa yang hanya angka itu NIM berikutnya yang terpisah
                // spasi, bukan nama.
                if ($sisa !== '' && preg_match('/^\d+$/', $sisa) !== 1) {
                    $hasil[] = ['nim' => $m[1], 'nama' => $this->rapikanNama($sisa)];

                    continue;
                }

                $hasil[] = ['nim' => $m[1], 'nama' => null];
            }
        }

        // Buang NIM kembar, prioritaskan yang punya nama.
        $unik = [];
        foreach ($hasil as $item) {
            if ($item['nama'] !== null || ! isset($unik[$item['nim']])) {
                $unik[$item['nim']] = $item['nama'];
            }
        }

        // PENTING: kunci array PHP yang isinya angka akan diubah jadi
        // integer. Tanpa cast di sini, NIM jadi integer dan perbandingan
        // ketat (in_array) selalu gagal terhadap nilai string dari
        // database, sehingga NIM yang sudah terdaftar tetap ikut
        // disimpan lalu menabrak UNIQUE constraint.
        $daftar = [];
        foreach ($unik as $nim => $nama) {
            $daftar[] = ['nim' => (string) $nim, 'nama' => $nama];
        }

        return $daftar;
    }

    /** Rapikan nama: spasi berlebih dibuang, huruf awal jadi besar. */
    private function rapikanNama(string $nama): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', $nama) ?? '');

        return mb_convert_case($nama, MB_CASE_TITLE, 'UTF-8');
    }

    private function cariPengguna(string $nomorInduk): ?User
    {
        return User::where('nomor_induk', $nomorInduk)
            ->whereIn('role', ['dosen', 'kaprodi', 'admin'])
            ->first();
    }
}
