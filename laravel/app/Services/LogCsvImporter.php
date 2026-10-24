<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\LogAktivitas;
use App\Models\LogImportError;
use App\Models\User;
use App\Services\Moodle\MoodleLogParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mengimpor file log LMS Moodle ke tabel log_aktivitas (FR-02, FR-03, FR-10).
 *
 * Prinsip yang dipegang:
 *
 * - Peran TIDAK ditebak dari panjang digit identifier. Pada file nyata,
 *   dosen pengampu punya id 6, 10, dan 18 digit sekaligus. Peran
 *   ditentukan dari kecocokan dengan tabel users.
 *
 * - Baris duplikat dicegah oleh kolom sidik_jari yang UNIQUE di level
 *   database, jadi berlaku lintas batch: file yang diunggah ulang, atau
 *   dua file bulanan yang saling tumpang tindih, tidak menggandakan data.
 *   Sidik jarinya tidak memakai detik, karena file sumber hanya presisi
 *   menit, sehingga dua kejadian nyata dalam menit yang sama tetap
 *   tersimpan terpisah selama event-nya berbeda.
 *
 * - Setiap baris bermasalah dicatat di log_import_errors supaya bisa
 *   ditelusuri, bukan hanya hilang.
 */
class LogCsvImporter
{
    /** Jumlah baris yang diproses per sekali insert. */
    private const UKURAN_CHUNK = 1000;

    /** Batas jumlah baris error yang disimpan per batch. */
    private const BATAS_ERROR = 500;

    public function __construct(private readonly MoodleLogParser $parser) {}

