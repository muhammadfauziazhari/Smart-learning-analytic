<?php

namespace App\Services;

use App\Models\LogAktivitas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tiga kartu ringkasan aktivitas + daftar materi untuk dashboard Dosen.
 *
 * Sumbernya hanya `log_aktivitas`, tanpa memanggil layanan analytics lagi:
 * level risiko sudah dihitung StatistikKelas untuk blok kelas, dan di sini
 * tinggal dihitung jumlahnya.
 *
 * TIGA KEPUTUSAN PENGGUNA (22 Okt 2026) YANG MEMBENTUK ANGKA DI SINI:
 *
 *   1. "Mahasiswa login" = mahasiswa berbeda yang punya aktivitas apa pun
 *      pada satu hari. Log Moodle yang diimpor TIDAK memuat event login
 *      sama sekali (0 baris berisi "login"/"logged"), jadi aktivitas harian
 *      dipakai sebagai proxy yang bisa dipertanggungjawabkan. Rata-rata
 *      dihitung atas hari yang memang ada aktivitasnya, bukan atas hari
 *      kalender: hari libur tanpa aktivitas bukan "mahasiswa tidak login".
 *
 *   2. "Persentase mahasiswa akses materi" = rata-rata persentase per kelas.
 *      Tiap kelas dihitung sendiri (mahasiswa yang membuka minimal satu
 *      materi dibagi jumlah mahasiswa kelas), lalu dirata-ratakan. Kelas
 *      besar tidak boleh mendominasi angka satu kelas kecil.
 *
 *   3. "Clustering aktif/sedang/at risk" memakai LEVEL RISIKO hasil
 *      analytics (low/medium/high), bukan label K-Means. Alasannya: label
 *      K-Means yang ada hanya "Sangat Aktif/Aktif/Cukup" dan tidak pernah
 *      menghasilkan "Pasif" pada data IF209, sehingga donut tiga bagian
 *      akan selalu kosong di bagian "At Risk". Level risiko adalah tiga
 *      kelompok nyata yang juga dipakai tab Keaktifan.
 *
 * MATERI YANG DIHITUNG
 *
 * Hanya nama yang dimulai "Modul ..." atau "Materi ..." — keputusan
 * pengguna. Soal ATS/AAS, "Download Packet Tracer", dan video-video
 * pendek tidak ikut, karena daftar "paling sering diakses" akan didominasi
 * soal ujian, dan daftar "paling jarang" didominasi video berdurasi pendek
 * yang memang bukan materi utama.
 *
 * Semua angka dibatasi jendela semester kelas (periodeAwal..periodeAkhir
 * dari StatistikKelas), sama seperti blok kelas di dashboard, supaya kartu
 * dan tabel tidak bercerita tentang rentang waktu yang berbeda.
 */
class StatistikAktivitas
{
    /** Banyak materi di tiap daftar (paling sering / paling jarang). */
    public const BATAS_MATERI = 5;

    /**
     * Ringkasan aktivitas untuk seluruh kelas yang sedang ditampilkan.
     *
     * @param  list<array<string, mixed>>  $daftarKelas  Keluaran StatistikKelas
     *                                                   (butuh kode MK, periodeAwal/Akhir, dan daftar mahasiswa berisi nim + level).
     * @return array<string, mixed>
     */
    public function untuk(array $daftarKelas): array
    {
        $kosong = $this->kosong();

        if ($daftarKelas === []) {
            return $kosong;
        }

        $kodeMk = collect($daftarKelas)->pluck('kode')->filter(fn ($k) => $k !== null && $k !== '-')->unique()->values()->all();

        $nim = [];
        foreach ($daftarKelas as $kelas) {
            foreach ($kelas['mahasiswa'] ?? [] as $mhs) {
                $nim['_'.$mhs['nim']] = true;
            }
        }

        $nim = array_map(fn ($kunci) => substr((string) $kunci, 1), array_keys($nim));

        if ($kodeMk === [] || $nim === []) {
            return $kosong;
        }

        $periode = $this->periode($daftarKelas);
        $materi = $this->materi($kodeMk, $nim, $periode);

        return [
            'login' => $this->login($kodeMk, $nim, $periode),
            'materi' => $this->persenMateri($daftarKelas, $kodeMk, $periode),
            'risiko' => $this->risiko($daftarKelas),
            'materiSering' => $materi['sering'],
            'materiJarang' => $materi['jarang'],
            'jumlahMateri' => $materi['jumlah'],
            'totalAkses' => $materi['total'],
            'periode' => $periode['label'],
        ];
    }

