<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Kelas;
use App\Models\LogAktivitas;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesin statistik analitik (FR-11 s.d. FR-15).
 *
 * Dipakai bersama oleh dashboard Kaprodi (FR-21) dan dashboard Dosen
 * (FR-20). Kedua peran memang butuh angka yang sama — event, hari, tugas,
 * jenis event, kuis ditolak, engagement, klaster, risiko — yang berbeda
 * hanya cakupan kelasnya. Menulisnya dua kali berarti ada dua tempat yang
 * bisa tumbuh jadi berbeda, jadi perhitungannya tinggal di sini.
 *
 * SUMBER KEBENARANNYA DUA SAJA
 *
 *   1. Tabel `log_aktivitas` — jumlah baris, hari aktif, tugas, dan
 *      ragam jenis event.
 *   2. Layanan analytics Python (FR-12) — engagement, klaster, dan skor
 *      risiko. Kalau layanan mati (FR-12a), statistik mentah tetap tampil
 *      dan bagian hasil analitik ditandai tidak tersedia.
 *
 * KENAPA DIKUMPULKAN PER MATA KULIAH, BUKAN PER KELAS
 *
 * Awalnya satu mata kuliah cuma punya satu kelas, jadi tidak terasa. Setelah
 * kelas dipecah jadi delapan (A/B/C/D x Pagi/Malam), menghitung per kelas
 * menjadi mahal: semua kelas satu mata kuliah memakai `kode_mata_kuliah`
 * yang sama, sehingga agregasi SQL yang sama diulang sembilan kali atas
 * 90 ribu baris log, dan layanan Python dipanggil sembilan kali untuk
 * angkatan yang sama. Diukur, halaman Dosen naik jadi sekitar 9 detik.
 *
 * Sekarang kelas-kelas dikelompokkan per (mata kuliah, jendela analisis,
 * batch import). Agregasi SQL dan panggilan ke layanan analytics dilakukan
 * satu kali per kelompok; hasilnya lalu dikembalikan ke masing-masing kelas
 * sesuai NIM-nya. Angka per mahasiswa tidak berubah: metrik tetap sama,
 * dan Python memang memproses seluruh mahasiswa dalam satu panggilan
 * (klasterisasi dihitung atas seluruh isian, bukan per `kelas_id`).
 *
 * JENDELA ANALISIS
 *
 * Analisis selalu memakai satu semester penuh, bukan satu bulan. Ambang
 * engagement di layanan Python dikalibrasi dari aktivitas satu semester.
 * Kalau dikirim satu bulan saja, semua mahasiswa otomatis terbaca rendah
 * karena jumlahnya memang lebih kecil dari ambang — bukan karena mereka
 * pasif. Jendelanya diambil dari data kelas (tahun akademik + semester)
 * lalu dipotong dengan periode yang benar-benar ada di log.
 *
 * CAKUPAN
 *
 * Kelas yang dipakai SELALU sudah dibatasi pemanggil lewat
 * `Kelas::scopeUntukPengguna()` (FR-05). Kelas di sini tidak tahu dan
 * tidak peduli apakah ia dilihat Dosen atau Kaprodi.
 */
class StatistikKelas
{
    /** Nama bulan dalam bahasa Indonesia, untuk label. */
    private const NAMA_BULAN = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    public function __construct(private AnalyticsService $analytics) {}

    /**
     * Cache jendela analisis dalam satu permintaan.
     *
     * Kunci: mata kuliah + tahun akademik + semester. Semua kelas hasil
     * pecahan roster berbagi tiga nilai itu, jadi tanpa cache query
     * `GROUP BY periode` yang sama diulang delapan kali (diukur 1,8 detik).
     *
     * @var array<string, array{awal: string|null, akhir: string|null, label: string, perBulan: list<array>}>
     */
    private array $cacheJendela = [];

    /**
     * Statistik satu kelas.
     *
     * @return array<string, mixed>
     */
    public function untukKelas(Kelas $kelas): array
    {
        return $this->untukBanyak(collect([$kelas]))[0];
    }

