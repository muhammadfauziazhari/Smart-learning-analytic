<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Detail keaktifan mahasiswa per orang (tab "Keaktifan").
 *
 * Kolom tabel diambil dari `context_type` pada log, bukan dari tebakan
 * nama kolom:
 *
 *   Akses Course = event bertipe konteks "Course"  (dibuka oleh mahasiswa:
 *                  "Course viewed" dan "Section viewed")
 *   Akses Materi = modul isi: "File", "URL", "Page", "Folder",
 *                  "Subsection", "Text and media area"
 *   Forum        = konteks "Forum"
 *   Submission   = "A submission has been submitted."
 *
 * Pada log nyata yang tersedia, hanya tiga dari enam konteks materi itu
 * yang muncul (File, Folder, URL); sisanya tetap ikut diletakkan karena
 * Moodle memang memakai konteks itu untuk modul isi, dan tidak seorang
 * pun yang melihat tabel ini bisa membedakan "tidak pernah diakses" dari
 * "modulnya tidak ada".
 *
 * CATATAN PENTING SOAL KOLOM FORUM: log yang diimpor tidak memuat event
 * POST forum sama sekali (dicek pada 18 Okt 2026: pencarian event berisi
 * "post"/"forum"/"discussion" mengembalikan nol baris). Angka Forum yang
 * kecil itu LIMITASI EKSPOR LOG, bukan mahasiswa yang malas berdiskusi,
 * jadi angkanya tidak boleh dipakai untuk menilai mahasiswa.
 *
 * Tepat waktu/terlambat TIDAK dihitung ulang di sini: angkanya diambil
 * dari RekapTugas, jadi tab Keaktifan dan tab Pengumpulan Tugas tidak
 * mungkin punya definisi tenggat yang berbeda.
 *
 * Engagement, klaster, dan risiko juga bukan dihitung di sini. Ketiganya
 * milik layanan analytics dan sudah dihitung untuk tab Dashboard; service
 * ini hanya menerimanya lewat `$petaAnalitik`. Status EWS diturunkan dari
 * level/skor risiko. Kalau layanan mati, hasil analitik tetap tidak tersedia.
 */
class StatistikKeaktifan
{
    /**
     * Konteks Moodle yang dihitung sebagai "akses materi".
     *
     * @var list<string>
     */
    public const KONTEKS_MATERI = ['File', 'URL', 'Page', 'Book', 'Folder', 'Subsection', 'Text and media area'];

    /**
     * Konteks halaman mata kuliah.
     */
    public const KONTEKS_COURSE = 'Course';

    /**
     * Konteks ruang diskusi.
     */
    public const KONTEKS_FORUM = 'Forum';

    public function __construct(private RekapTugas $rekapTugas) {}

    /**
     * @param  array{mata_kuliah?: ?string, jenis?: ?string, asal?: ?string, kelas_id?: ?int, q?: ?string, dari?: ?string, sampai?: ?string}  $filter
     * @param  array<string, array{engagement: ?float, klaster?: ?string, level: ?string, risk: ?float}>  $petaAnalitik  Data per mahasiswa dari
     *                                                                                                                  tab Dashboard (kunci berawalan "_"). Kalau kosong,
     *                                                                                                                  kolom analitik tampil "—".
     * @return array<string, mixed>
     */
    public function untuk(User $dosen, array $filter = [], array $petaAnalitik = []): array
    {
        $kelas = $this->rekapTugas->kelasTerpilih($dosen, $filter);
        $rentang = $this->rentang($filter, $kelas->first());
        $opsi = $this->rekapTugas->opsiFilter($kelas);

        $kosong = [
            'baris' => [],
            'ringkasan' => $this->ringkasanKosong(),
            'kelas' => $kelas->all(),
            'rentang' => $rentang,
            'filter' => $filter + $opsi,
            'kosong' => true,
        ];

        if ($kelas->isEmpty()) {
            return $kosong;
        }

        $mahasiswa = $this->rekapTugas->mahasiswaTerpilih($kelas, $filter['q'] ?? null);

        if ($mahasiswa === []) {
            return $kosong;
        }

        $nim = array_map(fn ($kunci) => substr((string) $kunci, 1), array_keys($mahasiswa));
        $kodeMk = $kelas->pluck('mataKuliah.kode')->filter()->unique()->values()->all();

        $aktivitas = $this->aktivitasPerNim($kodeMk, $nim, $rentang);

        // Pengumpulan per tugas (tepat waktu/terlambat) memakai service yang
        // sama dengan tab Pengumpulan Tugas, dengan rentang tanggal yang sama.
        $kirim = $this->rekapTugas->kirimPerMahasiswa($dosen, $filter + $rentang);

        $baris = [];
        $rekap = $this->ringkasanKosong();
        $daftarEngagement = [];

        foreach ($mahasiswa as $kunci => $data) {
            $a = $aktivitas[$kunci] ?? null;
            $k = $kirim[$kunci] ?? ['total' => 0, 'tepat' => 0, 'terlambat' => 0, 'tidak' => 0];
            $analitik = $petaAnalitik[$kunci] ?? null;

            $baris[] = [
                'nim' => substr((string) $kunci, 1),
                'nama' => $data['nama'],
                'kelas' => $data['kelas'],
                'course' => (int) ($a->course ?? 0),
                'materi' => (int) ($a->materi ?? 0),
                'forum' => (int) ($a->forum ?? 0),
                'submission' => (int) ($a->submission ?? 0),
                'hari' => (int) ($a->hari ?? 0),
                'terakhir' => $a->terakhir ?? null,
                'tepat' => $k['tepat'],
                'terlambat' => $k['terlambat'],
                'tidak' => $k['tidak'],
                'engagement' => $analitik['engagement'] ?? null,
                'klaster' => $analitik['klaster'] ?? null,
                'level' => $analitik['level'] ?? null,
                'risk' => $analitik['risk'] ?? null,
                'ews' => $this->statusEws($analitik),
            ];

            $rekap['mahasiswa']++;
            $rekap['submission'] += (int) ($a->submission ?? 0);
            $rekap['tepat'] += $k['tepat'];
            $rekap['terlambat'] += $k['terlambat'];
            $rekap['ews'][$this->statusEws($analitik)['kode']]++;

            if (($analitik['engagement'] ?? null) !== null) {
                $daftarEngagement[] = (float) $analitik['engagement'];
            }
        }

        // Urutan: yang paling berisiko dulu, lalu yang paling jarang aktif.
        usort($baris, function ($a, $b) {
            $risikoA = $a['risk'] ?? -1;
            $risikoB = $b['risk'] ?? -1;

            if ($risikoA !== $risikoB) {
                return $risikoB <=> $risikoA;
            }

            return ($a['course'] + $a['materi'] + $a['forum'])
                <=> ($b['course'] + $b['materi'] + $b['forum']);
        });

        $rekap['rataEngagement'] = $daftarEngagement === []
            ? null
            : array_sum($daftarEngagement) / count($daftarEngagement);

        return [
            'baris' => $baris,
            'ringkasan' => $rekap,
            'kelas' => $kelas->all(),
            'rentang' => $rentang,
            'filter' => $filter + $opsi,
            'kosong' => false,
        ];
    }

