<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Kelas;
use App\Models\MataKuliah;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pecah kelas besar per mata kuliah menjadi kelas Pagi/Malam dan asal A/B/C/D.
 *
 * DATA DARI PENGGUNA (18 Okt 2026)
 *
 * Daftar mahasiswa yang sudah ada dipisah berdasarkan kelas asal (A/B/C/D)
 * dan waktu pembelajaran (Pagi/Malam). Awalnya sistem hanya punya satu
 * kelas per mata kuliah ("Kelas A"), sehingga dashboard Dosen tidak bisa
 * memfilter jenis maupun asal kelas.
 *
 * Sumber daftar: berkas Excel di `sample-data/` (config services.excel).
 * Berkas .xlsx tidak bisa dibaca PHP di sini karena ekstensi `zip` tidak
 * ada, jadi dikonversi dulu oleh analytics/scripts/xlsx_ke_csv.py.
 *
 * ATURAN IMPORT
 *
 *   1. Mahasiswa yang NIM-nya tidak ada di roster TIDAK dibuang. Dia
 *      dipindahkan ke kelas khusus "Tanpa Kelas Asal" supaya aktivitasnya
 *      tetap terhitung dan tetap terlihat, hanya tidak punya label
 *      asal/jenis (keputusan pengguna, 18 Okt 2026).
 *   2. Tahun akademik dan semester kelas SALIN dari kelas lama. Kalau
 *      dibiarkan berbeda, jendela analisis satu semester ikut bergeser dan
 *      seluruh angka historis berubah.
 *   3. Kelas lama yang menjadi kosong dibiarkan. Menghapus kelas ikut
 *      menghapus penugasan dosen di `kelas_dosen`, dan itu keputusan Admin,
 *      bukan perintah ini. Kelas kosong hanya dilaporkan.
 *   4. Perintah boleh dijalankan berulang; yang sudah benar tidak disentuh.
 */
class ImporRosterKelas extends Command
{
    protected $signature = 'kelas:impor-roster
                            {--file= : Berkas .xlsx atau .csv daftar mahasiswa}
                            {--mata-kuliah=* : Kode mata kuliah tujuan (default: semua MK yang sudah punya kelas)}
                            {--dry-run : Tampilkan rencana saja, jangan ubah data}
                            {--perbarui-nama : Timpa nama mahasiswa dengan nama dari roster}
                            {--tanpa-pindahkan-penugasan : Jangan pindahkan penugasan dosen dari kelas lama ke kelas hasil pecahan}
                            {--hapus-kelas-lama : Hapus kelas lama yang sudah kosong setelah penugasannya dipindahkan}';

    protected $description = 'Pecah kelas per mata kuliah menjadi kelas Pagi/Malam dan asal A/B/C/D dari daftar mahasiswa';

    /** Kode kelas untuk mahasiswa yang tidak ada di daftar. */
    private const KODE_TANPA_ASAL = 'Tanpa Kelas Asal';

    /** @var list<list<string>> Baris CSV hasil pemecahan. */
    private array $barisCsv = [];

