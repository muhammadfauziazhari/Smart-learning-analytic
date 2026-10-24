<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap pengumpulan tugas per minggu (tab "Pengumpulan Tugas").
 *
 * INI DATANYA BISA DITURUNKAN LANGSUNG DARI LOG, tanpa data tambahan:
 *
 *   1. Tanggal tugas dibuat ada di event `Course module created` dengan
 *      `context_type` = "Assignment". Nama konteksnya PERSIS sama dengan
 *      nama tugas saat mahasiswa mengumpulkan, jadi keduanya bisa
 *      dipasangkan. Dari 111 tugas IF209, 111 punya catatan pembuatan —
 *      tidak ada tugas yang kehilangan tanggal dibuat.
 *
 *   2. Tenggat = tanggal dibuat + 7 hari (keputusan pengguna,
 *      18 Okt 2026). Diuji pada data nyata: "Pengumpulan Praktikum 7
 *      IF 2A Pagi" dibuat 22 Apr 2026, tenggat 29 Apr, dan 43 mahasiswa
 *      mengumpulkan sejak 22 Apr.
 *
 *   3. Minggu pada kolom diambil dari NOMOR DI NAMA TUGAS (keputusan
 *      pengguna, 21 Okt 2026: satu minggu satu tugas praktikum). "Praktikum
 *      7" jatuh di kolom Minggu 7, "Minggu 13" di kolom Minggu 13 — meski
 *      tugasnya di Moodle dibuat jauh dari minggu itu. Tugas yang namanya
 *      TIDAK memuat nomor (ATS, AAS, dan sebagian tugas IF207) tetap tampil,
 *      diletakkan di kolom minggu semester sesuai tanggal tugasnya dibuat.
 *      Minggu tanpa tugas (mis. "Praktikum 4" yang tidak pernah ada di log)
 *      tidak dibuat-buat: kolomnya tetap ada, tapi warnanya abu-abu.
 *
 * WARNA SEL:
 *
 *   hijau  = mengumpulkan tugas yang ada minggu itu, tepat waktu
 *   kuning = mengumpulkan, tapi setelah tenggat
 *   merah  = ada tugas minggu itu, tidak mengumpulkan sama sekali
 *   abu    = tidak ada tugas yang jatuh tempo minggu itu
 *
 * TUGAS YANG TIDAK DITAMPILKAN: nama tugas yang tidak menyebut kelas yang
 * sedang disaring (mis. "Batamindo", "Upload Sertifikat", "AAS").
 * Keputusan pengguna, 18 Okt 2026: hanya tugas milik kelas terpilih.
 */
class RekapTugas
{
    /** Batas pengumpulan: hari setelah tugas dibuat. */
    public const HARI_TENGGAT = 7;

    /**
     * Berapa mahasiswa yang ditampilkan matriks sekaligus.
     *
     * Tanpa batas ini, satu halaman dashboard Dosen memuat matriks untuk
     * seluruh kelas yang boleh ia lihat: diuji pada data nyata, 245
     * mahasiswa x 22 minggu = 5.390 sel, dan HTML halaman naik jadi 5,7 MB
     * sehingga halaman terasa berat walaupun datanya hanya satu semester.
     *
     * Yang ditampilkan adalah yang paling perlu diperhatikan dulu, jadi
     * yang dipotong justru bagian paling akhir. Halaman yang menampilkan
     * SEMUA mahasiswa ada di tab Keaktifan (satu baris satu mahasiswa, jauh
     * lebih ringan), dan menyaring per kelas atau memakai pencarian selalu
     * membuka sisanya.
     */
    public const BATAS_MAHASISWA = 100;

    /** Status satu sel. */
    public const TEPAT_WAKTU = 'tepat';

    public const TERLAMBAT = 'terlambat';

    public const TIDAK_MENGUMPULKAN = 'tidak';

    public const TANPA_TUGAS = 'tidak_ada_tugas';

