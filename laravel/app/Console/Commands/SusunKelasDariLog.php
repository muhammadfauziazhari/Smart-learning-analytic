<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use App\Services\BuktiPeran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Susun kelas dan daftar mahasiswa dari log yang sudah diimpor.
 *
 * Kelas idealnya dibuat lewat form akademik (FR-03). Perintah ini adalah
 * jalan pintasan untuk data awal: log LMS sudah memberi tahu siapa yang
 * mengikuti course mana, dan menghimpun 250-an mahasiswa satu per satu
 * lewat form adalah pekerjaan yang tidak realistis.
 *
 * Yang TIDAK diklaim perintah ini:
 *   - Kode kelas (pagi/malam/A/B) tidak ada di dalam log. Perintah
 *     membuat satu kelas per mata kuliah; pemisahan kelas sungguhan tetap
 *     kewajiban admin lewat form.
 *   - Identitas yang punya bukti membuat course, atau mengelola course,
 *     tidak boleh masuk daftar mahasiswa dan tidak ikut dihitung.
 */
class SusunKelasDariLog extends Command
{
    protected $signature = 'kelas:susun-dari-log
                            {--prodi= : Kode prodi tujuan, mis. TI}
                            {--kode-kelas=A : Kode kelas yang dibuat}
                            {--tahun-akademik= : Contoh 2025/2026}
                            {--semester= : 1 atau 2}
                            {--awalan-nim=3312 : Awalan NIM yang diakui, pisahkan dengan koma}
                            {--dry-run : Tampilkan rencana tanpa menyimpan}';

    protected $description = 'Buat kelas dan daftar mahasiswa dari log yang sudah diimpor';

