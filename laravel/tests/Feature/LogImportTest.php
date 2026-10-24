<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\LogAktivitas;
use App\Models\LogImportError;
use App\Models\User;
use App\Services\Moodle\MoodleLogParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FileUkuranPalsu;
use Tests\TestCase;

/**
 * Uji importer log LMS (FR-02, FR-03, FR-10).
 *
 * Fixture diambil dari file log Moodle nyata, supaya format yang diuji
 * sama persis dengan format yang akan datang dari LMS.
 */
class LogImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Time;User full name;Affected user;Event context;Component;Event name;Description;Origin;IP address';

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@test.local'],
            [
                'nama' => 'Admin',
                'password' => 'rahasia123',
                'role' => 'admin',
                'nomor_induk' => '999999999',
            ]
        );
    }

    /** Bangun file CSV sementara dari baris-baris yang diberikan. */
    private function buatCsv(array $baris, bool $bom = true, string $header = self::HEADER): UploadedFile
    {
        $isi = ($bom ? "\xEF\xBB\xBF" : '').$header."\r\n";
        foreach ($baris as $b) {
            $isi .= implode(';', $b)."\r\n";
        }

        $path = tempnam(sys_get_temp_dir(), 'log').'.csv';
        file_put_contents($path, $isi);

        return new UploadedFile($path, 'log-lms.csv', 'text/csv', null, true);
    }

    private function barisValid(array $ubah = []): array
    {
        return array_replace([
            '14/10/2026 14:50',
            'Ririn Anisa Sihite 3312501013',
            '-',
            'Course: Jaringan Komputer/IF209',
            'System',
            'Course viewed',
            'The user with id \'504\' viewed the course with id \'4\'.',
            'web',
            '1,62158E+11',
        ], $ubah);
    }

    // ------------------------------------------------------------- import dasar

    public function test_import_satu_baris_valid(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $this->buatCsv([$this->barisValid()])]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $batch = ImportBatch::first();
        $this->assertSame(ImportBatch::STATUS_BERHASIL, $batch->status);
        $this->assertSame(1, $batch->jumlah_berhasil);
        $this->assertSame(1, $batch->jumlah_baris);

        $this->assertDatabaseHas('log_aktivitas', [
            'occurred_at' => '2026-10-14 14:50:00',
            'periode' => '2026-10',
            'actor_name' => 'Ririn Anisa Sihite 3312501013',
            'actor_identifier' => '3312501013',
            'event_name' => 'Course viewed',
            'kode_mata_kuliah' => 'IF209',
            'nama_mata_kuliah' => 'Jaringan Komputer',
            'component' => 'System',
            'origin' => 'web',
        ]);
    }

    public function test_kolom_ip_tidak_disimpan(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $this->buatCsv([$this->barisValid()])]);

        // Kolomnya memang tidak dibuat sama sekali di migration.
        $this->assertFalse(
            Schema::hasColumn('log_aktivitas', 'ip_address'),
            'Kolom IP harus dihapus, bukan disimpan lalu dibersihkan.'
        );
    }

    public function test_affected_user_kosong_disimpan_null(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $this->buatCsv([$this->barisValid()])]);

        $baris = LogAktivitas::first();
        $this->assertNull($baris->target_identifier);
        $this->assertNull($baris->target_name);
    }

    public function test_affected_user_terbaca_bila_ada(): void
    {
        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => $this->buatCsv([
                $this->barisValid([
                    2 => 'Della Reska Damayanti 3312501011',
                    4 => 'User graded',
                ]),
            ]),
        ]);

        $baris = LogAktivitas::orderByDesc('id')->first();
        $this->assertNotNull($baris);
        $this->assertSame('3312501011', $baris->target_identifier);
        $this->assertSame('Della Reska Damayanti', $baris->target_name);
    }

    // ------------------------------------------------------------- peran

    public function test_peran_dari_tabel_users_bukan_dari_panjang_digit(): void
    {
        // Dosen pengampu pada file nyata punya identifier 6 digit,
        // 10 digit, dan 18 digit sekaligus.
        foreach (['225364', '317258', '198602192014042001'] as $nip) {
            User::create([
                'nama' => 'Dosen '.$nip,
                'email' => $nip.'@test.local',
                'password' => 'rahasia123',
                'role' => 'dosen',
                'nomor_induk' => $nip,
            ]);
        }

        // Identifier yang sama-sama Terdaftar sebagai akun apa pun akan
        // mengikuti peran akun itu, sepanjang digitnya.
        User::create([
            'nama' => 'Aneh',
            'email' => 'aneh@test.local',
            'password' => 'rahasia123',
            'role' => 'dosen',
            'nomor_induk' => '3312501013',
        ]);

        $baris = [];
        foreach (['225364', '317258', '198602192014042001', '3312501013'] as $id) {
            $baris[] = $this->barisValid([1 => 'Orang '.$id.' '.$id]);
        }

        $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $this->buatCsv($baris)]);

        foreach (['225364', '317258', '198602192014042001', '3312501013'] as $nip) {
            $this->assertSame(
                LogAktivitas::PERAN_DOSEN,
                LogAktivitas::where('actor_identifier', $nip)->value('actor_role'),
                "Identifier {$nip} harus mengikuti peran akunnya."
            );
        }
    }

    public function test_identitas_kosong_ditandai_tak_diketahui(): void
    {
        // Identifier tidak terbaca (kolom diisi "-"). Peran lain hanya
        // berlaku kalau identifier-nya ada.
        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => $this->buatCsv([
                $this->barisValid([1 => 'Tidak Diketahui -']),
            ]),
        ]);

        $batch = ImportBatch::first();
        $this->assertSame(0, $batch->jumlah_terkenal);
        $this->assertSame(1, $batch->jumlah_tak_dikenal);
        $this->assertSame(
            LogAktivitas::PERAN_TAK_DIKETAHUI,
            LogAktivitas::first()->actor_role
        );
    }

    public function test_identifier_tanpa_akun_dianggap_mahasiswa(): void
    {
        // Mahasiswa tidak punya akun. Identitas yang tidak cocok dengan
        // akun dosen manapun berarti mahasiswa.
        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => $this->buatCsv([$this->barisValid()]),
        ]);

        $this->assertSame(
            LogAktivitas::PERAN_MAHASISWA,
            LogAktivitas::first()->actor_role
        );
        $this->assertNull(LogAktivitas::first()->actor_user_id);

        // Belum "dikenal" karena NIM-nya belum masuk daftar kelas mana pun.
        $batch = ImportBatch::first();
        $this->assertSame(0, $batch->jumlah_terkenal);
        $this->assertSame(1, $batch->jumlah_tak_dikenal);
    }

    public function test_file_di_atas_batas_lama_tetap_diterima(): void
    {
        // Dulu ada aturan max:51200 (50 MB), padahal file log nyata bisa
        // ratusan MB. Ini menutup celah regresi: isi file kecil, tapi
        // ukuran yang dilaporkan 60 MB (di atas batas lama) harus tetap
        // diterima, bukan ditolak oleh validator.
        $file = FileUkuranPalsu::dariCsv($this->buatCsv([$this->barisValid()]), 60_000_000, 'log-besar.csv');

        $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $file])
            ->assertSessionHas('success');
    }

    public function test_ukuran_besar_tidak_menjadi_alasan_penolakan_tetapi_isinya_tetap_diperiksa(): void
    {
        // File yang dilaporkan 60 MB tapi isinya TIDAK valid tetap
        // ditolak — bukan karena ukurannya, melainkan karena isinya. Ini
        // menjaga supaya "tanpa batas ukuran" tidak terbaca sebagai
        // "terima apa pun".
        $file = FileUkuranPalsu::dariCsv(
            $this->buatCsv([['a', 'b', 'c']], header: 'nama;umur;kota'),
            60_000_000,
            'salah.csv'
        );

        $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $file])
            ->assertSessionHas('error');

        $this->assertSame(ImportBatch::STATUS_GAGAL, ImportBatch::first()->status);
    }

    // ------------------------------------------------------------- duplikat

    public function test_baris_identik_di_deduplikasi(): void
    {
        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => $this->buatCsv([
                $this->barisValid(),
                $this->barisValid(),
                $this->barisValid(),
            ]),
        ]);

        $batch = ImportBatch::first();
        $this->assertSame(1, $batch->jumlah_berhasil);
        $this->assertSame(2, $batch->jumlah_duplikat);
        $this->assertSame(1, LogAktivitas::count());
    }

    public function test_dua_kevent_berbeda_dalam_menit_yang_sama_tidak_dianggap_kembar(): void
    {
        // Waktu sumber hanya presisi menit. Mahasiswa yang membuka
        // halaman dua kali dalam menit yang sama adalah dua kejadian
        // nyata, bukan baris kembar.
        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => $this->buatCsv([
                $this->barisValid(),
                $this->barisValid([5 => 'Course module viewed']),
            ]),
        ]);

        $batch = ImportBatch::first();
        $this->assertSame(2, $batch->jumlah_berhasil);
        $this->assertSame(0, $batch->jumlah_duplikat);
    }

    // ------------------------------------------------------------- baris rusak

    public function test_waktu_rusak_tidak_menggagalkan_import(): void
    {
        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => $this->buatCsv([
                $this->barisValid(),
                $this->barisValid([0 => '31/02/2026 10:00']),
            ]),
        ]);

        $batch = ImportBatch::first();
        $this->assertSame(ImportBatch::STATUS_BERHASIL, $batch->status);
        $this->assertSame(1, $batch->jumlah_berhasil);
        $this->assertSame(1, $batch->jumlah_gagal);
        $this->assertSame(1, LogImportError::where('kode_error', LogImportError::KODE_WAKTU_TIDAK_VALID)->count());
    }

    public function test_tanggal_tidak_mungkin_ditolak(): void
    {
        // 31 Februari harus ditolak, bukan digeser ke 3 Maret.
        $parser = app(MoodleLogParser::class);
        $this->assertNull($parser->parseWaktu('31/02/2026 10:00'));
        $this->assertNotNull($parser->parseWaktu('28/02/2026 10:00'));
    }

    public function test_nama_kosong_ditandai(): void
    {
        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => $this->buatCsv([$this->barisValid([1 => ''])]),
        ]);

        $batch = ImportBatch::first();
        $this->assertSame(0, $batch->jumlah_berhasil);
        $this->assertSame(1, LogImportError::where('kode_error', LogImportError::KODE_NAMA_KOSONG)->count());
    }

    // ------------------------------------------------------------- format file

    public function test_header_bukan_log_moodle_ditolak(): void
    {
        $file = $this->buatCsv([['a', 'b', 'c']], header: 'nama;umur;kota');

        $response = $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $file]);

        $response->assertSessionHas('error');
        $this->assertSame(ImportBatch::STATUS_GAGAL, ImportBatch::first()->status);
        $this->assertSame(
            1,
            LogImportError::where('kode_error', LogImportError::KODE_HEADER_TIDAK_VALID)->count()
        );
        $this->assertSame(0, LogAktivitas::count());
    }

    public function test_bom_dibuang_dari_header(): void
    {
        // Tanpa ini, kolom pertama jadi "﻿Time" dan header tidak dikenali.
        $this->actingAs($this->admin())
            ->post('/admin/log-import', ['file_csv' => $this->buatCsv([$this->barisValid()], bom: true)]);

        $this->assertSame(1, LogAktivitas::count());
    }

    public function test_pemisah_koma_juga_diterima(): void
    {
        $isi = "Time,User full name,Affected user,Event context,Component,Event name,Description,Origin,IP address\n"
            ."14/10/2026 14:50,Ririn Anisa Sihite 3312501013,-,Course: Jaringan Komputer/IF209,System,Course viewed,desc,web,1.2.3.4\n";
        $path = tempnam(sys_get_temp_dir(), 'log').'.csv';
        file_put_contents($path, $isi);

        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => new UploadedFile($path, 'koma.csv', 'text/csv', null, true),
        ]);

        $this->assertSame(1, LogAktivitas::count());
    }

    public function test_file_tanpa_header_ditolak(): void
    {
        // Hanya BOM, tanpa baris header sama sekali.
        $path = tempnam(sys_get_temp_dir(), 'log').'.csv';
        file_put_contents($path, "\xEF\xBB\xBF");

        $response = $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => new UploadedFile($path, 'kosong.csv', 'text/csv', null, true),
        ]);

        $response->assertSessionHas('error');
        $batch = ImportBatch::first();
        $this->assertNotNull($batch);
        $this->assertSame(ImportBatch::STATUS_GAGAL, $batch->status);
        $this->assertSame(0, LogAktivitas::count());
    }

    public function test_file_nol_byte_ditolak_validasi_form(): void
    {
        // File 0 byte ditolak lebih dulu oleh validasi, sebelum importer jalan.
        $path = tempnam(sys_get_temp_dir(), 'log').'.csv';
        file_put_contents($path, '');

        $this->actingAs($this->admin())->post('/admin/log-import', [
            'file_csv' => new UploadedFile($path, 'nol.csv', 'application/octet-stream', null, true),
        ])->assertSessionHasErrors('file_csv');

        $this->assertSame(0, ImportBatch::count());
    }

    // ------------------------------------------------------------- validasi form

    public function test_file_wajib_diisi(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/log-import', [])
            ->assertSessionHasErrors('file_csv');
    }

    public function test_admin_bisa_mengakses_riwayat(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/log-import')
            ->assertOk()
            ->assertViewIs('Admin.LogImportHistory');
    }

    public function test_dosen_tidak_bisa_import(): void
    {
        $dosen = User::create([
            'nama' => 'Dosen',
            'email' => 'dosen@test.local',
            'password' => 'rahasia123',
            'role' => 'dosen',
        ]);

        $this->actingAs($dosen)
            ->post('/admin/log-import', ['file_csv' => $this->buatCsv([$this->barisValid()])])
            ->assertForbidden();

        $this->assertSame(0, ImportBatch::count());
    }

    // ------------------------------------------------------------- parser unit

    public function test_parse_identitas_memisahkan_nama_dan_id(): void
    {
        $parser = app(MoodleLogParser::class);

        $hasil = $parser->parseIdentitas('Ririn Anisa Sihite 3312501013');
        $this->assertSame('Ririn Anisa Sihite', $hasil['nama']);
        $this->assertSame('3312501013', $hasil['identifier']);

        $hasil = $parser->parseIdentitas('Ketua Program Studi Informatika 1506');
        $this->assertSame('1506', $hasil['identifier']);

        $hasil = $parser->parseIdentitas('-');
        $this->assertNull($hasil['identifier']);

        $hasil = $parser->parseIdentitas('Admin upa-tik');
        $this->assertSame('Admin upa-tik', $hasil['nama']);
        $this->assertNull($hasil['identifier']);
    }

    public function test_parse_konteks_memisahkan_course_dan_kode(): void
    {
        $parser = app(MoodleLogParser::class);

        $hasil = $parser->parseKonteks('Course: Jaringan Komputer/IF209');
        $this->assertSame('Course', $hasil['type']);
        $this->assertSame('IF209', $hasil['kode_mk']);
        $this->assertSame('Jaringan Komputer', $hasil['nama_mk']);

        $hasil = $parser->parseKonteks('Quiz: ATS Teori');
        $this->assertSame('Quiz', $hasil['type']);
        $this->assertSame('ATS Teori', $hasil['name']);
        $this->assertNull($hasil['kode_mk']);

        $hasil = $parser->parseKonteks('Assignment: Pengumpulan Praktikum 1 Kelas 2B Pagi');
        $this->assertSame('Assignment', $hasil['type']);
        $this->assertNull($hasil['kode_mk'], 'Kelas TIDAK diparse dari nama assignment');
    }
}