    public function handle(): int
    {
        $berkas = $this->cariBerkas();

        if ($berkas === null) {
            return self::FAILURE;
        }

        $csv = $this->siapkanCsv($berkas);

        if ($csv === null) {
            return self::FAILURE;
        }

        $roster = $this->bacaRoster($csv);

        if ($roster === []) {
            $this->error("Tidak ada baris roster yang bisa dibaca dari {$berkas}.");

            return self::FAILURE;
        }

        $mataKuliah = $this->cariMataKuliah();

        if ($mataKuliah->isEmpty()) {
            $this->error('Tidak ada mata kuliah yang jadi target. Buat kelas dulu, atau sebutkan --mata-kuliah=IF209.');

            return self::FAILURE;
        }

        $this->line("Sumber : {$berkas}");
        $this->line(sprintf(
            'Roster : %d mahasiswa, %d kelompok kelas',
            count($roster),
            collect($roster)->pluck('kelas')->unique()->count()
        ));
        $this->newLine();

        $dryRun = (bool) $this->option('dry-run');

        foreach ($mataKuliah as $mk) {
            $this->prosesMataKuliah($mk, $roster, $dryRun);
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------- cari berkas

    /** Berkas dari --file, atau berkas pertama yang ada di config. */
    private function cariBerkas(): ?string
    {
        $dariOpsi = $this->option('file');

        if (filled($dariOpsi)) {
            $jalur = $this->keJalurMutu($dariOpsi);

            if (! is_file($jalur)) {
                $this->error("Berkas tidak ditemukan: {$jalur}");

                return null;
            }

            return $jalur;
        }

        foreach ((array) config('services.excel.berkas', []) as $kandidat) {
            if (is_file($kandidat)) {
                return $kandidat;
            }
        }

        $this->error('Berkas roster tidak ditemukan. Taruh di sample-data/ atau sebutkan --file=');

        return null;
    }

    /**
     * Path relatif dihitung dari folder Laravel, bukan dari direktori kerja.
     *
     * Path Windows mutlak (C:\...) juga harus dikenali; kalau tidak,
     * base_path akan menempel di depannya dan berkas yang ada dianggap
     * tidak ditemukan.
     */
    private function keJalurMutu(string $jalur): string
    {
        $mutlak = preg_match('/^([A-Za-z]:[\\\\\/]|[\\\\\/]{1,2})/', $jalur) === 1;

        return $mutlak ? $jalur : base_path($jalur);
    }

    // ------------------------------------------------------------ konversi

    /**
     * Pastikan ada versi CSV dari berkasnya.
     *
     * CSV dipakai apa adanya; .xlsx dikonversi dulu lewat Python.
     */
    private function siapkanCsv(string $berkas): ?string
    {
        if (strtolower((string) pathinfo($berkas, PATHINFO_EXTENSION)) !== 'xlsx') {
            return $berkas;
        }

        $python = (string) config('services.excel.python');
        $skrip = (string) config('services.excel.script');

        if ($python === '' || ! is_file($skrip)) {
            $this->error('Konfigurasi services.excel belum benar (python atau script kosong).');

            return null;
        }

        if (! is_file($python)) {
            $this->error("Python tidak ditemukan di {$python}. Ubah EXCEL_PYTHON di .env atau jalankan perintah dari komputer lain.");

            return null;
        }

        $keluaran = storage_path('app/roster-import.csv');

        $proses = @proc_open(
            [$python, $skrip, $berkas, $keluaran],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipa,
        );

        if (! is_resource($proses)) {
            $this->error("Tidak bisa menjalankan Python: {$python}");

            return null;
        }

        $keluaranTeks = trim((string) stream_get_contents($pipa[1]));
        $galat = trim((string) stream_get_contents($pipa[2]));
        fclose($pipa[1]);
        fclose($pipa[2]);
        $kodeKeluar = proc_close($proses);

        if ($kodeKeluar !== 0 || ! is_file($keluaran)) {
            $this->error('Konversi .xlsx -> .csv gagal.');
            $this->line($galat !== '' ? $galat : $keluaranTeks);

            return null;
        }

        $this->line('Berkas .xlsx dikonversi ke CSV oleh Python (openpyxl).');

        return $keluaran;
    }

    // -------------------------------------------------------------- roster

    /**
     * Baca daftar mahasiswa dari CSV hasil konversi.
     *
     * Baris judul DICARI, bukan diasumsikan di baris pertama: berkas Excel
     * asli punya beberapa baris keterangan sebelum header.
     *
     * @return array<string, array{nim: string, nama: string, asal: ?string, jenis: ?string, kelas: string}>
     */
    private function bacaRoster(string $csv): array
    {
        $isi = (string) file_get_contents($csv);
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;

        $this->barisCsv = [];
        $this->pecahCsv($isi, $this->tebakPemisah($isi));

        $indeksHeader = null;
        $header = null;

        foreach ($this->barisCsv as $i => $kolom) {
            // Header harus punya NIM DAN kolom kelas sekaligus. Berkas Excel
            // asli punya baris keterangan di atas header yang kebetulan
            // memuat kata "NIM" (mis. "DAFTAR ... BERDASARKAN NIM"), jadi
            // mencari hanya kolom NIM akan salah menduga baris itu header.
            if ($this->kolomNim($kolom) !== null && $this->kolomBerlabel($kolom, ['kelas', 'kelompok']) !== null) {
                $header = $kolom;
                $indeksHeader = $i;

                break;
            }
        }

        if ($header === null) {
            $this->error('Header dengan kolom NIM tidak ditemukan di berkas CSV.');

            return [];
        }

        $indeksNim = $this->kolomNim($header);
        $indeksNama = $this->kolomBerlabel($header, ['nama']);
        $indeksKelas = $this->kolomBerlabel($header, ['kelas', 'kelompok']);

        if ($indeksKelas === null) {
            $this->error('Header dengan kolom kelas asal tidak ditemukan di berkas CSV.');

            return [];
        }

        $hasil = [];
        $duplikat = [];

        foreach (array_slice($this->barisCsv, $indeksHeader + 1) as $kolom) {
            $nim = preg_replace('/\D/', '', (string) ($kolom[$indeksNim] ?? '')) ?? '';

            if (strlen($nim) < 8) {
                continue;
            }

            if (isset($hasil[$nim])) {
                $duplikat[$nim] = true;

                continue;
            }

            $label = trim((string) ($kolom[$indeksKelas] ?? ''));
            [$asal, $jenis] = $this->pecahLabelKelas($label);

            $hasil[$nim] = [
                'nim' => $nim,
                'nama' => trim((string) ($indeksNama === null ? '' : ($kolom[$indeksNama] ?? ''))),
                'asal' => $asal,
                'jenis' => $jenis,
                'kelas' => $label !== '' ? $label : self::KODE_TANPA_ASAL,
            ];
        }

        if ($duplikat !== []) {
            $this->warn(sprintf(
                '%d NIM muncul lebih dari sekali; hanya baris pertama yang dipakai.',
                count($duplikat)
            ));
        }

        return $hasil;
    }

    private function tebakPemisah(string $isi): string
    {
        $baris = strtok($isi, "\r\n") ?: '';

        foreach (["\t", ';', ','] as $kandidat) {
            if (substr_count($baris, $kandidat) >= 1) {
                return $kandidat;
            }
        }

        return ',';
    }

    /** @param  list<string>  $kolom */
    private function kolomNim(array $kolom): ?int
    {
        return $this->kolomBerlabel($kolom, ['nim']);
    }

    /**
     * @param  list<string>  $kolom
     * @param  list<string>  $kunci
     */
    private function kolomBerlabel(array $kolom, array $kunci): ?int
    {
        foreach ($kolom as $i => $judul) {
            $judul = mb_strtolower(trim((string) $judul));

            foreach ($kunci as $k) {
                if ($judul !== '' && str_contains($judul, mb_strtolower($k))) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Pecah CSV yang aman terhadap tanda kutip dan baris baru di dalam sel.
     */
    private function pecahCsv(string $isi, string $pemisah): void
    {
        $sel = '';
        $dalamKutip = false;
        $selKolom = [];
        $panjang = strlen($isi);

        for ($i = 0; $i < $panjang; $i++) {
            $karakter = $isi[$i];
            $berikutnya = $isi[$i + 1] ?? '';

            if ($dalamKutip) {
                if ($karakter === '"' && $berikutnya === '"') {
                    $sel .= '"';
                    $i++;

                    continue;
                }

                if ($karakter === '"') {
                    $dalamKutip = false;

                    continue;
                }

                $sel .= $karakter;

                continue;
            }

            if ($karakter === '"') {
                $dalamKutip = true;

                continue;
            }

            if ($karakter === $pemisah) {
                $selKolom[] = $sel;
                $sel = '';

                continue;
            }

            if ($karakter === "\n" || $karakter === "\r") {
                if ($karakter === "\r" && $berikutnya === "\n") {
                    $i++;
                }

                $selKolom[] = $sel;
                $sel = '';

                if (trim(implode('', $selKolom)) !== '') {
                    $this->barisCsv[] = $selKolom;
                }

                $selKolom = [];

                continue;
            }

            $sel .= $karakter;
        }

        $selKolom[] = $sel;

        if (trim(implode('', $selKolom)) !== '') {
            $this->barisCsv[] = $selKolom;
        }
    }

    /**
     * "Kelas A Pagi" menjadi ["A", "Pagi"].
     *
     * Ditulis longgar karena berkas dibuat manusia: "A Pagi", "Kelas A Pagi",
     * dan "a-pagi" semuanya diterima. Nilai yang tidak dikenali menghasilkan
     * null yang berarti "tanpa asal"/"tanpa jenis" — bukan ditebak.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function pecahLabelKelas(string $label): array
    {
        if ($label === '') {
            return [null, null];
        }

        $jenis = preg_match('/\b(pagi|malam)\b/iu', $label, $m) === 1
            ? ucfirst(mb_strtolower($m[1]))
            : null;

        $sisa = str_ireplace((string) $jenis, '', $label);

        $asal = preg_match('/\b([a-z])\b/iu', $sisa, $m) === 1
            ? strtoupper($m[1])
            : null;

        return [$asal, $jenis];
    }

    // ------------------------------------------------------ proses per MK

    /** @return Collection<int, MataKuliah> */
    private function cariMataKuliah(): Collection
    {
        $kode = $this->option('mata-kuliah');

        return MataKuliah::query()
            ->when($kode !== [], fn ($q) => $q->whereIn('kode', $kode))
            // Hanya MK yang sudah punya kelas: dari situ tahun akademik dan
            // semester diambil, dan itu yang menentukan jendela analisis.
            ->whereHas('kelas')
            ->orderBy('kode')
            ->get();
    }

    /**
     * @param  array<string, array{nim: string, nama: string, asal: ?string, jenis: ?string, kelas: string}>  $roster
     */
    private function prosesMataKuliah(MataKuliah $mk, array $roster, bool $dryRun): void
    {
        $kelasLama = $mk->kelas()->orderBy('id')->get();
        $acuan = $kelasLama->first();

        if ($acuan === null) {
            return;
        }

        $this->line("{$mk->kode} - {$mk->nama}");

        // Kelompok roster: label kelas => daftar mahasiswa.
        $kelompok = [];

        foreach ($roster as $mhs) {
            $kelompok[$mhs['kelas']][] = $mhs;
        }

        $idLama = $kelasLama->pluck('id')->all();

        // Mahasiswa lama yang NIM-nya tidak ada di roster.
        $tanpaDaftar = array_values(array_diff(
            DB::table('kelas_mahasiswa')->whereIn('kelas_id', $idLama)->distinct()->pluck('nim')->all(),
            array_keys($roster),
        ));

        $perbaruiNama = (bool) $this->option('perbarui-nama');
        $ringkasan = [];
        $idKelasBaru = [];

        foreach ($kelompok as $label => $anggota) {
            $kelas = $dryRun
                ? null
                : $this->siapkanKelas($mk, $acuan, $label, $this->asalDari($anggota), $this->jenisDari($anggota));

            if (! $dryRun) {
                $this->taruhMahasiswa($kelas, $anggota, $idLama, $perbaruiNama);
                $idKelasBaru[] = $kelas->id;
            }

            $ringkasan[] = [$label, count($anggota), $dryRun ? '(dry-run)' : 'diimpor'];
        }

        if ($tanpaDaftar !== []) {
            if (! $dryRun) {
                $kelas = $this->siapkanKelas($mk, $acuan, self::KODE_TANPA_ASAL, null, null);
                $this->taruhMahasiswa($kelas, $this->dataTanpaDaftar($tanpaDaftar, $idLama), $idLama, false);
                $idKelasBaru[] = $kelas->id;
            }

            $ringkasan[] = [self::KODE_TANPA_ASAL, count($tanpaDaftar), $dryRun ? '(dry-run)' : 'diimpor'];
        }

        $this->table(['Kelas', 'Mahasiswa', 'Status'], $ringkasan);

        if ($tanpaDaftar !== []) {
            $this->line(sprintf(
                '%d mahasiswa tidak ada di roster, ditempatkan di "%s".',
                count($tanpaDaftar),
                self::KODE_TANPA_ASAL
            ));
        }

        if ($dryRun) {
            $this->newLine();

            return;
        }

        $this->pindahkanPenugasan($mk, $kelasLama, $idKelasBaru);
        $kosong = $this->hapusKelasLama($mk, $kelasLama);

        if ($kosong !== []) {
            $this->warn(sprintf(
                'Kelas lama yang kini kosong: %s. Tidak dihapus otomatis; pakai --hapus-kelas-lama bila memang tidak dipakai lagi.',
                implode(', ', $kosong)
            ));
        }

        $this->newLine();
    }

    /**
     * Pindahkan penugasan dosen dari kelas lama ke kelas hasil pecahan.
     *
     * Tanpa ini, memecah satu kelas jadi delapan membuat SEMUA penugasan
     * dosen menunjuk ke kelas yang sudah kosong, dan dashboard mereka
     * menjadi kosong tanpa sebab yang jelas.
     *
     * Sumbernya hanya kelas LAMA (yang `asal_kelas`-nya masih kosong), bukan
     * kelas hasil pecahan. Kalau kelas baru ikut jadi sumber, penugasan yang
     * sengaja hanya untuk "A Pagi" akan ikut menyebar ke semua kelas saat
     * perintah dijalankan ulang.
     *
     * @param  Collection<int, Kelas>  $kelasLama
     * @param  list<int>  $idKelasBaru
     */
    private function pindahkanPenugasan(MataKuliah $mk, Collection $kelasLama, array $idKelasBaru): void
    {
        if ($this->option('tanpa-pindahkan-penugasan') || $idKelasBaru === []) {
            return;
        }

        $idWadah = $kelasLama
            ->filter(fn (Kelas $k) => $k->asal_kelas === null)
            ->pluck('id')
            ->all();

        if ($idWadah === []) {
            return;
        }

        $baris = DB::table('kelas_dosen')->whereIn('kelas_id', $idWadah)->get();

        if ($baris->isEmpty()) {
            return;
        }

        $pindah = 0;

        foreach ($baris as $tritas) {
            foreach ($idKelasBaru as $idBaru) {
                $sudah = DB::table('kelas_dosen')
                    ->where('kelas_id', $idBaru)
                    ->where('user_id', $tritas->user_id)
                    ->exists();

                if ($sudah) {
                    continue;
                }

                DB::table('kelas_dosen')->insert([
                    'kelas_id' => $idBaru,
                    'user_id' => $tritas->user_id,
                    'peran' => $tritas->peran,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $pindah++;
            }
        }

        DB::table('kelas_dosen')->whereIn('kelas_id', $idWadah)->delete();

        $this->line(sprintf(
            'Penugasan: %d penugasan pada kelas lama %s diteruskan ke %d kelas hasil pecahan.',
            $baris->count(),
            $mk->kode,
            count($idKelasBaru)
        ).sprintf(' (+%d baris baru.)', $pindah));
    }

    /**
     * Hapus kelas lama yang kosong, kalau diminta.
     *
     * @param  Collection<int, Kelas>  $kelasLama
     * @return list<string>
     */
    private function hapusKelasLama(MataKuliah $mk, Collection $kelasLama): array
    {
        $kosong = $kelasLama
            ->filter(fn (Kelas $k) => $k->mahasiswa()->count() === 0 && $k->asal_kelas === null)
            ->all();

        if (! $this->option('hapus-kelas-lama') || $kosong === []) {
            return array_map(fn (Kelas $k) => $k->kode, $kosong);
        }

        foreach ($kosong as $kelas) {
            // Dosen di kelas ini sudah dipindahkan pada langkah sebelumnya.
            DB::table('kelas_dosen')->where('kelas_id', $kelas->id)->delete();
            $kelas->delete();
        }

        $this->line(sprintf(
            'Kelas lama %s yang kosong sudah dihapus (%d kelas).',
            $mk->kode,
            count($kosong)
        ));

        return [];
    }

    /**
     * @param  list<array{nim: string, asal: ?string, jenis: ?string}>  $anggota
     */
    private function asalDari(array $anggota): ?string
    {
        return $this->nilaiUnik($anggota, 'asal');
    }

    /** @param  list<array{nim: string, asal: ?string, jenis: ?string}>  $anggota */
    private function jenisDari(array $anggota): ?string
    {
        return $this->nilaiUnik($anggota, 'jenis');
    }

    /**
     * Nilai kolom yang sama untuk semua anggota kelompok.
     *
     * Kalau tidak seragam, ambil yang paling sering muncul. retrieve tidak
     * boleh diam-diam jadi asal/jenis palsu.
     *
     * @param  list<array<string, mixed>>  $anggota
     */
    private function nilaiUnik(array $anggota, string $kolom): ?string
    {
        $hitung = [];

        foreach ($anggota as $item) {
            $nilai = $item[$kolom] ?? null;

            if ($nilai === null) {
                continue;
            }

            $hitung[$nilai] = ($hitung[$nilai] ?? 0) + 1;
        }

        if ($hitung === []) {
            return null;
        }

        arsort($hitung);

        return (string) array_key_first($hitung);
    }

    /**
     * Cari kelas untuk satu kelompok roster, buat kalau belum ada.
     */
    private function siapkanKelas(
        MataKuliah $mk,
        Kelas $acuan,
        string $label,
        ?string $asal,
        ?string $jenis
    ): Kelas {
        $kode = $this->kodeKelas($label, $asal, $jenis);

        $kelas = $mk->kelas()->where('kode', $kode)->first();

        if ($kelas !== null) {
            // Kelas lama yang masih dipakai: lengkapi kolom baru kalau kosong.
            if ($kelas->asal_kelas === null && $asal !== null) {
                $kelas->update(['asal_kelas' => $asal, 'jenis_kelas' => $jenis]);
            }

            return $kelas;
        }

        return $mk->kelas()->create([
            'kode' => $kode,
            'nama' => $label,
            // Tahun akademik & semester SALIN dari kelas lama. Kalau
            // dibiarkan berbeda, jendela analisis satu semester ikut bergeser
            // dan seluruh angka historis berubah.
            'tahun_akademik' => $acuan->tahun_akademik,
            'semester' => $acuan->semester,
            'prodi_id' => $acuan->prodi_id,
            'asal_kelas' => $asal,
            'jenis_kelas' => $jenis,
        ]);
    }

    /**
     * Kode kelas dari label: "Kelas A Pagi" menjadi "A Pagi".
     *
     * Kode adalah kunci unik di dalam satu mata kuliah, jadi harus ringkas
     * dan tidak memuat kata "Kelas" yang tidak menambah informasi.
     */
    private function kodeKelas(string $label, ?string $asal, ?string $jenis): string
    {
        $kode = trim(($asal ?? '').' '.($jenis ?? ''));

        return $kode === '' ? self::KODE_TANPA_ASAL : $kode;
    }

    /**
     * Pindahkan anggota kelompok ke kelasnya, dan keluarkan dari kelas lain
     * pada mata kuliah yang sama (satu mahasiswa hanya boleh satu kelas).
     *
     * @param  list<array<string, mixed>>  $anggota
     * @param  list<int>  $idKelasLama
     */
    private function taruhMahasiswa(
        Kelas $kelas,
        array $anggota,
        array $idKelasLama,
        bool $perbaruiNama
    ): void {
        $nim = array_column($anggota, 'nim');

        // Nama yang sudah tersimpan lebih dulu, supaya tidak hilang saat
        // roster tidak menyertakan nama atau tidak ada --perbarui-nama.
        $tersimpan = Enrollment::query()
            ->whereIn('nim', $nim)
            ->whereIn('kelas_id', $idKelasLama)
            ->pluck('nama', 'nim');

        // Bersihkan supaya tidak ada mahasiswa yang tercatat di dua kelas
        // dalam mata kuliah yang sama.
        Enrollment::query()
            ->whereIn('nim', $nim)
            ->whereIn('kelas_id', $idKelasLama)
            ->delete();

        foreach ($anggota as $mhs) {
            $dariRoster = $mhs['nama'] ?? null;

            // Nama yang sudah tersimpan tidak ditimpa kecuali diminta
            // --perbarui-nama. Nama dari roster hanya dipakai sebagai
            // cadangan kalau nama lama kosong.
            $nama = $perbaruiNama && filled($dariRoster)
                ? $dariRoster
                : ($tersimpan[$mhs['nim']] ?? $dariRoster);

            Enrollment::updateOrCreate(
                ['kelas_id' => $kelas->id, 'nim' => $mhs['nim']],
                ['nama' => $nama, 'terdaftar_pada' => now()],
            );
        }
    }

    /**
     * Bentuk data untuk mahasiswa yang tidak ada di roster.
     *
     * @param  list<string>  $nim
     * @return list<array{nim: string, nama: ?string, asal: null, jenis: null, kelas: string}>
     */
    private function dataTanpaDaftar(array $nim, array $idKelasLama): array
    {
        $ada = Enrollment::query()
            ->whereIn('nim', $nim)
            ->whereIn('kelas_id', $idKelasLama)
            ->pluck('nama', 'nim');

        $hasil = [];

        foreach ($nim as $satu) {
            $hasil[] = [
                'nim' => $satu,
                // Nama dari kelas lama dipertahankan; perintah ini tidak
                // mengarang nama untuk mahasiswa yang tidak ada di roster.
                'nama' => $ada[$satu] ?? null,
                'asal' => null,
                'jenis' => null,
                'kelas' => self::KODE_TANPA_ASAL,
            ];
        }

        return $hasil;
    }
}
