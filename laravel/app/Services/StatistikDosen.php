<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data statistik analitik untuk dashboard Dosen (FR-20 / FRK-15).
 *
 * Angkanya sama persis dengan yang dilihat Kaprodi, dan memang itu
 * disengaja: perhitungannya ada di StatistikKelas supaya tidak mungkin
 * dua peran memakai rumus berbeda. Yang khas Dosen ada di sini:
 *
 *   1. Cakupan kelas. Dosen hanya melihat kelas yang ia ampu
 *      (penugasan di menu Penugasan Akademik) dan kelas mata kuliah
 *      yang diberi izin Admin lewat Kelola Akun (FR-05).
 *
 *   2. `sumberAkses` per kelas: dari penugasan pengampu/pembantu, atau
 *      dari izin mata kuliah. Dosen perlu tahu itu supaya bisa
 *      bertanya ke Admin kalau ada yang terasa ganjil.
 *
 *   3. `perluPerhatian`: mahasiswa berisiko lintas kelas, diurut dari
 *      paling berisiko. Bagi Dosen ini daftar kerjaan — siapa yang perlu
 *      dihubungi lebih dulu — bukan sekadar angka rekap seperti pada
 *      sisi Kaprodi.
 *
 * Penyaringan tidak boleh dilakukan di controller atau di view: kelas
 * selalu diambil lewat `Kelas::scopeUntukPengguna()`.
 */
class StatistikDosen
{
    /** Berapa mahasiswa berisiko yang ditampilkan di daftar kerjaan. */
    private const BATAS_PERLU_PERHATIAN = 10;

    public function __construct(private StatistikKelas $statistikKelas) {}

    /**
     * Susun seluruh data dashboard Dosen.
     *
     * @param  array{mata_kuliah?: ?string, jenis?: ?string, asal?: ?string}  $filter  Penyaring kelas
     *                                                                                 yang diminta pengguna (FRK-16). Nilai yang tidak ada di kelas
     *                                                                                 miliknya diabaikan, bukan dipakai menyaring diam-diam.
     * @return array<string, mixed>
     */
    public function untuk(User $dosen, array $filter = []): array
    {
        $semuaKelas = $this->kelasTerlihat($dosen);
        $bersih = $this->bersihkanFilter($semuaKelas, $filter);
        $kelasList = $this->saring($semuaKelas, $bersih);

        $daftarKelas = [];

        // Satu panggilan ke StatistikKelas untuk seluruh kelas yang
        // tersisa. Menghitung per kelas akan mengulang agregasi log dan
        // panggilan layanan untuk tiap kelas dari mata kuliah yang sama.
        foreach ($this->statistikKelas->untukBanyak($kelasList) as $data) {
            $data['sumberAkses'] = $this->sumberAkses(
                $kelasList->firstWhere('id', $data['id']),
                $dosen,
            );

            $daftarKelas[] = $data;
        }

        $rekap = $this->statistikKelas->ringkasan($kelasList, $daftarKelas);

        [$perluPerhatian, $jumlahPerluPerhatian] = $this->kumpulkanPerluPerhatian($daftarKelas);

        return $rekap + [
            'kelas' => $daftarKelas,
            'perluPerhatian' => $perluPerhatian,
            'jumlahPerluPerhatian' => $jumlahPerluPerhatian,
            // Berapa yang benar-benar ditampilkan, supaya view tidak perlu
            // menghitung ulang daftar yang sudah dipotong.
            'jumlahTampilPerluPerhatian' => count($perluPerhatian),
            'analisisAda' => $kelasList->isNotEmpty() && $this->semuaKelasTeranalisis($daftarKelas),
            'filter' => [
                'mata_kuliah' => $bersih['mata_kuliah'] ?? null,
                'jenis' => $bersih['jenis'] ?? null,
                'asal' => $bersih['asal'] ?? null,
                // Pilihan dropdown diambil dari kelas yang memang boleh
                // dibuka Dosen ini, bukan dari seluruh sistem.
                'pilihanMataKuliah' => $this->pilihanMataKuliah($semuaKelas),
                'pilihanJenis' => $this->pilihan($semuaKelas, 'jenis_kelas'),
                'pilihanAsal' => $this->pilihan($semuaKelas, 'asal_kelas'),
                // Dipakai untuk memberi tahu bahwa menyaring tidak
                // mengubah cakupan akses, hanya tampilan.
                'jumlahTanpaFilter' => $semuaKelas->count(),
            ],
        ];
    }

    /**
     * Kelas yang boleh dibuka Dosen ini, urut dari kode mata kuliah.
     *
     * @return Collection<int, Kelas>
     */
    private function kelasTerlihat(User $dosen): Collection
    {
        return Kelas::query()
            ->untukPengguna($dosen)
            ->with(['mataKuliah'])
            ->orderBy('kode')
            ->get()
            ->sortBy(fn (Kelas $k) => (string) ($k->mataKuliah?->kode ?? ''))
            ->values();
    }

    /**
     * Buang nilai penyaring yang tidak ada di pilihan kelas Dosen ini.
     *
     * Tanpa ini, mengetik `?jenis=ngawur` di URL akan membuat dashboard
     * kosong tanpa penjelasan — dan dropdown akan menampilkan nilai yang
     * tidak ada di mana pun.
     *
     * @param  Collection<int, Kelas>  $kelas
     * @param  array{mata_kuliah?: ?string, jenis?: ?string, asal?: ?string}  $filter
     * @return array{mata_kuliah: ?string, jenis: ?string, asal: ?string}
     */
    private function bersihkanFilter(Collection $kelas, array $filter): array
    {
        $pilihanJenis = $this->pilihan($kelas, 'jenis_kelas');
        $pilihanAsal = $this->pilihan($kelas, 'asal_kelas');
        $pilihanMk = array_column($this->pilihanMataKuliah($kelas), 'kode');

        return [
            'mata_kuliah' => in_array($filter['mata_kuliah'] ?? null, $pilihanMk, true) ? $filter['mata_kuliah'] : null,
            'jenis' => in_array($filter['jenis'] ?? null, $pilihanJenis, true) ? $filter['jenis'] : null,
            'asal' => in_array($filter['asal'] ?? null, $pilihanAsal, true) ? $filter['asal'] : null,
        ];
    }