    /**
     * Matriks mahasiswa x minggu untuk tab "Pengumpulan Tugas".
     *
     * @param  array{mata_kuliah?: ?string, jenis?: ?string, asal?: ?string, kelas_id?: ?int, q?: ?string, dari?: ?string, sampai?: ?string}  $filter
     * @return array<string, mixed>
     */
    public function untuk(User $pengguna, array $filter = []): array
    {
        $dasar = $this->dataDasar($pengguna, $filter);

        if ($dasar === null) {
            // Kelas tetap dikembalikan walau tidak ada mahasiswanya: form
            // filter harus tetap bisa menampilkan kelas yang boleh dibuka,
            // dan pesan "kosing" jadi bisa menyebut apa yang sebenarnya
            // terjadi.
            $kelasKosong = $this->kelasTerpilih($pengguna, $filter);

            return [
                'minggu' => [],
                'baris' => [],
                'jumlahMahasiswa' => 0,
                'ringkasan' => ['tugas' => 0, 'tepat' => 0, 'terlambat' => 0, 'tidak' => 0, 'sel' => 0],
                'kelas' => $kelasKosong->all(),
                'filter' => $filter + $this->opsiFilter($kelasKosong),
                'kosong' => true,
            ];
        }

        $kelas = $dasar['kelas'];
        $tugas = $dasar['tugas'];
        $kirim = $dasar['kirim'];

        // Kelompokkan tugas per minggu kolom, lalu LENGKAPI minggu yang kosong
        // di antaranya. Minggu tanpa tugas tetap ditampilkan sebagai kolom
        // abu-abu, supaya dosen melihat kontinuitas minggu, bukan
        // lompatan dari minggu 9 ke minggu 14.
        $perMinggu = [];
        foreach ($tugas as $t) {
            $perMinggu[$t['minggu']][] = $t;
        }

        $urutanMinggu = $perMinggu === []
            ? []
            : range(min(array_keys($perMinggu)), max(array_keys($perMinggu)));

        foreach ($urutanMinggu as $nomor) {
            $perMinggu[$nomor] ??= [];
        }
        ksort($perMinggu);

        $baris = [];
        $rekap = ['tugas' => count($tugas), 'tepat' => 0, 'terlambat' => 0, 'tidak' => 0, 'sel' => 0];

        foreach ($dasar['mahasiswa'] as $kunci => $dataMhs) {
            $sel = [];

            foreach ($perMinggu as $nomorMinggu => $daftarTugas) {
                $hasil = $this->statusMahasiswa($kunci, $daftarTugas, $kirim);

                // Minggu tanpa tugas dihitung abu-abu, bukan "tidak
                // mengumpulkan" — tidak ada apa-apa yang terlewat di sana.
                if ($hasil['status'] !== self::TANPA_TUGAS) {
                    $rekap[$hasil['status']]++;
                    $rekap['sel']++;
                }

                $sel[$nomorMinggu] = $hasil;
            }

            $baris[] = [
                // Kunci peta berawalan "_"; NIM yang ditampilkan adalah aslinya.
                'nim' => substr((string) $kunci, 1),
                'nama' => $dataMhs['nama'],
                'kelas' => $dataMhs['kelas'],
                'sel' => $sel,
                'tepat' => count(array_filter($sel, fn ($s) => $s['status'] === self::TEPAT_WAKTU)),
                'terlambat' => count(array_filter($sel, fn ($s) => $s['status'] === self::TERLAMBAT)),
                'tidak' => count(array_filter($sel, fn ($s) => $s['status'] === self::TIDAK_MENGUMPULKAN)),
            ];
        }

        // Urutan: yang paling banyak menunggak dulu.
        usort($baris, fn ($a, $b) => [$b['tidak'], $b['terlambat']] <=> [$a['tidak'], $a['terlambat']]);

        $minggu = [];
        foreach (array_keys($perMinggu) as $nomor) {
            $minggu[] = [
                'minggu' => $nomor,
                'label' => 'Minggu '.$nomor,
                'mulai' => $this->seninMinggu($nomor, $dasar['mingguAwal']),
                'tugas' => count($perMinggu[$nomor]),
            ];
        }

        return [
            'minggu' => $minggu,
            // Yang dipotong bukan data, hanya baris yang ditampilkan.
            'baris' => array_slice($baris, 0, self::BATAS_MAHASISWA),
            'jumlahMahasiswa' => count($baris),
            'ringkasan' => $rekap,
            'kelas' => $kelas->all(),
            'filter' => $filter + $this->opsiFilter($kelas),
            'kosong' => false,
        ];
    }

