<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\MataKuliah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perintah `kelas:impor-roster`: memecah kelas per mata kuliah menjadi kelas
 * Pagi/Malam dengan asal A/B/C/D, berdasarkan daftar mahasiswa dari Excel.
 *
 * Berkas roster pada test sengaja dibuat sebagai CSV, supaya test tidak
 * bergantung pada Python. Jalur konversi .xlsx -> .csv diuji terpisah oleh
 * berkas aslinya di sample-data/.
 */
class ImporRosterKelasTest extends TestCase
{
    use RefreshDatabase;

    private function buatMk(string $kode = 'IF209'): MataKuliah
    {
        return MataKuliah::create([
            'kode' => $kode, 'nama' => 'Jaringan Komputer', 'sks' => 3,
        ]);
    }

    private function buatKelasLama(MataKuliah $mk, array $mahasiswa): Kelas
    {
        $kelas = Kelas::create([
            'kode' => 'A', 'nama' => 'Kelas A', 'mata_kuliah_id' => $mk->id,
            'tahun_akademik' => '2025/2026', 'semester' => 2,
        ]);

        foreach ($mahasiswa as $nim => $nama) {
            DB::table('kelas_mahasiswa')->insert([
                'kelas_id' => $kelas->id, 'nim' => (string) $nim, 'nama' => $nama,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $kelas;
    }

    /**
     * Berkas CSV roster dengan baris keterangan di atas header, seperti
     * berkas Excel aslinya.
     */
    private function csv(array $baris): string
    {
        $isi = "DAFTAR MAHASISWA\nDAFTAR MAHASISWA BERDASARKAN NIM\n\n"
            ."No.,Nama Mahasiswa,NIM,Kelas Asal\n";

        foreach ($baris as $row) {
            $isi .= implode(',', $row)."\n";
        }

        $jalur = storage_path('app/roster-uji.csv');
        file_put_contents($jalur, $isi);

        return $jalur;
    }

    public function test_kelas_terpecah_pagi_malam_dan_asal(): void
    {
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        $csv = $this->csv([
            ['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi'],
            ['2', 'Andi Pratama', '3312501002', 'Kelas A Pagi'],
            ['3', 'Bunga Lestari', '3312501003', 'Kelas B Malam'],
        ]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $kelas = $mk->kelas()->orderBy('kode')->get();

        // Kelas lama "A" sengaja dibiarkan meski sudah kosong: menghapusnya
        // ikut menghapus penugasan dosen. Tidak ada kelas "Tanpa Kelas Asal"
        // karena semua mahasiswa lama ada di roster.
        $this->assertSame(['A', 'A Pagi', 'B Malam'], $kelas->pluck('kode')->all());

        $aPagi = $kelas->firstWhere('kode', 'A Pagi');
        $this->assertSame('Kelas A Pagi', $aPagi->nama);
        // Kolom ini yang dipakai filter; kalau tidak terisi, filter tidak
        // akan pernah bisa bekerja.
        $this->assertSame('A', $aPagi->asal_kelas);
        $this->assertSame('Pagi', $aPagi->jenis_kelas);
        $this->assertSame(2, $aPagi->mahasiswa()->count());

        $bMalam = $kelas->firstWhere('kode', 'B Malam');
        $this->assertSame('B', $bMalam->asal_kelas);
        $this->assertSame('Malam', $bMalam->jenis_kelas);
    }

    public function test_tahun_akademik_dan_semester_dikalin_dari_kelas_lama(): void
    {
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        $csv = $this->csv([['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi']]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $baru = $mk->kelas()->where('kode', 'A Pagi')->first();

        // Kalau tidak dikalin, jendela analisis satu semester ikut bergeser
        // dan seluruh angka historis berubah.
        $this->assertSame('2025/2026', $baru->tahun_akademik);
        $this->assertSame(2, $baru->semester);
    }

    public function test_mahasiswa_di_luar_roster_tidak_hilang(): void
    {
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, [
            '3312501001' => 'Budi',
            '3312211057' => 'Zefanya Febrian Tobing',
        ]);

        $csv = $this->csv([['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi']]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $tanpa = $mk->kelas()->where('kode', 'Tanpa Kelas Asal')->first();

        $this->assertNotNull($tanpa);
        $this->assertSame(1, $tanpa->mahasiswa()->count());
        // Namanya diambil dari kelas lama, bukan dikarang perintah ini.
        $this->assertSame(
            'Zefanya Febrian Tobing',
            $tanpa->mahasiswa()->first()->nama,
        );
        // Tidak punya label asal/jenis.
        $this->assertNull($tanpa->asal_kelas);
        $this->assertNull($tanpa->jenis_kelas);
    }

    public function test_mahasiswa_tidak_masuk_dua_kelas_dalam_satu_mata_kuliah(): void
    {
        $mk = $this->buatMk();
        $lama = $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        // Kelas kedua di mata kuliah yang sama, berisi mahasiswa yang sama.
        $kedua = Kelas::create([
            'kode' => 'B', 'nama' => 'Kelas B', 'mata_kuliah_id' => $mk->id,
            'tahun_akademik' => '2025/2026', 'semester' => 2,
        ]);
        DB::table('kelas_mahasiswa')->insert([
            'kelas_id' => $kedua->id, 'nim' => '3312501001', 'nama' => 'Budi',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $csv = $this->csv([['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi']]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $jumlah = DB::table('kelas_mahasiswa')->where('nim', '3312501001')->count();

        $this->assertSame(1, $jumlah, 'Satu mahasiswa tidak boleh tercatat di dua kelas mata kuliah yang sama.');
        $this->assertSame(
            $mk->kelas()->where('kode', 'A Pagi')->first()->id,
            DB::table('kelas_mahasiswa')->where('nim', '3312501001')->value('kelas_id'),
        );
        $this->assertSame(0, DB::table('kelas_mahasiswa')->where('kelas_id', $lama->id)->count());
    }

    public function test_dry_run_tidak_mengubah_data(): void
    {
        $mk = $this->buatMk();
        $lama = $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        $csv = $this->csv([['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi']]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(1, $mk->kelas()->count());
        $this->assertSame(1, DB::table('kelas_mahasiswa')->where('kelas_id', $lama->id)->count());
    }

    public function test_perintah_bisa_dijalankan_berulang_tanpa_menggandakan(): void
    {
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        $csv = $this->csv([
            ['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi'],
            ['2', 'Andi Pratama', '3312501002', 'Kelas A Pagi'],
        ]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();
        $jumlahKelasPertama = $mk->kelas()->count();
        $jumlahMhsPertama = DB::table('kelas_mahasiswa')->count();

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $this->assertSame($jumlahKelasPertama, $mk->kelas()->count());
        $this->assertSame($jumlahMhsPertama, DB::table('kelas_mahasiswa')->count());
    }

    public function test_nama_lama_tidak_ditimpa_kecuali_diminta(): void
    {
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, ['3312501001' => 'Nama Dari Log']);

        $csv = $this->csv([['1', 'Nama Dari Roster', '3312501001', 'Kelas A Pagi']]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $this->assertSame(
            'Nama Dari Log',
            DB::table('kelas_mahasiswa')->where('nim', '3312501001')->value('nama'),
        );

        $this->artisan('kelas:impor-roster', ['--file' => $csv, '--perbarui-nama' => true])
            ->assertSuccessful();

        $this->assertSame(
            'Nama Dari Roster',
            DB::table('kelas_mahasiswa')->where('nim', '3312501001')->value('nama'),
        );
    }

    public function test_berkas_hanya_bisa_ditunjuk_ke_mata_kuliah_yang_ada(): void
    {
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        $csv = $this->csv([['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi']]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv, '--mata-kuliah' => ['IF999']])
            ->expectsOutputToContain('Tidak ada mata kuliah')
            ->assertFailed();

        $this->assertSame(1, $mk->kelas()->count());
    }

    public function test_header_yang_hanya_memuat_kata_nim_tidak_terbaca_sebagai_header(): void
    {
        // Baris keterangan "DAFTAR ... BERDASARKAN NIM" mengandung kata NIM
        // dan pernah salah dianggap header, sehingga kolom kelas tidak ketemu.
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        $csv = $this->csv([['1', 'Sari Wulandari', '3312501001', 'Kelas A Pagi']]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $this->assertSame(1, $mk->kelas()->where('kode', 'A Pagi')->count());
    }

    public function test_label_kelas_varian_tetap_dikenali(): void
    {
        $mk = $this->buatMk();
        $this->buatKelasLama($mk, ['3312501001' => 'Budi']);

        $csv = $this->csv([
            ['1', 'Sari Wulandari', '3312501001', 'A Pagi'],
            ['2', 'Andi Pratama', '3312501002', 'kelas b malam'],
        ]);

        $this->artisan('kelas:impor-roster', ['--file' => $csv])->assertSuccessful();

        $aPagi = $mk->kelas()->where('kode', 'A Pagi')->first();
        $bMalam = $mk->kelas()->where('kode', 'B Malam')->first();

        $this->assertNotNull($aPagi);
        $this->assertNotNull($bMalam);
        $this->assertSame('A', $aPagi->asal_kelas);
        $this->assertSame('Malam', $bMalam->jenis_kelas);
    }

    public function test_berkas_tidak_ada_ditolak_dengan_pesan(): void
    {
        $this->artisan('kelas:impor-roster', ['--file' => 'sample-data/tidak-ada.xlsx'])
            ->expectsOutputToContain('Berkas tidak ditemukan')
            ->assertFailed();
    }

    public function test_perintah_terdaftar_di_laravel(): void
    {
        $this->assertArrayHasKey('kelas:impor-roster', Artisan::all());
    }
}