    /**
     * Statistik beberapa kelas sekaligus, urut sama seperti masuknya.
     *
     * @param  Collection<int, Kelas>  $kelasList
     * @return list<array<string, mixed>>
     */
    public function untukBanyak(Collection $kelasList): array
    {
        if ($kelasList->isEmpty()) {
            return [];
        }

        $urutan = $kelasList->pluck('id')->all();
        $hasil = [];

        foreach ($this->kelompokkan($kelasList) as $grup) {
            $metrik = $this->metrikPerNim(
                $grup['kodeMk'],
                $grup['jendela']['awal'],
                $grup['jendela']['akhir'],
            );

            $siswa = $this->kumpulkanSiswa($grup['kelas']);

            $analisis = ['berhasil' => false, 'hasil' => [], 'peringatan' => null];

            if ($siswa !== [] && $grup['jendela']['awal'] !== null) {
                $analisis = $this->analisis(
                    $grup['kodeMk'],
                    $grup['batchId'],
                    $grup['kalibrasi'],
                    $grup['jendela'],
                    $metrik,
                    $siswa,
                );
            }

            foreach ($grup['kelas'] as $kelas) {
                $hasil[$kelas->id] = $this->susunKelas($kelas, $grup['jendela'], $metrik, $analisis);
            }
        }

        return array_map(fn (int $id) => $hasil[$id], $urutan);
    }

    /**
     * Kelompokkan kelas yang boleh dihitung bersama.
     *
     * Kuncinya (mata kuliah, jendela analisis, batch import): semua kelas
     * dalam satu kelompok membaca baris log yang sama dan memakai kalibrasi
     * yang sama, jadi agregasi dan panggilan layanannya cukup satu kali.
     *
     * @param  Collection<int, Kelas>  $kelasList
     * @return list<array{kodeMk: string, batchId: int, kalibrasi: ?array, jendela: array, kelas: list<Kelas>}>
     */
    private function kelompokkan(Collection $kelasList): array
    {
        $batchCache = [];
        $grup = [];

        foreach ($kelasList as $kelas) {
            $kodeMk = (string) $kelas->mataKuliah?->kode;
            $jendela = $this->jendelaAnalisis($kelas, $kodeMk);

            if (! array_key_exists($kodeMk, $batchCache)) {
                $batchCache[$kodeMk] = (int) (DB::table('log_aktivitas')
                    ->where('kode_mata_kuliah', $kodeMk)
                    ->min('import_batch_id') ?? 0);
            }

            $batchId = $batchCache[$kodeMk];
            $kunci = implode('|', [$kodeMk, $jendela['awal'], $jendela['akhir'], $batchId]);

            if (! isset($grup[$kunci])) {
                $grup[$kunci] = [
                    'kodeMk' => $kodeMk,
                    'batchId' => $batchId,
                    'kalibrasi' => $this->kalibrasiKelas($kodeMk),
                    'jendela' => $jendela,
                    'kelas' => [],
                ];
            }

            $grup[$kunci]['kelas'][] = $kelas;
        }

        return array_values($grup);
    }

    /**
     * Semua mahasiswa pada sekumpulan kelas.
     *
     * @param  list<Kelas>  $kelasList
     * @return list<array{nim: string, kelas_id: int, enrollment: Enrollment}>
     */
    private function kumpulkanSiswa(array $kelasList): array
    {
        $siswa = [];

        foreach ($kelasList as $kelas) {
            foreach ($kelas->mahasiswa()->orderBy('nama')->get() as $e) {
                $siswa[] = ['nim' => $e->nim, 'kelas_id' => $kelas->id, 'enrollment' => $e];
            }
        }

        return $siswa;
    }