    public function handle(BuktiPeran $buktiPeran): int
    {
        $mataKuliah = $this->mataKuliahDariLog();

        if ($mataKuliah === []) {
            $this->warn('Belum ada log yang memuat kode mata kuliah.');
            $this->line('  Impor dulu lewat `php artisan log:impor <file>` atau form /admin/log-import.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info('Mata kuliah di log:');
        foreach ($mataKuliah as $mk) {
            $this->line(sprintf(
                '  %-8s %-32s %9s baris · %s s.d. %s',
                $mk['kode'],
                $mk['nama'],
                number_format($mk['baris'], 0, ',', '.'),
                substr((string) $mk['mulai'], 0, 10),
                substr((string) $mk['akhir'], 0, 10),
            ));
        }
        $this->newLine();

        $prodi = $this->menentukanProdi();

        if ($prodi === null) {
            return self::FAILURE;
        }

        $this->line("Prodi tujuan    : {$prodi->kode} / {$prodi->nama}");
        $this->line('Awalan NIM      : '.implode(', ', $this->awalanNim()));
        $this->line('Mode            : '.($dryRun ? '<fg=yellow>PRATINJAU (tidak menyimpan)</>' : 'menyimpan'));
        $this->newLine();

        $hasil = [];

        foreach ($mataKuliah as $mk) {
            [$tahun, $semester, $asal] = $this->menentukanPeriodeKelas($mk['kode']);

            $hasil[] = $this->susunSatu($mk, $prodi, $buktiPeran, $tahun, $semester, $dryRun, $asal);
        }

        $this->table(
            ['Mata kuliah', 'Kelas', 'Periode', 'Mahasiswa', 'Baris log', 'Dosen di log', 'Dikecualikan', 'Periode dari'],
            $hasil,
        );

        if ($dryRun) {
            $this->newLine();
            $this->comment('Pratinjau saja. Jalankan tanpa --dry-run untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /**
     * Mata kuliah yang muncul di log, beserta rentang waktunya.
     *
     * @return list<array{kode: string, nama: string, baris: int, mulai: string|null, akhir: string|null}>
     */
    private function mataKuliahDariLog(): array
    {
        return DB::table('log_aktivitas')
            ->selectRaw('kode_mata_kuliah as kode')
            ->selectRaw('MAX(nama_mata_kuliah) as nama')
            ->selectRaw('COUNT(*) as baris')
            ->selectRaw('MIN(occurred_at) as mulai')
            ->selectRaw('MAX(occurred_at) as akhir')
            ->whereNotNull('kode_mata_kuliah')
            ->where('kode_mata_kuliah', '<>', '')
            ->groupBy('kode_mata_kuliah')
            ->orderBy('kode_mata_kuliah')
            ->get()
            ->map(fn ($r) => [
                'kode' => (string) $r->kode,
                'nama' => trim((string) $r->nama) !== '' ? trim((string) $r->nama) : (string) $r->kode,
                'baris' => (int) $r->baris,
                'mulai' => $r->mulai,
                'akhir' => $r->akhir,
            ])
            ->all();
    }

    /**
     * Prodi tujuan.
     *
     * Kalau --prodi tidak diberikan, prodi diambil dari akun-akun yang
     * nomor induknya benar-benar muncul sebagai pengajar di log. Itu
     * bukan tebakan: orangnya sudah tercatat di sistem dan prodisnya sudah
     * diisi admin. Kalau pengajar log berasal dari prodi berbeda-beda,
     * perintah berhenti dan meminta --prodi.
     */
    private function menentukanProdi(): ?Prodi
    {
        $kode = $this->option('prodi');

        if (is_string($kode) && $kode !== '') {
            $prodi = Prodi::where('kode', $kode)->first();

            if ($prodi === null) {
                $this->error("Prodi {$kode} tidak ada. Isi dulu lewat form akademik.");
            }

            return $prodi;
        }

        $akunPengajar = User::query()
            ->whereNotNull('nomor_induk')
            ->whereNotNull('prodi_id')
            ->whereIn('nomor_induk', function ($q) {
                $q->select('actor_identifier')
                    ->from('log_aktivitas')
                    ->whereNotNull('actor_identifier')
                    ->distinct();
            })
            ->get();

        $prodiId = $akunPengajar->pluck('prodi_id')->unique();

        if ($prodiId->count() === 1) {
            $this->line(sprintf(
                'Prodi diambil dari %d akun pengajar yang sudah terdaftar (%s).',
                $akunPengajar->count(),
                $akunPengajar->pluck('email')->implode(', '),
            ));

            return Prodi::find($prodiId->first());
        }

        $this->error('Prodi tujuan tidak bisa dipastikan.');
        $this->line('  Akun pengajar di log berasal dari '.($prodiId->count() ?: 'nol').' prodi berbeda.');
        $this->line('  Jalankan ulang dengan --prodi=TI (atau kode prodi yang sesuai).');

        return null;
    }

    /**
     * Tahun akademik dan semester untuk satu mata kuliah.
     *
     * Sumbernya BULAN DENGAN AKTIVITAS TERBANYAK, bukan baris terakhir.
     *
     * Alasannya data nyata: file log Jaringan Komputer berakhir 14 Oktober
     * 2026, padahal 99% aktivitasnya terjadi Februari s.d. Juli 2026. Satu
     * baris sisipan setelah semester selesai akan membalikkan seluruh kelas
     * ke semester ganjil yang salah kalau yang dipakai adalah tanggal
     * terakhir.
     *
     * Semester ganjil berjalan Agustus s.d. Januari: data berakhir
     * September 2026 berarti 2026/2027, sedangkan data berakhir Juli 2026
     * berarti 2025/2026 (semester genap).
     *
     * @return array{0: string, 1: int, 2: string} [tahun, semester, asal]
     */
    private function menentukanPeriodeKelas(string $kodeMk): array
    {
        $tahunOpsi = $this->option('tahun-akademik');
        $semesterOpsi = $this->option('semester');

        $puncak = DB::table('log_aktivitas')
            ->selectRaw('periode, COUNT(*) as jumlah')
            ->where('kode_mata_kuliah', $kodeMk)
            ->whereNotNull('periode')
            ->groupBy('periode')
            ->orderByDesc('jumlah')
            ->value('periode');

        if ($puncak === null) {
            $puncak = (int) date('Y-m');
        }

        $tahun = (int) substr((string) $puncak, 0, 4);
        $bulan = (int) substr((string) $puncak, 5, 2);

        $tahunAkademik = is_string($tahunOpsi) && $tahunOpsi !== ''
            ? $tahunOpsi
            : ($bulan >= 8 ? "{$tahun}/".($tahun + 1) : ($tahun - 1)."/{$tahun}");

        $semester = in_array((string) $semesterOpsi, ['1', '2'], true)
            ? (int) $semesterOpsi
            : ($bulan >= 8 || $bulan === 1 ? 1 : 2);

        $asal = is_string($tahunOpsi) && $tahunOpsi !== ''
            ? 'ditentukan admin'
            : "bulan paling ramai {$puncak}";

        return [$tahunAkademik, $semester, $asal];
    }

    /**
     * Susun satu mata kuliah: kelas + daftar mahasiswa.
     *
     * @param  array{kode: string, nama: string, baris: int, mulai: string|null, akhir: string|null}  $mk
     * @return array<int, string>
     */
    private function susunSatu(
        array $mk,
        Prodi $prodi,
        BuktiPeran $buktiPeran,
        string $tahun,
        int $semester,
        bool $dryRun,
        string $asal,
    ): array {
        $mkModel = MataKuliah::firstOrNew(['kode' => $mk['kode']]);
        $mkModel->fill([
            'nama' => $mk['nama'],
            'prodi_id' => $prodi->id,
        ]);
        $mkModel->sks ??= 3;

        $kodeKelas = (string) $this->option('kode-kelas');

        $kelas = Kelas::firstOrNew([
            'mata_kuliah_id' => $mkModel->id ?: 0,
            'kode' => $kodeKelas,
        ]);
        $kelas->fill([
            'nama' => 'Kelas '.$kodeKelas,
            'prodi_id' => $prodi->id,
            'tahun_akademik' => $tahun,
            'semester' => $semester,
        ]);

        $sudah = Enrollment::where('kelas_id', $kelas->id)->count();

        $kandidat = $this->kandidatMahasiswa($mk['kode'], $buktiPeran);
        $baru = 0;
        $dikecualikan = [];
        $dosenDiLog = $this->akunDosenDiLog($mk['kode']);
        $awalanNim = $this->awalanNim();

        // Dua penyaring, berurutan dari yang paling kuat.
        foreach ($kandidat as $nim => $r) {
            if (! $buktiPeran->bolehJadiMahasiswa($r['tingkat'])) {
                $dikecualikan[$r['tingkat']] = ($dikecualikan[$r['tingkat']] ?? 0) + 1;

                continue;
            }

            // Nomor yang tidak berawalan NIM prodi ini bukan mahasiswa.
            // "Ketua Program Studi Informatika 1506" aktivitasnya sedikit,
            // tapi awalan nomornya sudah memastikan dia bukan mahasiswa.
            if (! $buktiPeran->bentukannyaNim((string) $nim, $awalanNim)) {
                $dikecualikan['bukan_nim'] = ($dikecualikan['bukan_nim'] ?? 0) + 1;

                continue;
            }

            $baru++;
        }

        if (! $dryRun) {
            $mkModel->save();
            $kelas->mata_kuliah_id = $mkModel->id;
            $kelas->save();

            foreach ($kandidat as $nim => $r) {
                if (! $buktiPeran->bolehJadiMahasiswa($r['tingkat'])) {
                    continue;
                }

                if (! $buktiPeran->bentukannyaNim((string) $nim, $awalanNim)) {
                    continue;
                }

                Enrollment::updateOrCreate(
                    ['kelas_id' => $kelas->id, 'nim' => (string) $nim],
                    ['nama' => $r['nama'], 'terdaftar_pada' => now()],
                );
            }
        }

        // Saat menyimpan, jumlah asli dibaca ulang dari database. Angka
        // "baru" hanya berlaku untuk pratinjau, karena Enrollment memakai
        // updateOrCreate dan tidak menambah baris baru kalau sudah ada.
        $totalTerdaftar = $dryRun
            ? $sudah + $baru
            : Enrollment::where('kelas_id', $kelas->id)->count();

        $keteranganKecualikan = $dikecualikan === []
            ? '-'
            : collect($dikecualikan)->map(fn ($j, $n) => "{$n} {$j}")->implode(', ');

        return [
            $mk['kode'].' '.$mk['nama'],
            $kodeKelas,
            $tahun.'/'.$semester,
            (string) $totalTerdaftar,
            number_format($mk['baris'], 0, ',', '.'),
            (string) $dosenDiLog,
            $keteranganKecualikan,
            $asal,
        ];
    }

    /**
     * Awalan NIM yang diakui sebagai mahasiswa prodi ini.
     *
     * Default 3312 (keputusan pengguna, 18 Okt 2026). Bisa ditambah untuk
     * prodi lain, mis. --awalan-nim=3312,4312 untuk kelas yang juga diikuti
     * mahasiswa Sistem Informasi.
     *
     * @return list<string>
     */
    private function awalanNim(): array
    {
        $opsi = $this->option('awalan-nim');

        if (! is_string($opsi) || trim($opsi) === '') {
            return ['3312'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $opsi))));
    }

    /**
     * Identitas ber peran mahasiswa di satu mata kuliah, beserta bukti
     * perannya.
     *
     * @return array<string, array{nama: string, tingkat: string, bukti: array<int, string>, event: int}>
     */
    private function kandidatMahasiswa(string $kodeMk, BuktiPeran $buktiPeran): array
    {
        $baris = DB::table('log_aktivitas')
            ->select('actor_identifier', 'actor_name', 'event_name')
            ->selectRaw('COUNT(*) as jumlah')
            ->where('kode_mata_kuliah', $kodeMk)
            ->where('actor_role', LogAktivitas::PERAN_MAHASISWA)
            ->whereNotNull('actor_identifier')
            ->groupBy('actor_identifier', 'actor_name', 'event_name')
            ->get();

        $gabung = [];

        foreach ($baris as $r) {
            $kunci = '_'.$r->actor_identifier;

            if (! isset($gabung[$kunci])) {
                $gabung[$kunci] = [
                    'nama' => $this->bersihkanNama((string) $r->actor_name, (string) $r->actor_identifier),
                    'jenis' => [],
                    'event' => 0,
                ];
            }

            $gabung[$kunci]['jenis'][(string) $r->event_name] = (int) $r->jumlah;
            $gabung[$kunci]['event'] += (int) $r->jumlah;
        }

        $hasil = [];

        foreach ($gabung as $kunci => $r) {
            $putusan = $buktiPeran->classify($r['jenis']);
            $hasil[ltrim($kunci, '_')] = [
                'nama' => $r['nama'],
                'tingkat' => $putusan['tingkat'],
                'bukti' => $putusan['bukti'],
                'event' => $r['event'],
            ];
        }

        return $hasil;
    }

    /** Berapa akun yang benar-benar ada di sistem dan muncul di log ini. */
    private function akunDosenDiLog(string $kodeMk): int
    {
        return User::query()
            ->whereNotNull('nomor_induk')
            ->whereIn('nomor_induk', function ($q) use ($kodeMk) {
                $q->select('actor_identifier')
                    ->from('log_aktivitas')
                    ->where('kode_mata_kuliah', $kodeMk)
                    ->whereNotNull('actor_identifier')
                    ->distinct();
            })
            ->count();
    }

    /**
     * Buang identitas yang menempel di ujung nama.
     *
     * Kolom "User full name" berisi nama DAN nomor sekaligus
     * ("Ririn Anisa Sihite 3312501013"). Yang disimpan di kelas_mahasiswa
     * hanya namanya; nomornya sudah ada di kolom nim.
     */
    private function bersihkanNama(string $nama, string $identitas): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', $nama) ?? '');

        if ($nama === '') {
            return 'NIM '.$identitas;
        }

        // Buang nomor yang menempel di ujung nama.
        $tanpaNomor = preg_replace('/\s*\d{4,}\s*$/u', '', $nama) ?? $nama;

        return trim($tanpaNomor) !== '' ? trim($tanpaNomor) : $nama;
    }
}