    /**
     * Jumlah tugas yang dikumpulkan tiap mahasiswa, tanpa matriks minggu.
     *
     * Dipakai tab Keaktifan. Sengaja tidak memakai `untuk()`: matriks
     * dibutuhkan di sana hanya sebagai turunan, sementara membangunnya
     * untuk 245 mahasiswa adalah pekerjaan yang tidak perlu.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, array{total: int, tepat: int, terlambat: int, tidak: int}> Kunci berawalan "_" seperti peta lain di service ini.
     */
    public function kirimPerMahasiswa(User $pengguna, array $filter = []): array
    {
        $dasar = $this->dataDasar($pengguna, $filter);

        if ($dasar === null) {
            return [];
        }

        $hasil = [];

        foreach (array_keys($dasar['mahasiswa']) as $kunci) {
            $hasil[$kunci] = $this->rekapKirim($kunci, $dasar['tugas'], $dasar['kirim']);
        }

        return $hasil;
    }

    /**
     * Data dasar yang dipakai `untuk()` dan `kirimPerMahasiswa()`.
     *
     * Satu titikkannya penting: daftar tugas, batas pengumpulan, dan aturan
     * tenggat hanya boleh dikumpulkan di satu tempat. Kalau dua tab
     * menghitung sendiri-sendiri, pasti ada saat angkanya mulai berbeda.
     *
     * @param  array<string, mixed>  $filter
     * @return array{kelas: Collection<int, Kelas>, mahasiswa: array<string, array{nama: string, kelas: string}>, tugas: list<array<string, mixed>>, kirim: array<string, array<string, string>>, mingguAwal: string}|null
     */
    private function dataDasar(User $pengguna, array $filter): ?array
    {
        $kelas = $this->kelasTerpilih($pengguna, $filter);

        if ($kelas->isEmpty()) {
            return null;
        }

        $mahasiswa = $this->mahasiswaTerpilih($kelas, $filter['q'] ?? null);

        if ($mahasiswa === []) {
            return null;
        }

        $kodeMk = $kelas->pluck('mataKuliah.kode')->filter()->unique()->values()->all();
        $mingguAwal = $this->mingguAwalSemester($kelas->first());

        $tugas = $this->kumpulkanTugas($kodeMk, $kelas, $mingguAwal);

        // Kunci peta mahasiswa berawalan "_" (jebakan kunci integer PHP), jadi
        // NIM asli harus dibuang lagi sebelum dikirim ke query.
        $kirim = $this->pengumpulan(
            $kodeMk,
            array_map(fn ($kunci) => substr((string) $kunci, 1), array_keys($mahasiswa)),
            $tugas,
            $this->rentang($filter),
        );

        return compact('kelas', 'mahasiswa', 'tugas', 'kirim', 'mingguAwal');
    }

    // -------------------------------------------------------------- tanggal

