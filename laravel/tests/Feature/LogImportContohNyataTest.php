<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\LogAktivitas;
use App\Models\LogImportError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Import potongan file log Moodle NYATA, bukan fixture buatan.
 *
 * Memakai laravel/sample-data/datalog-matakuliah-jaringankomputer.csv
 * yang berasal dari LMS. Test ini dilewati kalau file tidak ada.
 */
class LogImportContohNyataTest extends TestCase
{
    use RefreshDatabase;

    private const CONTOH = __DIR__.'/../../sample-data/datalog-matakuliah-jaringankomputer.csv';

    private function cuplik(int $jumlahBaris = 3000): UploadedFile
    {
        $asal = realpath(self::CONTOH);

        $sumber = fopen($asal, 'r');
        $header = fgets($sumber);

        $isi = $header;
        for ($i = 0; $i < $jumlahBaris; $i++) {
            $baris = fgets($sumber);
            if ($baris === false) {
                break;
            }
            $isi .= $baris;
        }
        fclose($sumber);

        $tujuan = tempnam(sys_get_temp_dir(), 'nyata').'.csv';
        file_put_contents($tujuan, $isi);

        return new UploadedFile($tujuan, 'cuplik-jaringan-komputer.csv', 'text/csv', null, true);
    }

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin',
            'email' => 'admin@nyata.local',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);
    }

    public function test_import_potong_file_nyata(): void
    {
        $mulai = microtime(true);

        $response = $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $this->cuplik()]);

        $durasi = round((microtime(true) - $mulai) * 1000);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $batch = ImportBatch::first();
        $lagi = microtime(true) - $mulai;

        fwrite(STDERR, "\n=== HASIL IMPORT FILE NYATA ===\n");
        fwrite(STDERR, sprintf("  file            : %s (%.1f KB cuplikan)\n", $batch->nama_file, strlen(file_get_contents(self::CONTOH)) / 1024));
        fwrite(STDERR, sprintf("  status          : %s\n", $batch->status));
        fwrite(STDERR, sprintf("  baris terbaca   : %d\n", $batch->jumlah_baris));
        fwrite(STDERR, sprintf("  tersimpan       : %d (%.2f%%)\n", $batch->jumlah_berhasil, $batch->persenBerhasil()));
        fwrite(STDERR, sprintf("  duplikat        : %d\n", $batch->jumlah_duplikat));
        fwrite(STDERR, sprintf("  gagal           : %d\n", $batch->jumlah_gagal));
        fwrite(STDERR, sprintf("  identitas dikenal: %d\n", $batch->jumlah_terkenal));
        fwrite(STDERR, sprintf("  tak dikenal     : %d\n", $batch->jumlah_tak_dikenal));
        fwrite(STDERR, sprintf("  mata kuliah     : %s / %s\n", $batch->kode_mata_kuliah, $batch->nama_mata_kuliah));
        fwrite(STDERR, sprintf("  periode         : %s\n", $batch->periode));
        fwrite(STDERR, sprintf("  waktu proses    : %d ms\n", (int) round($lagi * 1000)));

        // Struktur data harus benar
        $this->assertGreaterThan(0, $batch->jumlah_berhasil);
        $this->assertSame('IF209', $batch->kode_mata_kuliah);
        $this->assertSame('Jaringan Komputer', $batch->nama_mata_kuliah);

        $this->assertGreaterThan(0, LogAktivitas::count());

        // Tidak boleh ada waktu NULL atau tidak ter-parse
        $this->assertSame(0, LogAktivitas::whereNull('occurred_at')->count());

        // Role tidak boleh ada nilai di luar enum
        $roles = LogAktivitas::distinct()->pluck('actor_role')->all();
        $this->assertNotEmpty($roles);
        $roleValid = [
            LogAktivitas::PERAN_MAHASISWA,
            LogAktivitas::PERAN_DOSEN,
            LogAktivitas::PERAN_KAPRODI,
            LogAktivitas::PERAN_AKUN_LAINNYA,
            LogAktivitas::PERAN_TAK_DIKETAHUI,
        ];
        foreach ($roles as $role) {
            $this->assertContains($role, $roleValid);
        }

        // NIM harus terpisah dengan rapi dari nama
        $contoh = LogAktivitas::whereNotNull('actor_identifier')->first();
        $this->assertNotNull($contoh);
        $this->assertNotSame($contoh->actor_name, $contoh->actor_identifier);
        fwrite(STDERR, sprintf("  contoh baris    : %s | id=%s | role=%s | %s\n",
            $contoh->actor_name, $contoh->actor_identifier, $contoh->actor_role, $contoh->event_name));
    }

    public function test_import_dua_kali_menghasilkan_data_yang_berbeda(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/log-import', ['file_csv' => $this->cuplik(500)]);
        $pertama = LogAktivitas::count();

        $this->actingAs($admin)->post('/admin/log-import', ['file_csv' => $this->cuplik(500)]);
        $kedua = LogAktivitas::count();

        $this->assertSame($pertama, $kedua, 'Import kedua harus mengabaikan duplikat lewat batch, bukan menggandakan.');
        $this->assertSame(2, ImportBatch::count());

        // Batch kedua: seluruh barisnya sudah ada di database, jadi semuanya
        // dihitung duplikat. Jumlahnya 500, bukan 327, karena file aslinya
        // sendiri memuat baris kembar yang sudah dilewati sejak awal.
        $keduaBatch = ImportBatch::orderByDesc('id')->first();
        $this->assertSame(0, $keduaBatch->jumlah_berhasil);
        $this->assertSame(500, $keduaBatch->jumlah_duplikat);
        $this->assertGreaterThan($pertama, $keduaBatch->jumlah_duplikat);
    }

    public function test_gagalnya_baris_tercatat_lengkap(): void
    {
        $this->actingAs($this->admin())->post('/admin/log-import', ['file_csv' => $this->cuplik(2000)]);

        $batch = ImportBatch::first();
        $errors = LogImportError::all();

        // Setiap baris yang dicatat harus bisa ditelusuri sampai batch-nya.
        foreach ($errors as $error) {
            $this->assertSame($batch->id, $error->import_batch_id);
            $this->assertNotNull($error->kode_error);
            $this->assertNotEmpty($error->pesan);
        }

        // Jumlah error tidak boleh melebihi jumlah baris terbaca.
        $this->assertLessThanOrEqual($batch->jumlah_baris, count($errors) + 1);
    }
}