    /**
     * Terapkan penyaring mata kuliah/jenis/asal kelas.
     *
     * Class "Tanpa Kelas Asal" sengaja ikut tersaring: dia tidak punya
     * nilai jenis/asal, dan disembunyikan hanya kalau pengguna memilih
     * salah satu nilai.
     *
     * @param  Collection<int, Kelas>  $kelas
     * @param  array{mata_kuliah?: ?string, jenis?: ?string, asal?: ?string}  $filter
     * @return Collection<int, Kelas>
     */
    private function saring(Collection $kelas, array $filter): Collection
    {
        return $kelas
            ->when(
                filled($filter['mata_kuliah'] ?? null),
                fn (Collection $c) => $c->filter(fn (Kelas $k) => $k->mataKuliah?->kode === $filter['mata_kuliah'])
            )
            ->when(
                filled($filter['jenis'] ?? null),
                fn (Collection $c) => $c->filter(fn (Kelas $k) => $k->jenis_kelas === $filter['jenis'])
            )
            ->when(
                filled($filter['asal'] ?? null),
                fn (Collection $c) => $c->filter(fn (Kelas $k) => $k->asal_kelas === $filter['asal'])
            )
            ->values();
    }

    /**
     * Mata kuliah yang setidaknya satu kelasnya boleh dibuka Dosen ini.
     *
     * @param  Collection<int, Kelas>  $kelas
     * @return list<array{kode: string, nama: string}>
     */
    private function pilihanMataKuliah(Collection $kelas): array
    {
        return $kelas
            ->pluck('mataKuliah')
            ->filter()
            ->unique('kode')
            ->sortBy('kode')
            ->map(fn ($mk) => ['kode' => $mk->kode, 'nama' => $mk->nama])
            ->values()
            ->all();
    }

    /**
     * Nilai unik satu kolom dari kelas milik Dosen ini.
     *
     * @param  Collection<int, Kelas>  $kelas
     * @return list<string>
     */
    private function pilihan(Collection $kelas, string $kolom): array
    {
        return $kelas
            ->pluck($kolom)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Dari mana Dosen ini berhak melihat kelas tersebut.
     *
     * Dua sumber, sesuai keputusan 18 Okt 2026:
     *   - `kelas_dosen`      : ditugaskan pengampu atau pembantu
     *   - `dosen_mata_kuliah`: Admin memberi izin untuk seluruh mata kuliah
     */
    private function sumberAkses(Kelas $kelas, User $dosen): string
    {
        $peran = DB::table('kelas_dosen')
            ->where('kelas_id', $kelas->id)
            ->where('user_id', $dosen->id)
            ->value('peran');

        if ($peran === 'pembantu') {
            return 'Pembantu';
        }

        if ($peran === 'pengampu') {
            return 'Pengampu';
        }

        return 'Izin mata kuliah';
    }

    /**
     * Daftar mahasiswa berisiko lintas kelas, paling berisiko di atas.
     *
     * Hanya mahasiswa berstatus risiko tinggi atau menengah yang masuk.
     * Yang berisiko rendah justru disembunyikan: daftar ini diperlakukan
     * sebagai daftar kerjaan, dan masukkan semua orang membuat Dosen
     * berhenti membacanya.
     *
     * @param  list<array<string, mixed>>  $daftarKelas
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function kumpulkanPerluPerhatian(array $daftarKelas): array
    {
        $kandidat = [];

        foreach ($daftarKelas as $data) {
            foreach ($data['mahasiswa'] as $m) {
                if (! in_array($m['level'] ?? null, ['high', 'medium'], true)) {
                    continue;
                }

                $kandidat[] = [
                    'nim' => $m['nim'],
                    'nama' => $m['nama'],
                    'kelas' => $data['labelKelas'],
                    'mataKuliah' => $data['nama'],
                    'event' => $m['event'],
                    'hari' => $m['hari'],
                    'tugas' => $m['tugas'],
                    'engagement' => $m['engagement'],
                    'klaster' => $m['klaster'],
                    'level' => $m['level'],
                    'risk' => $m['risk'],
                ];
            }
        }

        usort($kandidat, function ($a, $b) {
            $risikoA = $a['risk'] ?? -1;
            $risikoB = $b['risk'] ?? -1;

            if ($risikoA !== $risikoB) {
                return $risikoB <=> $risikoA;
            }

            return $a['event'] <=> $b['event'];
        });

        return [
            array_slice($kandidat, 0, self::BATAS_PERLU_PERHATIAN),
            count($kandidat),
        ];
    }

    /**
     * True kalau semua kelas yang ada barisnya benar-benar dianalisis.
     *
     * Kalau layanan analytics mati (FR-12a), daftar perlu perhatian kosong
     * bukan karena semua mahasiswa aman, melainkan karena skor risikonya
     * tidak pernah dihitung. Penampilannya wajib membedakan keduanya.
     *
     * @param  list<array<string, mixed>>  $daftarKelas
     */
    private function semuaKelasTeranalisis(array $daftarKelas): bool
    {
        foreach ($daftarKelas as $data) {
            if ($data['terdaftar'] > 0 && ! $data['analisisAda']) {
                return false;
            }
        }

        return true;
    }
}