    /**
     * Rentang tanggal yang diminta pengguna, sudah dibersihkan.
     *
     * Tanggal tidak sah dibuang, bukan diteruskan ke query. Tanpa ini
     * `?dari=ngawur` akan membuat semua baris hilang tanpa penjelasan.
     *
     * @param  array<string, mixed>  $filter
     * @return array{dari: ?string, sampai: ?string}
     */
    private function rentang(array $filter): array
    {
        return [
            'dari' => $this->tanggalValid($filter['dari'] ?? null),
            'sampai' => $this->tanggalValid($filter['sampai'] ?? null),
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

    /**
     * Jumlah tugas yang benar-benar dikumpulkan mahasiswa ini, dipecah
     * tepat waktu / terlambat / tidak sama sekali.
     *
     * Perhitungannya satu per tugas, memakai aturan tenggat yang sama
     * dengan matriks minggu, supaya dua tab tidak pernah memberi angka
     * berbeda untuk tugas yang sama.
     *
     * @param  string  $kunciNim  Kunci peta mahasiswa (berawalan "_")
     * @param  list<array<string, mixed>>  $tugas
     * @param  array<string, array<string, string>>  $kirim
     * @return array{total: int, tepat: int, terlambat: int, tidak: int}
     */
    private function rekapKirim(string $kunciNim, array $tugas, array $kirim): array
    {
        $hasil = ['total' => 0, 'tepat' => 0, 'terlambat' => 0, 'tidak' => 0];

        foreach ($tugas as $tugasSatu) {
            $status = $this->statusMahasiswa($kunciNim, [$tugasSatu], $kirim)['status'];

            if ($status === self::TANPA_TUGAS) {
                continue;
            }

            $hasil[$status]++;

            if ($status !== self::TIDAK_MENGUMPULKAN) {
                $hasil['total']++;
            }
        }

        return $hasil;
    }

    // --------------------------------------------------------------- kelas

    /**
     * Kelas milik pengguna ini setelah filter.
     *
     * Dipakai juga oleh StatistikKeaktifan supaya penyaring kelas di tab
     * Pengumpulan Tugas dan tab Keaktifan benar-benar sama: kalau dua tab
     * menyaring beda, perbandingan antar keduanya selalu kabur.
     *
     * @param  array<string, mixed>  $filter
     * @return Collection<int, Kelas>
     */
    public function kelasTerpilih(User $pengguna, array $filter): Collection
    {
        return Kelas::query()
            ->untukPengguna($pengguna)
            ->with('mataKuliah')
            ->when(filled($filter['mata_kuliah'] ?? null), fn ($q) => $q
                ->whereHas('mataKuliah', fn ($m) => $m->where('kode', $filter['mata_kuliah'])))
            ->when(filled($filter['jenis'] ?? null), fn ($q) => $q->where('jenis_kelas', $filter['jenis']))
            ->when(filled($filter['asal'] ?? null), fn ($q) => $q->where('asal_kelas', $filter['asal']))
            ->when(filled($filter['kelas_id'] ?? null), fn ($q) => $q->where('id', $filter['kelas_id']))
            ->orderBy('mata_kuliah_id')
            ->orderBy('kode')
            ->get();
    }

    /**
     * Opsi dropdown, diambil dari kelas milik pengguna ini saja.
     *
     * @param  Collection<int, Kelas>  $kelas
     * @return array<string, mixed>
     */
    public function opsiFilter(Collection $kelas): array
    {
        return [
            'pilihanMataKuliah' => $kelas->pluck('mataKuliah')->filter()->unique('kode')->sortBy('kode')
                ->map(fn ($mk) => ['kode' => $mk->kode, 'nama' => $mk->nama])->values()->all(),
            'pilihanJenis' => $kelas->pluck('jenis_kelas')->filter()->unique()->sort()->values()->all(),
            'pilihanAsal' => $kelas->pluck('asal_kelas')->filter()->unique()->sort()->values()->all(),
            'pilihanKelas' => $kelas->map(fn (Kelas $k) => [
                'id' => $k->id,
                'label' => $k->label(),
                'jenis' => $k->jenis_kelas,
                'asal' => $k->asal_kelas,
            ])->values()->all(),
        ];
    }

    /**
     * Mahasiswa pada kelas terpilih, dikunci NIM-nya.
     *
     * Peta dikembalikan dengan awalan '_' karena PHP mengubah kunci array
     * berisi angka murni menjadi integer; pencarian dengan kunci string
     * "3312501013" akan gagal senyap.
     *
     * Mahasiswa yang tercatat di lebih dari satu kelas tetap satu baris,
     * dan nama kelasnya digabung. Menyembunyikan salah satu kelas akan
     * membuat tabel Keaktifan terlihat seperti dia hanya Diane di satu
     * tempat.
     *
     * Yang dipakai adalah nama kelas yang ringkas, bukan label lengkap
     * ("Jaringan Komputer (IF209) - Kelas A Pagi"): di dalam tabel, nama
     * mata kuliah sudah tercermin di filter dan kolom kode MK.
     *
     * @param  Collection<int, Kelas>  $kelas
     * @return array<string, array{nama: string, kelas: string}>
     */
    public function mahasiswaTerpilih(Collection $kelas, ?string $cari): array
    {
        $cari = trim((string) $cari);

        $baris = DB::table('kelas_mahasiswa')
            ->whereIn('kelas_id', $kelas->pluck('id'))
            ->get(['kelas_id', 'nim', 'nama']);

        $labelKelas = $kelas->mapWithKeys(fn (Kelas $k) => [$k->id => $k->nama]);

        $hasil = [];

        foreach ($baris as $b) {
            if ($cari !== ''
                && ! str_contains((string) $b->nama, $cari)
                && ! str_contains((string) $b->nim, $cari)) {
                continue;
            }

            $kunci = '_'.$b->nim;

            $hasil[$kunci] ??= [
                'nama' => (string) ($b->nama ?: $b->nim),
                'labelKelas' => [],
            ];

            if (($label = $labelKelas->get($b->kelas_id)) !== null) {
                $hasil[$kunci]['labelKelas'][$b->kelas_id] = $label;
            }
        }

        foreach ($hasil as &$data) {
            $data['kelas'] = implode(', ', $data['labelKelas']);
            unset($data['labelKelas']);
        }

        ksort($hasil);

        return $hasil;
    }

    // --------------------------------------------------------------- tugas

    /**
     * Daftar tugas milik kelas terpilih, beserta tenggatnya.
     *
     * @param  list<string>  $kodeMk
     * @param  Collection<int, Kelas>  $kelas
     * @return list<array{nama: string, dibuat: string, tenggat: string, minggu: int, kelas: string}>
     */
    private function kumpulkanTugas(array $kodeMk, Collection $kelas, string $mingguAwal): array
    {
        $dibuat = DB::table('log_aktivitas')
            ->select('context_name')
            ->selectRaw('MIN(occurred_at) as dibuat')
            ->where('event_name', LogAktivitas::EVENT_TUGAS_DIBUAT)
            ->where('context_type', LogAktivitas::KONTEKS_TUGAS)
            ->whereIn('kode_mata_kuliah', $kodeMk)
            ->whereNotNull('context_name')
            ->groupBy('context_name')
            ->get()
            ->keyBy('context_name');

        if ($dibuat->isEmpty()) {
            return [];
        }

        $polaKelas = $kelas
            ->map(fn (Kelas $k) => $this->kunciKelas($k))
            ->filter()
            ->unique()
            ->values();

        $hasil = [];

        foreach ($dibuat as $nama => $baris) {
            $kelasPemilik = $this->kelasPemilik((string) $nama, $polaKelas);

            if ($kelasPemilik === null) {
                continue;
            }

            $tanggalDibuat = (string) $baris->dibuat;
            $tenggat = $this->tambahHari($tanggalDibuat, self::HARI_TENGGAT);

            // Kolom mengikuti nomor di nama ("Praktikum 7" = Minggu 7).
            // Tugas tanpa nomor (ATS, AAS, dan sebagian tugas IF207) tetap
            // tampil, diletakkan di minggu semester saat tugasnya dibuat.
            $hasil[] = [
                'nama' => (string) $nama,
                'dibuat' => $tanggalDibuat,
                'tenggat' => $tenggat,
                'minggu' => $this->nomorDariNama((string) $nama)
                    ?? $this->nomorMinggu($tanggalDibuat, $mingguAwal),
                'kelas' => $kelasPemilik,
            ];
        }

        usort($hasil, fn ($a, $b) => $a['minggu'] <=> $b['minggu'] ?: strcmp($a['nama'], $b['nama']));

        return $hasil;
    }

    /**
     * Tugas ini milik kelas mana dari yang sedang disaring?
     *
     * Nama tugas di Moodle menyebut kelasnya ("Pengumpulan Praktikum 7
     * IF 2A Pagi"), dan Polanya sedikit berbeda-beda: ada yang "2A Pagi",
     * ada yang "Kelas A Pagi", ada yang "Kelas - IF - Batamindo". Karena itu
     * perbandingan dilakukan pada teks yang hanya berisi huruf dan angka
     * (huruf kecil): "2A Pagi" dan "Kelas A Pagi" sama-sama menjadi
     * "apagi".
     *
     * @param  list<string>  $polaKelas
     */
    private function kelasPemilik(string $namaTugas, Collection $polaKelas): ?string
    {
        $samar = $this->kunciKelas(null, $namaTugas);

        foreach ($polaKelas as $kunci) {
            if (str_contains($samar, $kunci)) {
                return $kunci;
            }
        }

        return null;
    }

    /** Kunci pembanding kelas, mis. "A Pagi" menjadi "apagi". */
    private function kunciKelas(?Kelas $kelas, ?string $teks = null): string
    {
        $asal = $kelas?->asal_kelas;
        $jenis = $kelas?->jenis_kelas;

        if ($kelas === null) {
            // Mode teks bebas: ambil potongan yang mengandung kata kelas.
            return $this->hurufAngka((string) $teks);
        }

        if ($asal === null || $jenis === null) {
            return '';
        }

        return $this->hurufAngka($asal.' '.$jenis);
    }

    private function hurufAngka(string $teks): string
    {
        return mb_strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $teks));
    }

