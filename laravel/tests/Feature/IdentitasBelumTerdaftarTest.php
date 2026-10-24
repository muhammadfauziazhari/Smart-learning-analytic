<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Fakultas;
use App\Models\ImportBatch;
use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Uji perintah pratinjau identitas yang belum dikenali.
 *
 * Yang dijaga di sini:
 *
 * 1. Perintah ini TIDAK membuat akun dan tidak menulis apa pun.
 * 2. Pembedaan dosen/pengelola diambil dari jenis event yang mustahil
 *    dilakukan mahasiswa, BUKAN dari panjang NIM/NIP.
 * 3. Event yang muncul pada hampir semua mahasiswa ("Role assigned",
 *    "User enrolled in course") tidak boleh dianggap bukti peran.
 */
class IdentitasBelumTerdaftarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin', 'email' => 'admin@test.local',
            'password' => 'rahasia123', 'role' => 'admin',
        ]);

        $this->batchId = ImportBatch::create([
            'admin_id' => $this->admin->id,
            'nama_file' => 'log.csv',
            'status' => ImportBatch::STATUS_BERHASIL,
        ])->id;
    }

    // ----------------------------------------------------------- dari database

    public function test_membagi_identitas_menurut_perilakunya(): void
    {
        $this->log('225364', 'Syaprilla Donata', 'Course module created');
        $this->log('3312000001', 'Staf Pengelola', 'User unenrolled from course');
        $this->log('3312501013', 'Mahasiswa Biasa', 'Quiz attempt started');

        $this->artisan('identitas:belum-terdaftar')
            ->expectsOutputToContain('Bukti perbuatan pengajar')
            ->expectsOutputToContain('Bukti perbuatan pengelola')
            ->expectsOutputToContain('Perilakunya sama dengan mahasiswa')
            ->assertSuccessful();
    }

    public function test_dosen_dikenali_dari_perbuatan_bukan_panjang_digit(): void
    {
        // 6 digit. Kalau panjang digit dipakai sebagai dasar, ini akan
        // salah dikira dosen — makanya yang diuji adalah perbuatannya.
        $this->log('225364', 'Syaprilla Donata', 'Course module created');

        // 10 digit, sama seperti NIM mahasiswa, tapi membatalkan
        // pendaftaran orang lain: jelas bukan mahasiswa.
        $this->log('4312001038', 'Ali Akbar', 'User unenrolled from course');

        $this->artisan('identitas:belum-terdaftar')
            ->expectsOutputToContain('Course module created')
            ->expectsOutputToContain('User unenrolled from course')
            ->assertSuccessful();
    }

    public function test_role_assigned_tidak_dianggap_bukti_peran(): void
    {
        // Event ini muncul pada hampir setiap mahasiswa saat mendaftar
        // sendiri ke course. Kalau dipakai sebagai bukti, semua orang
        // akan terbaca sebagai pengelola.
        $this->log('3312501013', 'Mahasiswa Biasa', 'Role assigned');
        $this->log('3312501014', 'Mahasiswa Lain', 'User enrolled in course');

        $this->artisan('identitas:belum-terdaftar')
            ->doesntExpectOutputToContain('Bukti perbuatan pengelola')
            ->expectsOutputToContain('Perilakunya sama dengan mahasiswa')
            ->assertSuccessful();
    }

    public function test_nama_tanpa_nim_dilaporkan(): void
    {
        // "Admin upa-tik" benar-benar ada di file nyata: 23 baris, tidak
        // punya NIM, jadi tidak akan pernah bisa dicocokkan dengan akun.
        $this->log(null, 'Admin upa-tik', 'Course viewed');

        $this->artisan('identitas:belum-terdaftar')
            ->expectsOutputToContain('Nama tanpa NIM/NIP')
            ->expectsOutputToContain('Admin upa-tik')
            ->assertSuccessful();
    }

    public function test_identitas_yang_sudah_punya_akun_tidak_ditampilkan(): void
    {
        User::create([
            'nama' => 'Yeni Rokhayati', 'email' => 'yeni@test.local',
            'password' => 'rahasia123', 'role' => 'dosen',
            'nomor_induk' => '198602192014042001',
        ]);

        $this->log('198602192014042001', 'Yeni Rokhayati', 'Course module created');
        $this->log('3312509999', 'Belum Dikenal', 'Course viewed');

        $this->artisan('identitas:belum-terdaftar')
            ->expectsOutputToContain('sudah dikenali')
            ->assertSuccessful();

        $this->assertSame(1, LogAktivitas::where('actor_identifier', '198602192014042001')->count());
    }

    public function test_mahasiswa_yang_sudah_terdaftar_di_kelas_tidak_dihitung(): void
    {
        $this->siapkanProdiKelas();
        $kelas = Kelas::firstOrFail();

        Enrollment::create(['kelas_id' => $kelas->id, 'nim' => '3312501013', 'nama' => 'Budi']);
        $this->log('3312501013', 'Budi', 'Quiz attempt started');

        $this->artisan('identitas:belum-terdaftar')
            ->expectsOutputToContain('Semua identitas di log sudah dikenali')
            ->assertSuccessful();
    }

    public function test_tidak_membuat_akun_apa_pun(): void
    {
        $this->log('225364', 'Syaprilla Donata', 'Course module created');
        $this->log('3312501013', 'Mahasiswa Biasa', 'Quiz attempt started');

        $sebelum = User::count();

        $this->artisan('identitas:belum-terdaftar')->assertSuccessful();

        $this->assertSame(
            $sebelum,
            User::count(),
            'Perintah ini hanya menampilkan, tidak boleh membuat akun.'
        );
    }

    public function test_ringkasan_menghitung_porsi_aktivitas(): void
    {
        $this->log('225364', 'Syaprilla Donata', 'Course module created');
        $this->log('3312501013', 'Mhs', 'Course viewed');
        $this->log('3312501014', 'Mhs', 'Course viewed');
        $this->log('3312501015', 'Mhs', 'Course viewed');

        $this->artisan('identitas:belum-terdaftar')
            ->expectsOutputToContain('4 identitas berbeda muncul di log')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------- dari file

    public function test_pratinjau_dari_file_tidak_menyimpan_apa_pun(): void
    {
        $file = $this->tulisCsv([
            ['225364', 'Syaprilla Donata', 'Course module created'],
            ['4312001038', 'Ali Akbar', 'User unenrolled from course'],
            ['3312501013', 'Mahasiswa Biasa', 'Quiz attempt started'],
            ['3312501014', 'Mahasiswa Lain', 'Quiz attempt started'],
        ]);

        $this->artisan('identitas:belum-terdaftar', ['--file' => $file])
            ->expectsOutputToContain('pratinjau, tidak disimpan')
            ->expectsOutputToContain('Bukti perbuatan pengajar')
            ->expectsOutputToContain('Bukti perbuatan pengelola')
            ->expectsOutputToContain('Perilakunya sama dengan mahasiswa')
            ->assertSuccessful();

        $this->assertSame(0, LogAktivitas::count(), 'Pratinjau tidak boleh menyimpan log.');
        $this->assertSame(1, User::count(), 'Pratinjau tidak boleh membuat akun.');
    }

    public function test_file_yang_bukan_log_moodle_ditolak_dengan_jelas(): void
    {
        $file = $this->tulisCsv([], 'nama;umur;kota');

        $this->artisan('identitas:belum-terdaftar', ['--file' => $file])
            ->expectsOutputToContain('Header tidak dikenali')
            ->assertFailed();
    }

    public function test_file_tidak_ada_ditolak(): void
    {
        $this->artisan('identitas:belum-terdaftar', ['--file' => 'tidak/ada.csv'])
            ->expectsOutputToContain('File tidak ditemukan')
            ->assertFailed();
    }

    public function test_batas_membatasi_baris_mahasiswa(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->log('33125010'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Mhs '.$i, 'Quiz attempt started');
        }

        $this->artisan('identitas:belum-terdaftar', ['--batas' => 3])
            ->expectsOutputToContain('dan 7 identitas lain')
            ->assertSuccessful();
    }

    // ----------------------------------------------------------------- bantu

    private function log(?string $identitas, string $nama, string $event, ?string $waktu = null): void
    {
        $waktu ??= '2026-10-14 14:50:00';

        LogAktivitas::create([
            'import_batch_id' => $this->batchId,
            'occurred_at' => $waktu,
            'periode' => Carbon::parse($waktu)->format('Y-m'),
            'actor_name' => $identitas === null ? $nama : "{$nama} {$identitas}",
            'actor_identifier' => $identitas,
            'actor_role' => LogAktivitas::PERAN_TAK_DIKETAHUI,
            'event_name' => $event,
            'sidik_jari' => hash('xxh128', $nama.($identitas ?? '').$event.$waktu),
        ]);
    }

    private function siapkanProdiKelas(): void
    {
        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Ilmu Komputer']);
        $prodi = Prodi::create(['kode' => 'TI', 'nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id]);
        $mk = MataKuliah::create([
            'kode' => 'IF209', 'nama' => 'Jaringan Komputer',
            'sks' => 3, 'semester' => 5, 'prodi_id' => $prodi->id,
        ]);

        Kelas::create([
            'kode' => '2A', 'nama' => '2A Pagi', 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $prodi->id, 'tahun_akademik' => '2026/2027', 'semester' => 1,
        ]);
    }

    /**
     * Tulis file CSV log Moodle sementara.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $baris
     */
    private function tulisCsv(array $baris, ?string $header = null): string
    {
        $header ??= 'Time;User full name;Affected user;Event context;Component;Event name;Description;Origin;IP address';

        $isi = "\xEF\xBB\xBF".$header."\r\n";
        foreach ($baris as [$identitas, $nama, $event]) {
            $isi .= sprintf(
                "14/10/2026 14:50;%s %s;-;Course: Jaringan Komputer/IF209;System;%s;desc;web;1,6E+11\r\n",
                $nama,
                $identitas,
                $event
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'log').'.csv';
        file_put_contents($path, $isi);

        return $path;
    }
}