    /**
     * Jalankan import. Mengembalikan batch berisi ringkasan hasil.
     */
    public function import(string $pathToFile, User $admin): ImportBatch
    {
        $batch = $this->buatBatch($pathToFile, $admin);

        $handle = @fopen($pathToFile, 'r');
        if ($handle === false) {
            return $this->gagalkan($batch, "File tidak bisa dibuka: {$pathToFile}");
        }

        try {
            return $this->proses($batch, $handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function proses(ImportBatch $batch, $handle): ImportBatch
    {
        $pemisah = $this->tebakPemisah($handle);
        $header = fgetcsv($handle, 0, $pemisah);
        if ($header === false || $header === null) {
            return $this->gagalkan($batch, 'File kosong: tidak ada baris header.');
        }

        $peta = $this->parser->petakanHeader(array_map('strval', $header));

        $kurang = $this->parser->kolomWajib($peta);
        if ($kurang !== []) {
            $this->catatError($batch, [
                'nomor_baris' => 1,
                'kode_error' => LogImportError::KODE_HEADER_TIDAK_VALID,
                'pesan' => 'Kolom wajib tidak ditemukan: '.implode(', ', $kurang),
                'nilai_asal' => substr(implode(';', array_map('strval', $header)), 0, 255),
            ]);

            return $this->gagalkan($batch, 'Format file tidak dikenali sebagai log report Moodle.');
        }

        $batch->forceFill([
            'kode_mata_kuliah' => null,
            'status' => ImportBatch::STATUS_DIPROSES,
            'started_at' => Carbon::now(),
        ])->save();

        $totalBaris = 0;
        $valid = 0;
        $terinsert = 0;
        $gagal = 0;
        $barisKe = 1;

        $antrean = [];
        $antreanError = [];
        $kodeMk = null;
        $namaMk = null;
        $peranCache = [];

        while (($baris = fgetcsv($handle, 0, $pemisah)) !== false) {
            $barisKe++;
            $totalBaris++;

            // Baris yang seluruh kolomnya kosong diabaikan
            if ($this->barisKosong($baris)) {
                continue;
            }

            $hasil = $this->parser->parseBaris($baris, $peta);

            if (! $hasil['valid']) {
                $gagal++;
                if (count($antreanError) < self::BATAS_ERROR) {
                    $antreanError[] = [
                        'import_batch_id' => $batch->id,
                        'nomor_baris' => $barisKe,
                        'kode_error' => $this->kodeError($hasil['errors']),
                        'pesan' => implode(' ', $hasil['errors']),
                        'nilai_asal' => substr(implode(';', array_map('strval', $baris)), 0, 255),
                    ];
                }

                continue;
            }

            $data = $hasil['data'];
            $valid++;

            // Sidik jari dihitung di sini dan disimpan ke kolom UNIQUE.
            // Duplikasi ditangani MySQL saat insert, jadi berlaku juga
            // saat file yang sama diunggah ulang di batch berbeda.
            $data['sidik_jari'] = LogAktivitas::sidikJari($data);

            // Peran diambil dari tabel users, bukan dari panjang digit
            $peran = $this->tentukanPeran($data['actor_identifier'], $peranCache);

            $data['import_batch_id'] = $batch->id;
            $data['actor_role'] = $peran['role'];
            $data['actor_user_id'] = $peran['user_id'];

            $kodeMk ??= $data['kode_mata_kuliah'];
            $namaMk ??= $data['nama_mata_kuliah'];

            $antrean[] = $data;

            if (count($antrean) >= self::UKURAN_CHUNK) {
                $terinsert += $this->simpan($antrean);
                $antrean = [];
            }

            if (count($antreanError) >= self::BATAS_ERROR) {
                $this->simpanError($antreanError);
                $antreanError = [];
            }
        }

        if ($antrean !== []) {
            $terinsert += $this->simpan($antrean);
        }
        if ($antreanError !== []) {
            $this->simpanError($antreanError);
        }

        // Log Moodle hanya menyebut kode mata kuliah pada baris bertipe
        // konteks "Course: Nama/KODE". Sisanya (Assignment, Quiz, File,
        // URL, dan friends) tetap milik course yang sama, tapi kolom
        // kodenya kosong — dan tanpa itu semua statistik jadi buta 69%.
        $dilengkapi = $this->lengkapiKodeMataKuliah($batch);

        // Baris valid, tapi sudah ada di database dari batch sebelumnya
        $duplikat = max(0, $valid - $terinsert);
        $berhasil = $terinsert;

        $terkenal = $this->hitungTerknown($batch->id);

        $batch->forceFill([
            'kode_mata_kuliah' => $kodeMk,
            'nama_mata_kuliah' => $namaMk,
            'periode' => $this->tebakPeriode($batch),
            'jumlah_baris' => $totalBaris,
            'jumlah_berhasil' => $berhasil,
            'jumlah_duplikat' => $duplikat,
            'jumlah_gagal' => $gagal,
            'jumlah_terkenal' => $terkenal,
            'jumlah_tak_dikenal' => max(0, $berhasil - $terkenal),
            'status' => $berhasil > 0 ? ImportBatch::STATUS_BERHASIL : ImportBatch::STATUS_GAGAL,
            'pesan' => $this->ringkas($totalBaris, $berhasil, $duplikat, $gagal).($dilengkapi > 0
                ? sprintf(' · %s baris dilengkapi kode MK dari course yang sama', number_format($dilengkapi, 0, ',', '.'))
                : ''),
            'finished_at' => Carbon::now(),
        ])->save();

        return $batch->refresh();
    }

    /**
     * Isi kode mata kuliah pada baris yang konteksnya bukan "Course:".
     *
     * Alasan ini boleh dilakukan: file log yang diimpor adalah laporan
     * PER mata kuliah, dan di dalam satu file hanya ada satu kode mata
     * kuliah. Kalau ternyata ada dua kode atau lebih, metode ini berhenti
     * dan mengembalikan 0 — tidak menebak.
     *
     * @return int jumlah baris yang diperbarui
     */
    public function lengkapiKodeMataKuliah(ImportBatch $batch): int
    {
        $kodeTersedia = DB::table('log_aktivitas')
            ->where('import_batch_id', $batch->id)
            ->whereNotNull('kode_mata_kuliah')
            ->where('kode_mata_kuliah', '<>', '')
            ->distinct()
            ->pluck('kode_mata_kuliah');

        // Tidak ada kode sama sekali, atau lebih dari satu: jangan menebak.
        if ($kodeTersedia->count() !== 1) {
            return 0;
        }

        $kode = (string) $kodeTersedia->first();

        $nama = DB::table('log_aktivitas')
            ->select('nama_mata_kuliah')
            ->selectRaw('COUNT(*) as jumlah')
            ->where('import_batch_id', $batch->id)
            ->where('kode_mata_kuliah', $kode)
            ->whereNotNull('nama_mata_kuliah')
            ->where('nama_mata_kuliah', '<>', '')
            ->groupBy('nama_mata_kuliah')
            ->orderByDesc('jumlah')
            ->value('nama_mata_kuliah');

        $perubahan = ['kode_mata_kuliah' => $kode];

        if (is_string($nama) && $nama !== '') {
            $perubahan['nama_mata_kuliah'] = $nama;
        }

        return DB::table('log_aktivitas')
            ->where('import_batch_id', $batch->id)
            ->where(function ($q) {
                $q->whereNull('kode_mata_kuliah')->orWhere('kode_mata_kuliah', '');
            })
            ->update($perubahan);
    }

    /**
     * Tentukan peran seorang pelaku aktivitas.
     *
     * Logikanya dibalik: akun yang ada HANYA berisi orang yang benar-benar
     * masuk ke sistem, yaitu Admin, Dosen, dan Kaprodi. Mahasiswa tidak
     * punya akun sama sekali, sehingga identifier-nya tidak akan pernah
     * ditemukan di tabel users.
     *
     * Maka aturannya:
     *   - identifier cocok dengan akun       -> peran dari akun itu
     *   - identifier ada tapi tidak cocok     -> mahasiswa
     *     (bisa dosen yang belum didaftarkan, jadi perlu diperiksa)
     *   - identifier sama sekali tidak terbaca -> tak_diketahui
     *
     * @param  array<string, array{role: string, user_id: int|null}>  $cache
     * @return array{role: string, user_id: int|null}
     */
    private function tentukanPeran(?string $identifier, array &$cache): array
    {
        if ($identifier === null || $identifier === '') {
            return ['role' => LogAktivitas::PERAN_TAK_DIKETAHUI, 'user_id' => null];
        }

        if (isset($cache[$identifier])) {
            return $cache[$identifier];
        }

        $user = User::where('nomor_induk', $identifier)->first();

        $hasil = $user === null
            ? ['role' => LogAktivitas::PERAN_MAHASISWA, 'user_id' => null]
            : ['role' => $this->normalisasiPeran($user->role), 'user_id' => $user->id];

        return $cache[$identifier] = $hasil;
    }

    private function normalisasiPeran(?string $peran): string
    {
        return match ($peran) {
            'dosen' => LogAktivitas::PERAN_DOSEN,
            'kaprodi' => LogAktivitas::PERAN_KAPRODI,
            'mahasiswa' => LogAktivitas::PERAN_MAHASISWA,
            'admin' => LogAktivitas::PERAN_AKUN_LAINNYA,
            default => LogAktivitas::PERAN_TAK_DIKETAHUI,
        };
    }

    private function buatBatch(string $pathToFile, User $admin): ImportBatch
    {
        $path = null;
        try {
            $path = Storage::disk('local')->putFile('log-lms', new UploadedFile(
                $pathToFile,
                basename($pathToFile),
                null,
                null,
                true
            ));
        } catch (\Throwable) {
            // Penyimpanan arsip tidak boleh menggagalkan import.
            $path = null;
        }

        return ImportBatch::create([
            'admin_id' => $admin->id,
            'nama_file' => basename($pathToFile),
            'path' => $path,
            'status' => ImportBatch::STATUS_MENUNGGU,
        ]);
    }

    /**
     * Tebak pemisah kolom dari baris header.
     *
     * Moodle memakai ';', tapi file yang pernah dibuka di Excel kadang
     * berubah jadi ','. Karena itu keduanya diperiksa.
     */
    private function tebakPemisah($handle): string
    {
        $baris = fgets($handle);
        if ($baris === false) {
            return ';';
        }
        rewind($handle);

        return $this->parser->tebakPemisah($baris);
    }

    private function barisKosong(array $baris): bool
    {
        foreach ($baris as $nilai) {
            if (trim((string) $nilai) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, array<string, mixed>> $antrean */
    private function simpan(array $antrean): int
    {
        $waktu = Carbon::now();

        // insertOrIgnore: baris dengan sidik_jari yang sudah ada akan
        // dilewati oleh MySQL karena constraint UNIQUE. Ini yang membuat
        // pengunggah ulang file tidak menggandakan data.
        $terinsert = DB::table('log_aktivitas')->insertOrIgnore(array_map(
            fn (array $baris) => $baris + ['created_at' => $waktu, 'updated_at' => $waktu],
            $antrean
        ));

        return $terinsert;
    }

    /** @param array<int, array<string, mixed>> $antrean */
    private function simpanError(array $antrean): void
    {
        $waktu = Carbon::now();

        DB::table('log_import_errors')->insert(array_map(
            fn (array $baris) => $baris + ['created_at' => $waktu, 'updated_at' => $waktu],
            $antrean
        ));
    }

    /** @param array<string, mixed> $error */
    private function catatError(ImportBatch $batch, array $error): void
    {
        DB::table('log_import_errors')->insert($error + [
            'import_batch_id' => $batch->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function kodeError(array $errors): string
    {
        $gabungan = implode(' ', $errors);

        return match (true) {
            str_contains($gabungan, 'Waktu') => LogImportError::KODE_WAKTU_TIDAK_VALID,
            str_contains($gabungan, 'User full name') => LogImportError::KODE_NAMA_KOSONG,
            str_contains($gabungan, 'Event name') => LogImportError::KODE_EVENT_KOSONG,
            default => 'baris_tidak_valid',
        };
    }

    /**
     * Hitung banyaknya identitas yang "dikenal" sistem.
     *
     * Dikenal berarti salah satu dari dua hal:
     *  - identifier-nya cocok dengan akun (dosen, kaprodi, admin)
     *  - NIM-nya sudah tercatat di salah satu kelas
     *
     * Peran 'mahasiswa' sendiri BUKAN bukti bahwa identitasnya dikenal:
     * peran itu hanya berarti "tidak cocok dengan akun dosen manapun".
     * Tanpa akun, semua orang akan berperan mahasiswa.
     *
     * Sisanya (tak_dikenal) perlu ditindaklanjuti Admin: biasanya
     * dosen yang belum didaftarkan, atau mahasiswa yang belum masuk
     * daftar kelas.
     */
    private function hitungTerknown(int $batchId): int
    {
        return LogAktivitas::query()
            ->where('import_batch_id', $batchId)
            ->whereNotNull('actor_identifier')
            ->where(function (Builder $query): void {
                $query->whereIn('actor_role', LogAktivitas::PERAN_TERKENAL)
                    ->orWhereIn('actor_identifier', function ($sub): void {
                        $sub->select('nim')->from('kelas_mahasiswa');
                    });
            })
            ->distinct()
            ->count('actor_identifier');
    }

    private function tebakPeriode(ImportBatch $batch): ?string
    {
        $pertama = DB::table('log_aktivitas')
            ->where('import_batch_id', $batch->id)
            ->orderBy('occurred_at')
            ->value('occurred_at');

        if ($pertama === null) {
            return null;
        }

        return Carbon::parse($pertama)->format('Y-m');
    }

    private function ringkas(int $total, int $berhasil, int $duplikat, int $gagal): string
    {
        return sprintf(
            '%s baris terbaca: %d tersimpan, %d duplikat dilewati, %d gagal.',
            number_format($total, 0, ',', '.'),
            $berhasil,
            $duplikat,
            $gagal,
        );
    }

    private function gagalkan(ImportBatch $batch, string $pesan): ImportBatch
    {
        $batch->forceFill([
            'status' => ImportBatch::STATUS_GAGAL,
            'pesan' => $pesan,
            'finished_at' => Carbon::now(),
        ])->save();

        return $batch->refresh();
    }
}