    // --------------------------------------------------------------- nomor
    //
    /**
     * Nomor minggu yang tertulis di nama tugas, atau null kalau tidak ada.
     *
     * Aturan membacanya konservatif, supaya angka yang BUKAN nomor minggu
     * tidak ikut kebawa:
     *
     *   - "Pengumpulan Praktikum 5 Kelas IF 2C Pagi"     => 5
     *   - "Pengumpulan praktikum minggu 6 kelas A Pagi"  => 6  (angka setelah
     *     kata "Praktikum" dulu dicari; kalau tidak ada, baru setelah
     *     kata "Minggu")
     *   - "Upload Tugas Minggu ke-11 Manpro ND"          => 11 (kata "ke-"
     *     ikut ditoleransi)
     *   - "Upload Tugas Minggu 12-13 ..."                => 12 (angka pertama)
     *   - "ATS Pratikum - IF 2C Pagi"                    => null  (pratikum
     *     salah tulis + tidak ada angka: bukan nomor minggu)
     *   - "Upload hasil design (1)"                      => null  (angka
     *     dalam kurung itu penanda file, bukan urutan minggu)
     *   - "Pengumpulan AAS Teori dan Praktikum - IF 2C Pagi" => null  (angka
     *     "2" di nama kelas "2C" bukan nomor minggu; diwarnai sebagai jeda
     *     hanya boleh berupa spasi dan kata "minggu"/"ke-")
     */
    private function nomorDariNama(string $nama): ?int
    {
        $polaAngka = '(?:\\s*(?:ke\\s*-?|minggu\\s*)?\\s*(\\d{1,2}))';

        foreach (['/praktikum'.$polaAngka.'/i', '/minggu'.$polaAngka.'/i'] as $pola) {
            if (preg_match($pola, $nama, $m) === 1) {
                $nomor = (int) $m[1];

                if ($nomor >= 1 && $nomor <= 30) {
                    return $nomor;
                }

                return null;
            }
        }

        return null;
    }

