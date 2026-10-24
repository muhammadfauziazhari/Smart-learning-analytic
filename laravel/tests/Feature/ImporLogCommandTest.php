<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Services\LogCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perintah `log:impor` dan pelengkapan kode mata kuliah.
 *
 * Latar belakang: log Moodle hanya menyebut kode mata kuliah pada baris
 * bertipe konteks "Course: Nama/KODE". Pada file log asli, 69% baris
 * bertipe Assignment/Quiz/File/URL sehingga kolom kodenya kosong. Kalau
 * tidak dilengkapi, semua statistik mahasiswa jadi menghitung kurang dari
 * seperempat data sebenarnya.
 */
class ImporLogCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin',
            'email' => 'admin@uji.local',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);
    }

    /** @return array<int, string> */
    private function headerLog(): array
    {
        return [
            'Time', 'User full name', 'Affected user', 'Event context',
            'Component', 'Event name', 'Description', 'Origin', 'IP address',
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $baris
     */
    private function tulisCsv(string $path, array $baris): string
    {
        $isi = implode(';', $this->headerLog()).PHP_EOL;

        foreach ($baris as $b) {
            $isi .= implode(';', array_pad($b, 9, '-')).PHP_EOL;
        }

        file_put_contents($path, $isi);

        return $path;
    }

    public function test_perintah_impor_menyimpan_log_dan_melengkapi_kode_mk(): void
    {
        $file = $this->tulisCsv(sys_get_temp_dir().'/log-uji-1.csv', [
            // Baris bertipe Course: satu-satunya yang menyebut kode MK.
            ['14/10/2026 14:50', 'Ririn Anisa Sihite 3312501013', '-', 'Course: Jaringan Komputer/IF209', 'Course', 'Course viewed', '-', 'web', '1'],
            // Dua baris tanpa kode: ikut course yang sama.
            ['14/10/2026 14:51', 'Ririn Anisa Sihite 3312501013', '-', 'Assignment: Pengumpulan Praktikum 1', 'Assignment', 'A submission has been submitted.', '-', 'web', '1'],
            ['14/10/2026 14:52', 'Ririn Anisa Sihite 3312501013', '-', 'Quiz: ATS Teori', 'Quiz', 'Quiz attempt viewed', '-', 'web', '1'],
        ]);

        $this->artisan('log:impor', ['file' => $file])
            ->assertSuccessful();

        $this->assertSame(3, LogAktivitas::count());

        // Ketiganya harus terbaca sebagai IF209, bukan hanya yang Course.
        $tanpaKode = DB::table('log_aktivitas')
            ->where(fn ($q) => $q->whereNull('kode_mata_kuliah')->orWhere('kode_mata_kuliah', ''))
            ->count();

        $this->assertSame(0, $tanpaKode);
        $this->assertSame(3, LogAktivitas::where('kode_mata_kuliah', 'IF209')->count());
        $this->assertSame(
            'Jaringan Komputer',
            LogAktivitas::where('kode_mata_kuliah', 'IF209')->value('nama_mata_kuliah'),
        );

        @unlink($file);
    }

    public function test_file_yang_berdua_kode_mata_kuliah_tidak_ditebak(): void
    {
        $file = $this->tulisCsv(sys_get_temp_dir().'/log-uji-2.csv', [
            ['14/10/2026 14:50', 'Ririn 3312501013', '-', 'Course: Jaringan Komputer/IF209', 'Course', 'Course viewed', '-', 'web', '1'],
            ['14/10/2026 14:51', 'Ririn 3312501013', '-', 'Course: Struktur Data/IF201', 'Course', 'Course viewed', '-', 'web', '1'],
            ['14/10/2026 14:52', 'Ririn 3312501013', '-', 'Assignment: Tugas', 'Assignment', 'A submission has been submitted.', '-', 'web', '1'],
        ]);

        $this->artisan('log:impor', ['file' => $file])->assertSuccessful();

        // Baris tanpa kode tetap kosong: dua kode = tidak boleh ditebak.
        $tanpaKode = DB::table('log_aktivitas')
            ->where(fn ($q) => $q->whereNull('kode_mata_kuliah')->orWhere('kode_mata_kuliah', ''))
            ->count();

        $this->assertSame(1, $tanpaKode);

        @unlink($file);
    }

    public function test_perintah_melengkapi_batch_lama(): void
    {
        $batch = ImportBatch::create([
            'admin_id' => $this->admin->id,
            'nama_file' => 'log-lama.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ]);

        LogAktivitas::create([
            'import_batch_id' => $batch->id,
            'occurred_at' => '2026-05-10 09:00:00',
            'periode' => '2026-05',
            'actor_name' => 'Yohana 3312501008',
            'actor_identifier' => '3312501008',
            'actor_role' => LogAktivitas::PERAN_MAHASISWA,
            'event_name' => 'Course viewed',
            'kode_mata_kuliah' => 'IF209',
            'nama_mata_kuliah' => 'Jaringan Komputer',
            'sidik_jari' => 'sj-lama-1',
        ]);

        LogAktivitas::create([
            'import_batch_id' => $batch->id,
            'occurred_at' => '2026-05-10 09:01:00',
            'periode' => '2026-05',
            'actor_name' => 'Yohana 3312501008',
            'actor_identifier' => '3312501008',
            'actor_role' => LogAktivitas::PERAN_MAHASISWA,
            'event_name' => 'Quiz attempt viewed',
            'kode_mata_kuliah' => null,
            'nama_mata_kuliah' => null,
            'sidik_jari' => 'sj-lama-2',
        ]);

        $this->artisan('log:lengkapi-kode-mk')->assertSuccessful();

        $barisKedua = LogAktivitas::where('sidik_jari', 'sj-lama-2')->first();

        $this->assertSame('IF209', $barisKedua->kode_mata_kuliah);
        $this->assertSame('Jaringan Komputer', $barisKedua->nama_mata_kuliah);
    }

    public function test_file_tidak_ada_ditolak_dengan_berkas_pesan(): void
    {
        $this->artisan('log:impor', ['file' => 'tidak/ada.csv'])
            ->expectsOutputToContain('File tidak ditemukan')
            ->assertFailed();
    }

    public function test_tanpa_akun_admin_import_ditolak(): void
    {
        User::where('id', $this->admin->id)->delete();

        $file = $this->tulisCsv(sys_get_temp_dir().'/log-uji-3.csv', [
            ['14/10/2026 14:50', 'Ririn 3312501013', '-', 'Course: Jaringan Komputer/IF209', 'Course', 'Course viewed', '-', 'web', '1'],
        ]);

        $this->artisan('log:impor', ['file' => $file])
            ->expectsOutputToContain('Tidak ada akun admin')
            ->assertFailed();

        $this->assertSame(0, LogAktivitas::count());

        @unlink($file);
    }

    public function test_admin_yang_diberi_tidak_ikut_dipakai(): void
    {
        User::create([
            'nama' => 'Admin Dua',
            'email' => 'admin2@uji.local',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        $file = $this->tulisCsv(sys_get_temp_dir().'/log-uji-4.csv', [
            ['14/10/2026 14:50', 'Ririn 3312501013', '-', 'Course: Jaringan Komputer/IF209', 'Course', 'Course viewed', '-', 'web', '1'],
        ]);

        $this->artisan('log:impor', ['file' => $file, '--admin' => 'admin2@uji.local'])
            ->assertSuccessful();

        $this->assertSame(
            'admin2@uji.local',
            ImportBatch::latest('id')->first()->admin->email,
        );

        @unlink($file);
    }

    public function test_melengkapi_kode_mengembalikan_nol_saat_tidak_ada_kode_sama_sekali(): void
    {
        $batch = ImportBatch::create([
            'admin_id' => $this->admin->id,
            'nama_file' => 'log-tanpa-kode.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ]);

        LogAktivitas::create([
            'import_batch_id' => $batch->id,
            'occurred_at' => '2026-05-10 09:00:00',
            'periode' => '2026-05',
            'actor_name' => 'Tanpa Kode',
            'actor_role' => LogAktivitas::PERAN_MAHASISWA,
            'event_name' => 'Course viewed',
            'kode_mata_kuliah' => null,
            'sidik_jari' => 'sj-tanpa-kode',
        ]);

        $importer = app(LogCsvImporter::class);

        $this->assertSame(0, $importer->lengkapiKodeMataKuliah($batch));
    }
}