    /**
     * Rakit blok statistik satu kelas dari metrik dan hasil analisis yang
     * sudah dihitung untuk kelompoknya.
     *
     * @param  array{awal: string|null, akhir: string|null, label: string, perBulan: list<array>}  $jendela
     * @param  array<string, object>  $metrik
     * @param  array{berhasil: bool, hasil: array<string, array>, peringatan: ?string}  $analisis
     * @return array<string, mixed>
     */
    private function susunKelas(Kelas $kelas, array $jendela, array $metrik, array $analisis): array
    {
        $terdaftar = $kelas->mahasiswa()->orderBy('nama')->get();

        // Peta nim diisi manual, BUKAN pluck('nim','nim'): PHP mengubah
        // kunci array berisi angka murni menjadi integer, maka pencarian
        // dengan kunci string "3312501013" akan gagal senyap.
        $nimTerdaftar = [];

        foreach ($terdaftar as $e) {
            $nimTerdaftar['_'.$e->nim] = true;
        }

        // Identitas yang berperan mahasiswa tapi TIDAK terdaftar di kelas.
        // Ini bukan galat import: ini sinyal bahwa roster belum lengkap.
        $diLuarDaftar = collect($metrik)
            ->keys()
            ->reject(fn ($kunci) => isset($nimTerdaftar[$kunci]))
            ->count();

        $data = [
            'id' => $kelas->id,
            // PENTING: `kode` dan `nama` di bawah milik MATA KULIAH, bukan
            // kelas. Data kelas ada di `kelasKode`/`kelasNama`. Kalau tidak
            // dipisah, semua blok kelas menampilkan judul yang sama dan
            // pengguna tidak pernah tahu sedang melihat kelas pagi atau malam.
            'kode' => $kelas->mataKuliah?->kode ?? '-',
            'nama' => $kelas->mataKuliah?->nama ?? 'Tanpa mata kuliah',
            'kelasKode' => $kelas->kode,
            'kelasNama' => $kelas->nama,
            'asal' => $kelas->asal_kelas,
            'jenis' => $kelas->jenis_kelas,
            'labelKelas' => $kelas->label(),
            'periode' => $jendela['label'],
            'periodeAwal' => $jendela['awal'],
            'periodeAkhir' => $jendela['akhir'],
            'perBulan' => $jendela['perBulan'],
            'terdaftar' => $terdaftar->count(),
            'terpantau' => 0,
            'diLuarDaftar' => $diLuarDaftar,
            'totalEvent' => 0,
            'rataEngagement' => null,
            'risikoTinggi' => 0,
            'klaster' => [],
            'mahasiswa' => [],
            'analisisAda' => false,
            'peringatan' => null,
        ];

        $baris = [];

        // Baris tabel dibangun dari DAFTAR KELAS, bukan dari kunci metrik:
        // mahasiswa terdaftar yang tidak punya satu baris log pun tetap
        // tampil dengan angka nol — itu justru data paling penting bagi EWS.
        foreach ($terdaftar as $e) {
            $m = $metrik['_'.$e->nim] ?? null;

            $baris[] = [
                'nim' => $e->nim,
                'nama' => $e->namaTampil(),
                'event' => $m->event ?? 0,
                'hari' => $m->hari ?? 0,
                'tugas' => $m->tugas ?? 0,
                'jenis' => $m->jenis ?? 0,
                'kuis' => $m->kuis ?? 0,
                'engagement' => null,
                'klaster' => null,
                'level' => null,
                'risk' => null,
            ];

            if ($m !== null) {
                $data['totalEvent'] += $m->event;

                if ($m->event > 0) {
                    $data['terpantau']++;
                }
            }
        }

        if ($analisis['berhasil']) {
            // Hasil analitik sengaja dihitung sekali untuk seluruh kelas
            // satu mata kuliah (lihat untukBanyak). Jadi di sini WAJIB
            // dipotong ke mahasiswa kelas ini dulu: kalau tidak, setiap
            // blok kelas akan melaporkan klaster dan risiko SELURUH mata
            // kuliah, dan jumlah "risiko tinggi" bisa melebihi jumlah
            // mahasiswa yang ada.
            $hasilKelas = array_intersect_key($analisis['hasil'], $nimTerdaftar);

            $this->tempelHasil($baris, $analisis['hasil']);
            $this->susunKlaster($hasilKelas, $data);
            $this->hitungRataRata($hasilKelas, $data);
        } elseif ($analisis['peringatan'] !== null) {
            $data['peringatan'] = $analisis['peringatan'];
        }

        // Urutan: paling berisiko dulu, lalu yang paling tidak aktif.
        usort($baris, function ($a, $b) {
            $risikoA = $a['risk'] ?? -1;
            $risikoB = $b['risk'] ?? -1;

            if ($risikoA !== $risikoB) {
                return $risikoB <=> $risikoA;
            }

            return $a['event'] <=> $b['event'];
        });

        $data['mahasiswa'] = $baris;

        return $data;
    }