    // --------------------------------------------------------------- rentang

    /**
     * Rentang tanggal yang dipakai, beserta asalnya.
     *
     * Default adalah bulan semester kelas (Februari s.d. Juli untuk genap,
     * Agustus s.d. Januari untuk ganjil) — keputusan pengguna 18 Okt 2026:
     * angka Keaktifan harus bisa dibandingkan dengan tab lain yang memakai
     * jendela semester penuh.
     *
     * Tanggal yang diketik pengguna wins, asal formatnya benar. Bila
     * tanggal awal lebih besar dari tanggal akhir, keduanya ditukar;
     * membiarkan rentang terbalik akan menghapus semua baris tanpa
     * penjelasan.
     *
     * @param  array<string, mixed>  $filter
     * @return array{dari: string, sampai: string, label: string, bawaan: bool}
     */
    private function rentang(array $filter, ?Kelas $kelas): array
    {
        [$dariBawaan, $sampaiBawaan] = $this->bulanSemester($kelas);

        $dariPakai = $this->tanggalValid($filter['dari'] ?? null);
        $sampaiPakai = $this->tanggalValid($filter['sampai'] ?? null);

        $dari = $dariPakai ?? $dariBawaan;
        $sampai = $sampaiPakai ?? $sampaiBawaan;

        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'label' => date('d/m/Y', strtotime($dari)).' - '.date('d/m/Y', strtotime($sampai)),
            // "Bawaan" berarti rentang yang terpakai memang bawaan semester,
            // bukan sekadar form-nya kosong: tanggal ngawur di URL juga
            // berakhir di rentang bawaan, dan tombol reset tidak perlu
            // muncul saat memang tidak ada yang perlu direset.
            'bawaan' => $dariPakai === null && $sampaiPakai === null,
        ];
    }

    /**
     * Tanggal pertama dan terakhir bulan semester kelas.
     *
     * Tahun akademiknya ditulis "2025/2026": semester 1 milik paruh
     * pertama, semester 2 milik paruh kedua. Kelas tanpa tahun akademik
     * yang terbaca tidak bisa ditafsirkan, jadi jendela satu tahun penuh
     * dipakai sebagai gantinya — lebih baik menampilkan terlalu banyak
     * daripada menampilkan nol tanpa alasan.
     *
     * @return array{0: string, 1: string}
     */
    private function bulanSemester(?Kelas $kelas): array
    {
        $kelas ??= new Kelas;

        $tahunAkademik = (string) $kelas->tahun_akademik;
        $tahun = preg_match('/^(\d{4})/', trim($tahunAkademik), $m) === 1 ? (int) $m[1] : (int) date('Y');

        [$tahunAwal, $bulanAwal, $tahunAkhir, $bulanAkhir] = $kelas->semester === 1
            ? [$tahun, 8, $tahun + 1, 1]
            : [$tahun + 1, 2, $tahun + 1, 7];

        return [
            sprintf('%04d-%02d-01', $tahunAwal, $bulanAwal),
            date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $tahunAkhir, $bulanAkhir))),
        ];
    }

    private function tanggalValid(mixed $tanggal): ?string
    {
        $tanggal = is_string($tanggal) ? trim($tanggal) : '';

        if ($tanggal === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return null;
        }

        return date('Y-m-d', strtotime($tanggal)) === $tanggal ? $tanggal : null;
    }

    // ------------------------------------------------------------ aktivitas

    /**
     * Angka aktivitas per mahasiswa dalam rentang yang dipilih.
     *
     * Satu query untuk seluruh mahasiswa: menghitung per mahasiswa akan
     * mengulang pemindaian atas 130 ribu baris log sebanyak 245 kali.
     *
     * @param  list<string>  $kodeMk
     * @param  list<string>  $nim
     * @param  array{dari: string, sampai: string, label: string, bawaan: bool}  $rentang
     * @return array<string, object>
     */
    private function aktivitasPerNim(array $kodeMk, array $nim, array $rentang): array
    {
        if ($nim === []) {
            return [];
        }

        return DB::table('log_aktivitas')
            ->select('actor_identifier')
            ->selectRaw('SUM(CASE WHEN context_type = ? THEN 1 ELSE 0 END) as course', [self::KONTEKS_COURSE])
            ->selectRaw('SUM(CASE WHEN context_type IN ('.
                implode(',', array_fill(0, count(self::KONTEKS_MATERI), '?')).') THEN 1 ELSE 0 END) as materi',
                self::KONTEKS_MATERI)
            ->selectRaw('SUM(CASE WHEN context_type = ? THEN 1 ELSE 0 END) as forum', [self::KONTEKS_FORUM])
            ->selectRaw('SUM(CASE WHEN event_name = ? THEN 1 ELSE 0 END) as submission', [LogAktivitas::EVENT_TUGAS_DIKUMPULKAN])
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as hari')
            ->selectRaw('MAX(occurred_at) as terakhir')
            ->whereIn('kode_mata_kuliah', $kodeMk)
            ->where('actor_role', LogAktivitas::PERAN_MAHASISWA)
            ->whereNotNull('actor_identifier')
            ->whereIn('actor_identifier', $nim)
            ->where('occurred_at', '>=', $rentang['dari'].' 00:00:00')
            ->where('occurred_at', '<=', $rentang['sampai'].' 23:59:59')
            ->groupBy('actor_identifier')
            ->get()
            ->keyBy(fn ($b) => '_'.$b->actor_identifier)
            ->all();
    }

    // ------------------------------------------------------------------- EWS

    /**
     * Status EWS sementara, dari level risiko yang sudah dihitung layanan
     * analytics (lihat config/services.php bagian `ews`).
     *
     * Kalau levelnya belum ada tapi skornya ada, ambang di config yang
     * dipakai. Kalau keduanya belum ada, status-nya "belum" — bukan
     * "aman". Menampilkan aman untuk mahasiswa yang belum pernah dianalisis
     * adalah kesalahan yang paling berbahaya di halaman ini.
     *
     * @param  array{engagement: ?float, level: ?string, risk: ?float}|null  $analitik
     * @return array{kode: string, label: string, warna: string}
     */
    private function statusEws(?array $analitik): array
    {
        $level = $analitik['level'] ?? null;

        if ($level === null && ($analitik['risk'] ?? null) !== null) {
            $level = $this->levelDariSkor((float) $analitik['risk']);
        }

        if ($level === null) {
            return ['kode' => 'belum', 'label' => 'Belum dihitung', 'warna' => 'abu'];
        }

        $label = config("services.ews.label.$level");

        return [
            'kode' => $level,
            'label' => is_string($label) ? $label : ucfirst((string) $level),
            'warna' => match ($level) {
                'high' => 'merah',
                'medium' => 'kuning',
                default => 'hijau',
            },
        ];
    }

    private function levelDariSkor(float $skor): ?string
    {
        $atRisk = (float) config('services.ews.ambang.at_risk', 0.6);
        $perhatian = (float) config('services.ews.ambang.perhatian', 0.35);

        if ($skor >= $atRisk) {
            return 'high';
        }

        if ($skor >= $perhatian) {
            return 'medium';
        }

        return 'low';
    }

    // ------------------------------------------------------------- ringkasan

    /**
     * @return array{mahasiswa: int, submission: int, tepat: int, terlambat: int, rataEngagement: ?float, ews: array<string, int>}
     */
    private function ringkasanKosong(): array
    {
        return [
            'mahasiswa' => 0,
            'submission' => 0,
            'tepat' => 0,
            'terlambat' => 0,
            'rataEngagement' => null,
            'ews' => ['low' => 0, 'medium' => 0, 'high' => 0, 'belum' => 0],
        ];
    }
}