    // -------------------------------------------------------------- minggu

    /** Tanggal Senin pada minggu ke-1 semester. */
    private function mingguAwalSemester(Kelas $kelas): string
    {
        $tahun = (string) $kelas->tahun_akademik;
        $awalTahun = preg_match('/^(\d{4})/', trim($tahun), $m) === 1 ? (int) $m[1] : (int) date('Y');

        $bulan = $kelas->semester === 1 ? 8 : 2;
        $tahunBulan = $kelas->semester === 1 ? $awalTahun : $awalTahun + 1;

        $awalBulan = sprintf('%04d-%02d-01', $tahunBulan, $bulan);

        // Senin pada minggu yang MEMATUKI tanggal 1. Cukup dengan mengurangi
        // (N-1) hari: tanggal 1 bisa jatuh di hari apa pun, dan minggu ISO
        // selalu dimulai hari Senin.
        $hari = (int) date('N', strtotime($awalBulan));

        return date('Y-m-d', strtotime('-'.($hari - 1).' days', strtotime($awalBulan)));
    }

    private function tambahHari(string $tanggal, int $hari): string
    {
        return date('Y-m-d H:i:s', strtotime($tanggal.' +'.$hari.' days'));
    }

    /** Nomor minggu semester, mulai dari 1. */
    private function nomorMinggu(string $tanggal, string $mingguAwal): int
    {
        $selisih = strtotime(date('Y-m-d', strtotime($tanggal))) - strtotime($mingguAwal);

        return (int) floor($selisih / 604800) + 1;
    }

    /** Tanggal Senin pada minggu ke-N (untuk tooltip). */
    private function seninMinggu(int $nomor, string $mingguAwal): string
    {
        return date('Y-m-d', strtotime($mingguAwal.' +'.(($nomor - 1) * 7).' days'));
    }