    /**
     * Rekap beberapa kelas sekaligus.
     *
     * Rata-rata engagement dirata-ratakan dari angka per kelas, bukan dari
     * semua mahasiswa digabung. Alasannya: engagement dihitung di dalam satu
     * kelas (batas kalibrasi per mata kuliah), jadi mahasiswa satu kelas
     * tidak boleh ikut menentukan rata-rata kelas lain.
     *
     * @param  Collection<int, Kelas>  $kelasList
     * @param  list<array<string, mixed>>  $daftarKelas
     * @return array{layananAktif: bool, peringatan: ?string, ringkasan: array<string, mixed>}
     */
    public function ringkasan(Collection $kelasList, array $daftarKelas): array
    {
        $ringkasan = [
            'kelas' => $kelasList->count(),
            'mahasiswaUnik' => 0,
            'totalEvent' => 0,
            'risikoTinggi' => 0,
            'rataEngagement' => null,
        ];

        if ($daftarKelas === []) {
            return ['layananAktif' => true, 'peringatan' => null, 'ringkasan' => $ringkasan];
        }

        $peringatan = [];
        $rataRataSemua = [];

        foreach ($daftarKelas as $data) {
            $ringkasan['totalEvent'] += $data['totalEvent'];
            $ringkasan['risikoTinggi'] += $data['risikoTinggi'];

            if ($data['analisisAda'] && $data['rataEngagement'] !== null) {
                $rataRataSemua[] = $data['rataEngagement'];
            } elseif ($data['peringatan'] !== null) {
                $peringatan[] = $data['peringatan'];
            }
        }

        $ringkasan['mahasiswaUnik'] = (int) DB::table('kelas_mahasiswa')
            ->whereIn('kelas_id', $kelasList->pluck('id'))
            ->distinct()
            ->count('nim');

        $ringkasan['rataEngagement'] = $rataRataSemua === []
            ? null
            : round(array_sum($rataRataSemua) / count($rataRataSemua), 4);

        return [
            'layananAktif' => $peringatan === [],
            'peringatan' => $peringatan === []
                ? null
                : $peringatan[0].(count($peringatan) > 1
                    ? sprintf(' (+%d kelas lain gagal dianalisis.)', count($peringatan) - 1)
                    : ''),
            'ringkasan' => $ringkasan,
        ];
    }