    /**
     * Struktur kosong, dipakai juga saat dashboard tidak punya kelas.
     *
     * @return array<string, mixed>
     */
    private function kosong(): array
    {
        return [
            'login' => ['maks' => null, 'min' => null, 'rata' => null, 'hari' => 0],
            'materi' => ['persen' => null, 'min' => null, 'maks' => null, 'jumlahKelas' => 0],
            'risiko' => ['aktif' => 0, 'sedang' => 0, 'atRisk' => 0, 'belum' => 0, 'total' => 0],
            'materiSering' => [],
            'materiJarang' => [],
            'jumlahMateri' => 0,
            'totalAkses' => 0,
            'periode' => 'Belum ada data',
        ];
    }

    /**
     * Jendela waktu dari kelas yang sedang ditampilkan.
     *
     * Tidak memakai tanggal hari ini: dashboard selalu bercerita tentang
     * semester kelasnya, bukan tentang kapan halaman dibuka.
     *
     * @param  list<array<string, mixed>>  $daftarKelas
     * @return array{awal: ?string, akhir: ?string, label: string}
     */
    private function periode(array $daftarKelas): array
    {
        $awal = collect($daftarKelas)->pluck('periodeAwal')->filter()->min();
        $akhir = collect($daftarKelas)->pluck('periodeAkhir')->filter()->max();

        if ($awal === null || $akhir === null) {
            return ['awal' => null, 'akhir' => null, 'label' => 'Seluruh periode data'];
        }

        return [
            'awal' => $awal,
            'akhir' => $akhir,
            'label' => StatistikKelas::labelPeriode($awal).' - '.StatistikKelas::labelPeriode($akhir),
        ];
    }

    /**
     * Mahasiswa aktif per hari: min, rata-rata, maks.
     *
     * Satu query; hari dihitung dari tanggal kalender (DATE), bukan dari
     * "sesi" — log tidak punya id sesi.
     *
     * @param  list<string>  $kodeMk
     * @param  list<string>  $nim
     * @param  array{awal: ?string, akhir: ?string, label: string}  $periode
     * @return array{maks: ?int, min: ?int, rata: ?float, hari: int}
     */
    private function login(array $kodeMk, array $nim, array $periode): array
    {
        $harian = DB::table('log_aktivitas')
            ->selectRaw('DATE(occurred_at) as tgl')
            ->selectRaw('COUNT(DISTINCT actor_identifier) as mhs')
            ->whereIn('kode_mata_kuliah', $kodeMk)
            ->where('actor_role', LogAktivitas::PERAN_MAHASISWA)
            ->whereIn('actor_identifier', $nim)
            ->when($periode['awal'], fn ($q) => $q->where('periode', '>=', $periode['awal']))
            ->when($periode['akhir'], fn ($q) => $q->where('periode', '<=', $periode['akhir']))
            ->groupBy('tgl')
            ->get();

        if ($harian->isEmpty()) {
            return ['maks' => null, 'min' => null, 'rata' => null, 'hari' => 0];
        }

        $jumlah = $harian->pluck('mhs')->map(fn ($n) => (int) $n);

        return [
            'maks' => (int) $jumlah->max(),
            'min' => (int) $jumlah->min(),
            'rata' => round((float) $jumlah->avg(), 1),
            'hari' => $harian->count(),
        ];
    }

    /**
     * Rata-rata persentase mahasiswa per kelas yang membuka materi.
     *
     * Kelas tanpa mahasiswa dilewati, bukan dihitung 0% — kelas kosong
     * bukan kelas yang seluruh mahasiswanya pasif.
     *
     * @param  list<array<string, mixed>>  $daftarKelas
     * @param  list<string>  $kodeMk
     * @param  array{awal: ?string, akhir: ?string, label: string}  $periode
     * @return array{persen: ?float, min: ?float, maks: ?float, jumlahKelas: int}
     */
    private function persenMateri(array $daftarKelas, array $kodeMk, array $periode): array
    {
        $pembuka = DB::table('log_aktivitas')
            ->distinct()
            ->whereIn('kode_mata_kuliah', $kodeMk)
            ->where('event_name', LogAktivitas::EVENT_MODUL_DILIHAT)
            ->whereIn('context_type', StatistikKeaktifan::KONTEKS_MATERI)
            ->where('actor_role', LogAktivitas::PERAN_MAHASISWA)
            ->when($periode['awal'], fn ($q) => $q->where('periode', '>=', $periode['awal']))
            ->when($periode['akhir'], fn ($q) => $q->where('periode', '<=', $periode['akhir']))
            ->pluck('actor_identifier')
            ->flip();

        $persen = [];

        foreach ($daftarKelas as $kelas) {
            $mahasiswa = collect($kelas['mahasiswa'] ?? [])->pluck('nim');

            if ($mahasiswa->isEmpty()) {
                continue;
            }

            $jumlah = $mahasiswa->filter(fn ($n) => isset($pembuka[$n]))->count();
            $persen[] = $jumlah / $mahasiswa->count() * 100;
        }

        if ($persen === []) {
            return ['persen' => null, 'min' => null, 'maks' => null, 'jumlahKelas' => 0];
        }

        return [
            'persen' => round(array_sum($persen) / count($persen), 1),
            'min' => round(min($persen), 1),
            'maks' => round(max($persen), 1),
            'jumlahKelas' => count($persen),
        ];
    }