    // --------------------------------------------------------------- status

    /**
     * Pengumpulan mahasiswa, dikunci [nim][nama tugas] = waktu pertama.
     *
     * Hanya pengumpulan PERTAMA yang dipakai: mahasiswa yang mengupload
     * dua kali tetap dihitung sekali, dan yang pertama menentukan apakah
     * dia tepat waktu.
     *
     * @param  list<string>  $kodeMk
     * @param  list<string>  $nim
     * @param  list<array<string, mixed>>  $tugas
     * @param  array{dari: ?string, sampai: ?string}  $rentang  Hanya pengumpulan
     *                                                          yang terjadi di dalam rentang ini yang dihitung (tab Keaktifan). Kosong
     *                                                          berarti semua.
     * @return array<string, array<string, string>>
     */
    private function pengumpulan(array $kodeMk, array $nim, array $tugas, array $rentang = ['dari' => null, 'sampai' => null]): array
    {
        if ($tugas === [] || $nim === []) {
            return [];
        }

        $namaTugas = array_column($tugas, 'nama');

        $baris = DB::table('log_aktivitas')
            ->select('actor_identifier', 'context_name', 'occurred_at')
            ->where('event_name', LogAktivitas::EVENT_TUGAS_DIKUMPULKAN)
            ->whereIn('kode_mata_kuliah', $kodeMk)
            ->where('actor_role', LogAktivitas::PERAN_MAHASISWA)
            ->whereIn('actor_identifier', $nim)
            ->whereIn('context_name', $namaTugas)
            ->when($rentang['dari'], fn ($q) => $q->where('occurred_at', '>=', $rentang['dari'].' 00:00:00'))
            ->when($rentang['sampai'], fn ($q) => $q->where('occurred_at', '<=', $rentang['sampai'].' 23:59:59'))
            ->orderBy('occurred_at')
            ->get();

        $hasil = [];

        foreach ($baris as $b) {
            $kunciNim = '_'.$b->actor_identifier;

            if (! isset($hasil[$kunciNim][$b->context_name])) {
                $hasil[$kunciNim][$b->context_name] = (string) $b->occurred_at;
            }
        }

        return $hasil;
    }

    /**
     * Status satu mahasiswa untuk satu minggu.
     *
     * Urutan artisitas: merah mengalahkan kuning mengalahkan hijau, karena
     * minggu tanpa pengumpulan sama sekali adalah yang paling perlu
     * mendapat perhatian.
     *
     * @param  string  $kunciNim  Kunci peta mahasiswa (sudah berawalan "_")
     * @param  list<array<string, mixed>>  $daftarTugas
     * @param  array<string, array<string, string>>  $kirim
     * @param  string  $kunciNim  Kunci peta mahasiswa (sudah berawalan "_")
     * @return array{status: string, detail: list<array<string, string>>}
     */
    private function statusMahasiswa(string $kunciNim, array $daftarTugas, array $kirim): array
    {
        $status = $daftarTugas === [] ? self::TANPA_TUGAS : self::TEPAT_WAKTU;
        $detail = [];

        foreach ($daftarTugas as $tugas) {
            $waktu = $kirim[$kunciNim][$tugas['nama']] ?? null;

            if ($waktu === null) {
                $statusPerTugas = self::TIDAK_MENGUMPULKAN;
            } elseif ($waktu <= $tugas['tenggat']) {
                $statusPerTugas = self::TEPAT_WAKTU;
            } else {
                $statusPerTugas = self::TERLAMBAT;
            }

            $detail[] = [
                'nama' => $tugas['nama'],
                'status' => $statusPerTugas,
                'waktu' => $waktu === null ? 'tidak mengumpulkan' : $waktu,
                'tenggat' => $tugas['tenggat'],
            ];

            $status = match ($statusPerTugas) {
                self::TIDAK_MENGUMPULKAN => self::TIDAK_MENGUMPULKAN,
                self::TERLAMBAT => $status === self::TIDAK_MENGUMPULKAN ? self::TIDAK_MENGUMPULKAN : self::TERLAMBAT,
                default => $status,
            };
        }

        return ['status' => $status, 'detail' => $detail];
    }
}