    /**
     * "2026-05" menjadi "Mei 2026".
     */
    public static function labelPeriode(string $periode): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $periode, $m)) {
            return $periode;
        }

        return (self::NAMA_BULAN[(int) $m[2]] ?? $m[2]).' '.$m[1];
    }

    /**
     * Jendela analisis satu kelas: bulan-bulan semester kelas, dipotong
     * dengan periode yang benar-benar ada di log.
     *
     * Kalau kelas tidak punya tahun akademik/semester yang terbaca, atau
     * tidak ada satu pun periodenya yang cocok, dipakai seluruh rentang
     * data yang ada — dan ini disebut di label, bukan disamarkan.
     *
     * @return array{awal: string|null, akhir: string|null, label: string, perBulan: list<array{periode: string, label: string, event: int}>}
     */
    private function jendelaAnalisis(Kelas $kelas, string $kodeMk): array
    {
        $kunci = implode('|', [$kodeMk, $kelas->tahun_akademik, $kelas->semester]);

        if (isset($this->cacheJendela[$kunci])) {
            return $this->cacheJendela[$kunci];
        }

        return $this->cacheJendela[$kunci] = $this->hitungJendela($kelas, $kodeMk);
    }

    /**
     * @return array{awal: string|null, akhir: string|null, label: string, perBulan: list<array{periode: string, label: string, event: int}>}
     */
    private function hitungJendela(Kelas $kelas, string $kodeMk): array
    {
        $tersedia = DB::table('log_aktivitas')
            ->select('periode')
            ->selectRaw('COUNT(*) as event')
            ->where('kode_mata_kuliah', $kodeMk)
            ->whereNotNull('periode')
            ->groupBy('periode')
            ->orderBy('periode')
            ->get()
            ->keyBy('periode');

        if ($tersedia->isEmpty()) {
            return ['awal' => null, 'akhir' => null, 'label' => 'Belum ada data log', 'perBulan' => []];
        }

        // Semester kelas hanya dipakai kalau tahun akademiknya benar-benar
        // terbaca. Semester 2 tanpa tahun yang sah tidak bisa ditafsirkan,
        // jadi tidak boleh dipakai untuk memotong data.
        $bulanSemester = $this->bulanSemester($kelas);
        $asal = 'seluruh periode data';

        if ($bulanSemester !== []) {
            $terpakai = $tersedia->only(array_keys($bulanSemester));

            if ($terpakai->isNotEmpty()) {
                $asal = 'semester '.($kelas->semester === 1 ? 'ganjil' : 'genap')
                    .' '.$kelas->tahun_akademik;
            } else {
                $terpakai = $tersedia;
            }
        } else {
            $terpakai = $tersedia;
        }

        $awal = (string) $terpakai->keys()->first();
        $akhir = (string) $terpakai->keys()->last();

        $perBulan = [];

        foreach ($terpakai as $periode => $baris) {
            $perBulan[] = [
                'periode' => $periode,
                'label' => self::labelPeriode((string) $periode),
                'event' => (int) $baris->event,
            ];
        }

        return [
            'awal' => $awal,
            'akhir' => $akhir,
            'label' => self::labelPeriode($awal).' - '.self::labelPeriode($akhir).' ('.$asal.')',
            'perBulan' => $perBulan,
        ];
    }

    /**
     * Bulan-bulan dalam semester kelas, format YYYY-MM.
     *
     * Semester ganjil: Agustus s.d. Januari. Genap: Februari s.d. Juli.
     * Tahun akademiknya ditulis "2025/2026", jadi semester ganjil milik
     * tahun pertama dan semester genap milik paruh kedua.
     *
     * @return array<string, true>
     */
    private function bulanSemester(Kelas $kelas): array
    {
        $tahunAkademik = (string) $kelas->tahun_akademik;

        if (! preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', trim($tahunAkademik), $m)) {
            return [];
        }

        $awalTahun = (int) $m[1];

        $bulan = $kelas->semester === 1
            ? [[$awalTahun, 8], [$awalTahun, 9], [$awalTahun, 10], [$awalTahun, 11], [$awalTahun, 12], [$awalTahun + 1, 1]]
            : [[$awalTahun + 1, 2], [$awalTahun + 1, 3], [$awalTahun + 1, 4], [$awalTahun + 1, 5], [$awalTahun + 1, 6], [$awalTahun + 1, 7]];

        $hasil = [];

        foreach ($bulan as [$tahun, $ke]) {
            $hasil[sprintf('%d-%02d', $tahun, $ke)] = true;
        }

        return $hasil;
    }

    /**
     * Metrik agregat per NIM dalam rentang periode.
     *
     * Dipanggil satu kali per kelompok kelas, bukan per kelas. Semua kelas
     * satu mata kuliah membaca `kode_mata_kuliah` yang sama, jadi menghitung
     * ulang untuk tiap kelas hanya mengulang query yang sama atas 90 ribu
     * baris log.
     *
     * Kunci array diberi awalan '_' karena PHP mengubah kunci yang isinya
     * angka murni menjadi integer — tanpa awalan ini, pencocokan NIM bisa
     * gagal senyap (np. NIM "3312501013" jadi kunci integer).
     *
     * @return array<string, object{event: int, hari: int, tugas: int, jenis: int, kuis: int}>
     */
    private function metrikPerNim(string $kodeMk, ?string $awal, ?string $akhir): array
    {
        $query = DB::table('log_aktivitas')
            ->selectRaw('actor_identifier')
            ->selectRaw('COUNT(*) as event')
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as hari')
            ->selectRaw('SUM(CASE WHEN event_name = ? THEN 1 ELSE 0 END) as tugas', [LogAktivitas::EVENT_TUGAS_DIKUMPULKAN])
            ->selectRaw('COUNT(DISTINCT event_name) as jenis')
            ->selectRaw('SUM(CASE WHEN event_name LIKE ? THEN 1 ELSE 0 END) as kuis', [LogAktivitas::EVENT_KUIS_DITOLAK.'%'])
            ->where('kode_mata_kuliah', $kodeMk)
            ->where('actor_role', LogAktivitas::PERAN_MAHASISWA)
            ->whereNotNull('actor_identifier');

        if ($awal !== null && $akhir !== null) {
            $query->whereBetween('periode', [$awal, $akhir]);
        }

        $out = [];

        foreach ($query->groupBy('actor_identifier')->get() as $r) {
            $out['_'.$r->actor_identifier] = (object) [
                'event' => (int) $r->event,
                'hari' => (int) $r->hari,
                'tugas' => (int) $r->tugas,
                'jenis' => (int) $r->jenis,
                'kuis' => (int) $r->kuis,
            ];
        }

        return $out;
    }

    /**
     * Kirim metrik sekumpulan kelas ke layanan analytics (FR-11/13/14/15).
     *
     * Mahasiswa yang terdaftar TAPI tidak punya satu baris log pun di
     * jendela ini tetap dikirim dengan metrik nol — tidak beri aktivitas
     * adalah fakta yang paling penting untuk EWS, bukan celah data yang
     * boleh dibiarkan kosong.
     *
     * @param  array<string, float>|null  $kalibrasi
     * @param  array{awal: string, akhir: string, label: string}  $jendela
     * @param  array<string, object>  $metrik
     * @param  list<array{nim: string, kelas_id: int, enrollment: Enrollment}>  $siswa
     * @return array{berhasil: bool, hasil: array<string, array<string, mixed>>, peringatan: ?string}
     */
    private function analisis(
        string $kodeMk,
        int $batchId,
        ?array $kalibrasi,
        array $jendela,
        array $metrik,
        array $siswa
    ): array {
        $tabel = [];

        foreach ($siswa as $item) {
            // Kunci metrik sudah berawalan "_" untuk menghindari jebakan
            // kunci integer; pencariannya tinggal satu kunci.
            $m = $metrik['_'.$item['nim']] ?? null;

            $tabel[] = [
                'nim' => $item['nim'],
                'kelas_id' => $item['kelas_id'],
                'metrics' => [
                    'total_event' => $m->event ?? 0,
                    'hari_aktif' => $m->hari ?? 0,
                    'tugas_dikumpulkan' => $m->tugas ?? 0,
                    'jenis_event' => $m->jenis ?? 0,
                    'kuis_ditolak' => $m->kuis ?? 0,
                ],
            ];
        }

        try {
            // Kontrak v3.1 menerima rentang, supaya jendela analisis
            // semester utuh — bukan dipotong satu bulan.
            //
            // Ambang engagement dikirim per mata kuliah. Tanpa ini, kelas
            // yang aktivitasnya lebih rendah (IF207) terbaca pasif semua
            // karena dibandingkan dengan ambang IF209.
            $hasil = $this->analytics->analyze(
                $batchId,
                $jendela['awal'].'..'.$jendela['akhir'],
                $tabel,
                $kalibrasi === null ? [] : ['batas' => $kalibrasi],
            );

            $hasilMahasiswa = [];

            foreach ($hasil['results'] ?? [] as $r) {
                $hasilMahasiswa['_'.$r['nim']] = [
                    'engagement' => isset($r['engagement_rate']) ? (float) $r['engagement_rate'] : null,
                    'komponen' => $r['komponen'] ?? [],
                    'klaster' => $r['cluster_label'] ?? 'Klaster '.$r['cluster'],
                    'risk' => isset($r['risk_score']) ? (float) $r['risk_score'] : null,
                    'level' => $r['risk_level'] ?? null,
                ];
            }

            return ['berhasil' => true, 'hasil' => $hasilMahasiswa, 'peringatan' => null];
        } catch (ConnectionException|Response $e) {
            if (! $this->analytics->isUnavailable($e)) {
                return [
                    'berhasil' => false,
                    'hasil' => [],
                    'peringatan' => 'Layanan analytics menolak data: '.$e->getMessage(),
                ];
            }

            return [
                'berhasil' => false,
                'hasil' => [],
                'peringatan' => 'Layanan analytics tidak bisa dihubungi. Statistik mentah tetap tampil, kolom engagement/klaster/risiko dikosongkan (FR-12a).',
            ];
        }
    }

    /**
     * Ambang engagement khusus mata kuliah ini.
     *
     * Ambang diambil dari `config/services.php` bagian
     * analytics.kalibrasi. Kalau mata kuliahnya tidak disebut di sana,
     * null dikembalikan sehingga layanan memakai kalibrasi bawaannya
     * (dari IF209). Ambang yang benar-benar dipakai tetap dikembalikan
     * layanan di `meta.kalibrasi`, jadi angkanya selalu bisa ditelusuri.
     *
     * @return array<string, float>|null
     */
    private function kalibrasiKelas(string $kodeMk): ?array
    {
        $daftar = (array) config('services.analytics.kalibrasi', []);

        $batas = $daftar[$kodeMk] ?? null;

        if (! is_array($batas)) {
            return null;
        }

        return array_map('floatval', $batas);
    }

    /**
     * Tempel hasil analitik ke baris tabel per mahasiswa.
     *
     * Parameter array harus lewat referensi: kalau tidak, perubahan di
     * dalam fungsi ini tidak akan terlihat di pemanggilnya.
     *
     * @param  list<array<string, mixed>>  $baris
     * @param  array<string, array<string, mixed>>  $hasil
     */
    private function tempelHasil(array &$baris, array $hasil): void
    {
        foreach ($baris as $i => $b) {
            $h = $hasil['_'.$b['nim']] ?? null;

            $baris[$i]['engagement'] = $h['engagement'] ?? null;
            $baris[$i]['klaster'] = $h['klaster'] ?? null;
            $baris[$i]['risk'] = $h['risk'] ?? null;
            $baris[$i]['level'] = $h['level'] ?? null;
        }
    }

    /**
     * Susun hitungan distribusi klaster dari hasil analitik.
     *
     * @param  array<string, array<string, mixed>>  $hasil
     * @param  array<string, mixed>  $data
     */
    private function susunKlaster(array $hasil, array &$data): void
    {
        $hitung = [];

        foreach ($hasil as $h) {
            $label = $h['klaster'] ?? '-';
            $hitung[$label] = ($hitung[$label] ?? 0) + 1;
        }

        arsort($hitung);

        $data['klaster'] = [];

        foreach ($hitung as $label => $jumlah) {
            $data['klaster'][] = ['label' => $label, 'jumlah' => $jumlah];
        }
    }

    /**
     * Rata-rata engagement + jumlah risiko tinggi.
     *
     * @param  array<string, array<string, mixed>>  $hasil
     * @param  array<string, mixed>  $data
     */
    private function hitungRataRata(array $hasil, array &$data): void
    {
        $engagement = [];
        $tinggi = 0;

        foreach ($hasil as $h) {
            if ($h['engagement'] !== null) {
                $engagement[] = $h['engagement'];
            }

            if (($h['level'] ?? '') === 'high') {
                $tinggi++;
            }
        }

        $data['analisisAda'] = true;
        $data['rataEngagement'] = $engagement === []
            ? null
            : round(array_sum($engagement) / count($engagement), 4);
        $data['risikoTinggi'] = $tinggi;
    }
}