    /**
     * Sebaran level risiko dari mahasiswa yang sedang ditampilkan.
     *
     * Mahasiswa yang belum dianalisis masuk "belum", BUKAN "aktif":
     * menampilkan aman untuk skor yang tidak pernah dihitung adalah
     * kesalahan yang sama berbahayanya dengan di tab Keaktifan.
     *
     * @param  list<array<string, mixed>>  $daftarKelas
     * @return array{aktif: int, sedang: int, atRisk: int, belum: int, total: int}
     */
    private function risiko(array $daftarKelas): array
    {
        $hasil = ['aktif' => 0, 'sedang' => 0, 'atRisk' => 0, 'belum' => 0, 'total' => 0];

        foreach ($daftarKelas as $kelas) {
            foreach ($kelas['mahasiswa'] ?? [] as $mhs) {
                $hasil['total']++;

                match ($mhs['level'] ?? null) {
                    'low' => $hasil['aktif']++,
                    'medium' => $hasil['sedang']++,
                    'high' => $hasil['atRisk']++,
                    default => $hasil['belum']++,
                };
            }
        }

        return $hasil;
    }

    /**
     * Daftar materi paling sering dan paling jarang diakses.
     *
     * Persentase dihitung dari total akses seluruh materi yang ikut
     * dihitung (bukan dari seluruh aktivitas), karena pertanyaannya
     * "bagian akses materi ini dari akses materi". Lebar bar dihitung
     * relatif terhadap materi teratas di daftarnya masing-masing supaya
     * daftar "paling jarang" tetap terbaca (kalau memakai skala yang sama,
     * bar materi jarang hanya selebar satu piksel).
     *
     * @param  list<string>  $kodeMk
     * @param  list<string>  $nim
     * @param  array{awal: ?string, akhir: ?string, label: string}  $periode
     * @return array{sering: list<array<string, mixed>>, jarang: list<array<string, mixed>>, jumlah: int, total: int}
     */
    private function materi(array $kodeMk, array $nim, array $periode): array
    {
        $baris = DB::table('log_aktivitas')
            ->select('context_name')
            ->selectRaw('COUNT(*) as akses')
            ->selectRaw('COUNT(DISTINCT actor_identifier) as mhs')
            ->whereIn('kode_mata_kuliah', $kodeMk)
            ->where('event_name', LogAktivitas::EVENT_MODUL_DILIHAT)
            ->whereIn('context_type', StatistikKeaktifan::KONTEKS_MATERI)
            ->where('actor_role', LogAktivitas::PERAN_MAHASISWA)
            ->whereIn('actor_identifier', $nim)
            ->whereNotNull('context_name')
            ->when($periode['awal'], fn ($q) => $q->where('periode', '>=', $periode['awal']))
            ->when($periode['akhir'], fn ($q) => $q->where('periode', '<=', $periode['akhir']))
            ->groupBy('context_name')
            ->get();

        $materi = $baris
            ->filter(fn ($b) => preg_match('/^(modul|materi)\b/i', trim((string) $b->context_name)) === 1)
            ->map(fn ($b) => [
                'nama' => trim((string) $b->context_name),
                'akses' => (int) $b->akses,
                'mhs' => (int) $b->mhs,
            ])
            ->sortBy([['akses', 'desc'], ['nama', 'asc']])
            ->values();

        $total = (int) $materi->sum('akses');

        if ($total === 0) {
            return ['sering' => [], 'jarang' => [], 'jumlah' => 0, 'total' => 0];
        }

        $sering = $materi->take(self::BATAS_MATERI);

        // Materi yang sudah muncul di daftar teratas tidak diulang di daftar
        // terbawah. Kalau materinya sedikit, daftar terbawah boleh kosong:
        // mengulang nama yang sama di dua kartu hanya membingungkan.
        $jarang = $materi
            ->reject(fn ($m) => $sering->contains('nama', $m['nama']))
            ->sortBy([['akses', 'asc'], ['nama', 'asc']])
            ->take(self::BATAS_MATERI)
            ->values();

        return [
            'sering' => $this->lengkapi($sering->values(), $total),
            'jarang' => $this->lengkapi($jarang, $total),
            'jumlah' => $materi->count(),
            'total' => $total,
        ];
    }

    /**
     * Tambahkan persentase dan lebar bar ke tiap materi.
     *
     * @param  Collection<int, array<string, mixed>>  $daftar
     * @return list<array<string, mixed>>
     */
    private function lengkapi(Collection $daftar, int $total): array
    {
        $teratas = max(1, (int) $daftar->max('akses'));

        return $daftar->map(fn ($m) => $m + [
            'persen' => round($m['akses'] / $total * 100, 1),
            'lebar' => (int) round($m['akses'] / $teratas * 100),
        ])->values()->all();
    }
}
