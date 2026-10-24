<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BuktiPeran;
use App\Services\Moodle\MoodleLogParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tampilkan identitas di log LMS yang belum dikenali sistem.
 *
 * Perintah ini HANYA membaca dan menampilkan. Ia tidak membuat akun dan
 * tidak menulis apa pun ke database. Keputusan siapa yang didaftarkan
 * tetap di tangan Admin.
 *
 * Sebagian identitas bisa dipastikan bukan mahasiswa tanpa menebak dari
 * panjang digit. Dasarnya adalah jenis event yang MUSTAHIL dilakukan
 * mahasiswa: membuat aktivitas, mengubah course, membuat grup, mengatur
 * pendaftaran. Mahasiswa hanya bisa melihat dan mengerjakan.
 *
 * Pelajaran penting dari data nyata: di file IF209 ada identifier 6 digit
 * (225364: dosen) DAN identifier 10 digit yang ternyata pengelola
 * (4312001038 membatalkan pendaftaran orang). Menentukan peran dari
 * panjang digit akan salah pada keduanya.
 */
class IdentitasBelumTerdaftar extends Command
{
    protected $signature = 'identitas:belum-terdaftar
        {--file= : Baca langsung dari file CSV log Moodle, tanpa impor}
        {--batas=25 : Jumlah mahasiswa yang ditampilkan}';

    protected $description = 'Tampilkan identitas di log LMS yang belum punya akun atau belum masuk daftar kelas';

    /**
     * Daftar event pembuktian peran TIDAK lagi ditulis di sini.
     *
     * Semuanya pindah ke App\Services\BuktiPeran supaya perintah ini dan
     * perintah penyusun kelas memakai aturan yang sama persis. Kalau daftar
     * eventnya bercabang di dua tempat, sooner atau later kaprodi melihat
     * angka mahasiswa yang berbeda dari yang dihitung perintah identitas.
     */
    public function handle(BuktiPeran $buktiPeran): int
    {
        $file = $this->option('file');

        try {
            $hasil = $file === null
                ? $this->ringkasDariDatabase()
                : $this->ringkasDariFile((string) $file);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $ringkasan = $hasil['identitas'];
        $tanpaIdentitas = $hasil['tanpa_identitas'];

        if ($ringkasan === [] && $tanpaIdentitas === []) {
            $this->warn('Tidak ada identitas di log.');
            $this->line($file === null
                ? '  Belum ada log yang diimpor. Jalankan impor dulu, atau pakai --file untuk pratinjau.'
                : '  File tidak memuat baris identitas yang bisa dibaca.');

            return self::SUCCESS;
        }

        $adaAkun = $this->himpunanNomorInduk();
        $terdaftar = $this->himpunanNimTerdaftar();

        $belum = [];
        foreach ($ringkasan as $identitas => $r) {
            if (! isset($adaAkun[$identitas]) && ! isset($terdaftar[$identitas])) {
                $belum[$identitas] = $r;
            }
        }

        $this->tampilkanRingkasan($ringkasan, $belum);

        if ($belum === [] && $tanpaIdentitas === []) {
            $this->info('Semua identitas di log sudah dikenali. Tidak ada yang perlu ditindaklanjuti.');

            return self::SUCCESS;
        }

        $this->line($file === null
            ? 'Sumber: tabel <options=bold>log_aktivitas</>'
            : 'Sumber: <options=bold>'.$file.'</> (pratinjau, tidak disimpan)');

        $this->tampilkanPerTingkat($belum, $buktiPeran);
        $this->tampilkanTanpaIdentitas($tanpaIdentitas);

        $this->newLine();
        $this->line('<comment>Tidak ada akun yang dibuat oleh perintah ini.</comment>');
        $this->line('  Dosen      : daftarkan di /admin/akun, lalu tambahkan sebagai pengampu di /admin/penugasan');
        $this->line('  Mahasiswa  : cukup catat NIM-nya di /admin/penugasan pada kelas yang sesuai');
        $this->line('  Pengelola  : biasanya akun pengelola LMS, tidak perlu didaftarkan sebagai dosen');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------ pengumpulan

    /**
     * @return array<string, array<string, mixed>>
     */
    private function ringkasDariDatabase(): array
    {
        $hasil = [];

        $baris = DB::table('log_aktivitas')
            ->selectRaw('actor_identifier')
            ->selectRaw('MAX(actor_name) as nama')
            ->selectRaw('COUNT(*) as event')
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as hari')
            ->selectRaw('MIN(occurred_at) as mulai')
            ->selectRaw('MAX(occurred_at) as akhir')
            ->whereNotNull('actor_identifier')
            ->groupBy('actor_identifier')
            ->get();

        foreach ($baris as $r) {
            $hasil['_'.$r->actor_identifier] = [
                'nama' => (string) $r->nama,
                'event' => (int) $r->event,
                'hari' => (int) $r->hari,
                'mulai' => $r->mulai,
                'akhir' => $r->akhir,
                'jenis' => [],
            ];
        }

        $jenis = DB::table('log_aktivitas')
            ->selectRaw('actor_identifier, event_name, COUNT(*) as jumlah')
            ->whereNotNull('actor_identifier')
            ->groupBy('actor_identifier', 'event_name')
            ->get();

        foreach ($jenis as $r) {
            $kunci = '_'.$r->actor_identifier;
            if (isset($hasil[$kunci])) {
                $hasil[$kunci]['jenis'][(string) $r->event_name] = (int) $r->jumlah;
            }
        }

        return ['identitas' => $hasil, 'tanpa_identitas' => $this->tanpaIdentitasDariDatabase()];
    }

    /**
     * Baris log yang identitasnya tidak bisa dibaca sama sekali.
     *
     * Ini tidak boleh dibuang diam-diam: kalau ada akun yang benar-benar
     * aktif tapi namanya tidak berakhiran NIM/NIP (mis. "Admin upa-tik"),
     * seluruh aktivitasnya akan hilang dari pantauan.
     *
     * @return array<string, array{event: int, hari: int, mulai: string|null, akhir: string|null}>
     */
    private function tanpaIdentitasDariDatabase(): array
    {
        $baris = DB::table('log_aktivitas')
            ->selectRaw('actor_name')
            ->selectRaw('COUNT(*) as event')
            ->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as hari')
            ->selectRaw('MIN(occurred_at) as mulai')
            ->selectRaw('MAX(occurred_at) as akhir')
            ->whereNull('actor_identifier')
            ->whereNotNull('actor_name')
            ->groupBy('actor_name')
            ->get();

        $hasil = [];
        foreach ($baris as $r) {
            $nama = (string) $r->actor_name;
            $hasil[$nama] = [
                'event' => (int) $r->event,
                'hari' => (int) $r->hari,
                'mulai' => $r->mulai,
                'akhir' => $r->akhir,
            ];
        }

        return $hasil;
    }

    /**
     * @return array{identitas: array<string, array<string, mixed>>, tanpa_identitas: array<string, array<string, mixed>>}
     */
    private function ringkasDariFile(string $file): array
    {
        if (! is_file($file)) {
            throw new \RuntimeException("File tidak ditemukan: {$file}");
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new \RuntimeException("File tidak bisa dibuka: {$file}");
        }

        $parser = new MoodleLogParser;
        $pemisah = $parser->tebakPemisah((string) fgets($handle));
        rewind($handle);

        $peta = null;
        $hasil = [];
        $tanpa = [];

        try {
            while (($baris = fgetcsv($handle, 0, $pemisah)) !== false) {
                if ($peta === null) {
                    $peta = $parser->petakanHeader($baris);

                    $kurang = $parser->kolomWajib($peta);
                    if ($kurang !== []) {
                        throw new \RuntimeException(
                            'Header tidak dikenali sebagai log Moodle. Kolom yang hilang: '.implode(', ', $kurang)
                        );
                    }

                    continue;
                }

                $parsed = $parser->parseBaris($baris, $peta);
                if (! $parsed['valid']) {
                    continue;
                }

                $data = $parsed['data'];
                $identitas = $data['actor_identifier'];
                $waktu = (string) $data['occurred_at'];
                $event = (string) $data['event_name'];

                if ($identitas === null || $identitas === '') {
                    $nama = (string) $data['actor_name'];
                    $tanpa[$nama] ??= ['event' => 0, 'hari' => 0, 'mulai' => $waktu, 'akhir' => $waktu, 'tanggal' => []];
                    $tanpa[$nama]['event']++;
                    $tanpa[$nama]['tanggal'][substr($waktu, 0, 10)] = true;
                    $tanpa[$nama]['mulai'] = min($tanpa[$nama]['mulai'], $waktu);
                    $tanpa[$nama]['akhir'] = max($tanpa[$nama]['akhir'], $waktu);

                    continue;
                }

                $kunci = '_'.$identitas;

                if (! isset($hasil[$kunci])) {
                    $hasil[$kunci] = [
                        'nama' => '',
                        'event' => 0,
                        'hari' => 0,
                        'mulai' => $waktu,
                        'akhir' => $waktu,
                        'jenis' => [],
                        'tanggal' => [],
                    ];
                }

                // Nama diambil yang terbaru: kadang berubah antar baris.
                $hasil[$kunci]['nama'] = (string) $data['actor_name'];
                $hasil[$kunci]['event']++;
                $hasil[$kunci]['tanggal'][substr($waktu, 0, 10)] = true;
                $hasil[$kunci]['mulai'] = min($hasil[$kunci]['mulai'], $waktu);
                $hasil[$kunci]['akhir'] = max($hasil[$kunci]['akhir'], $waktu);
                $hasil[$kunci]['jenis'][$event] = ($hasil[$kunci]['jenis'][$event] ?? 0) + 1;
            }
        } finally {
            fclose($handle);
        }

        foreach ($hasil as $kunci => $r) {
            $hasil[$kunci]['hari'] = count($r['tanggal']);
            unset($hasil[$kunci]['tanggal']);
        }

        foreach ($tanpa as $nama => $r) {
            $tanpa[$nama]['hari'] = count($r['tanggal']);
            unset($tanpa[$nama]['tanggal']);
        }

        return ['identitas' => $hasil, 'tanpa_identitas' => $tanpa];
    }

    /**
     * Himpunan nomor_induk yang sudah punya akun.
     *
     * Kunci diberi awalan '_' karena PHP mengubah kunci array yang isinya
     * angka menjadi integer. Tanpa awalan, pencocokan NIM bisa gagal
     * secara senyap.
     *
     * @return array<string, true>
     */
    private function himpunanNomorInduk(): array
    {
        $himpunan = [];
        foreach (User::whereNotNull('nomor_induk')->pluck('nomor_induk') as $nomor) {
            $himpunan['_'.$nomor] = true;
        }

        return $himpunan;
    }

    /** @return array<string, true> */
    private function himpunanNimTerdaftar(): array
    {
        $himpunan = [];
        foreach (DB::table('kelas_mahasiswa')->distinct()->pluck('nim') as $nim) {
            $himpunan['_'.$nim] = true;
        }

        return $himpunan;
    }

    // ---------------------------------------------------------------- tampilan

    /**
     * @param  array<string, array<string, mixed>>  $ringkasan
     * @param  array<string, array<string, mixed>>  $belum
     */
    private function tampilkanRingkasan(array $ringkasan, array $belum): void
    {
        $totalBaris = array_sum(array_column($ringkasan, 'event'));
        $barisBelum = array_sum(array_column($belum, 'event'));

        $this->info(sprintf('%d identitas berbeda muncul di log.', count($ringkasan)));
        $this->line(sprintf('  sudah dikenali     : %d', count($ringkasan) - count($belum)));
        $this->line(sprintf('  belum dikenali     : %d', count($belum)));
        $this->line(sprintf(
            '  porsi aktivitasnya : %s dari %s baris (%.1f%%)',
            number_format($barisBelum, 0, ',', '.'),
            number_format($totalBaris, 0, ',', '.'),
            $totalBaris > 0 ? $barisBelum / $totalBaris * 100 : 0
        ));
    }

    /**
     * Bagi identitas yang belum dikenali menjadi empat tingkat bukti,
     * lalu tampilkan satu tabel per tingkat.
     *
     * Tingkatnya ditentukan dari jenis event yang dilakukan, BUKAN dari
     * panjang NIM/NIP. Urutannya dari bukti terkuat ke terlemah supaya
     * yang perlu ditindaklanjuti langsung terlihat di atas.
     *
     * @param  array<string, array<string, mixed>>  $belum
     */
    private function tampilkanPerTingkat(array $belum, BuktiPeran $buktiPeran): void
    {
        $tingkat = ['pengajar' => [], 'pengelola' => [], 'meragukan' => [], 'peserta' => []];

        foreach ($belum as $identitas => $r) {
            $putusan = $buktiPeran->classify($r['jenis']);
            $tingkat[$putusan['tingkat']][$identitas] = $r + ['bukti' => $putusan['bukti']];
        }

        $batas = max(1, (int) $this->option('batas'));

        $judul = [
            'pengajar' => ['<fg=green>Bukti perbuatan pengajar</>', 'Mahasiswa tidak bisa membuat aktivitas, mengubah course, atau membuat grup. Ini kandidat dosen — perlu akun.'],
            'pengelola' => ['<fg=yellow>Bukti perbuatan pengelola</>', 'Membatalkan pendaftaran atau mengubah peran orang lain. Biasanya akun admin LMS, bukan dosen pengampu.'],
            'meragukan' => ['<fg=yellow>Bukti lemah — periksa manual</>', 'Hanya melakukan event penilaian sesekali. Moodle kadang mencatatnya atas nama orang yang membuka halaman, jadi belum tentu pengajar.'],
            'peserta' => ['<options=bold>Perilakunya sama dengan mahasiswa</>', 'Tidak ada satu pun event pengajar maupun pengelola. Kemungkinan besar memang mahasiswa yang belum masuk daftar kelas.'],
        ];

        foreach ($tingkat as $nama => $daftar) {
            if ($daftar === []) {
                continue;
            }

            [$header, $keterangan] = $judul[$nama];
            $maks = $nama === 'peserta' ? $batas : PHP_INT_MAX;

            $this->newLine();
            $this->line(sprintf('%s <options=bold>(%d)</>', $header, count($daftar)));
            $this->line('  '.$keterangan);

            $this->table(
                $nama === 'peserta'
                    ? ['Identitas', 'Nama', 'Event', 'Hari', 'Periode']
                    : ['Identitas', 'Nama', 'Event', 'Hari', 'Bukti perbuatan'],
                $this->susunBaris($daftar, $maks, denganBukti: $nama !== 'peserta')
            );

            if (count($daftar) > $maks) {
                $this->line(sprintf(
                    '  ... dan %d identitas lain. Pakai --batas=%d untuk melihat semuanya.',
                    count($daftar) - $maks,
                    count($daftar)
                ));
            }
        }
    }

    /**
     * Baris log yang namanya tidak berakhiran NIM/NIP.
     *
     * Tidak bisa dicocokkan dengan akun mana pun. Biasanya akun teknis
     * ("-"), tapi bisa juga akun penting seperti "Admin upa-tik". Sengaja
     * ditampilkan supaya tidak ada aktivitas yang hilang tanpa jejak.
     *
     * @param  array<string, array<string, mixed>>  $tanpaIdentitas
     */
    private function tampilkanTanpaIdentitas(array $tanpaIdentitas): void
    {
        if ($tanpaIdentitas === []) {
            return;
        }

        uasort($tanpaIdentitas, fn ($a, $b) => $b['event'] <=> $a['event']);

        $baris = [];
        foreach ($tanpaIdentitas as $nama => $r) {
            $baris[] = [
                $nama,
                number_format($r['event'], 0, ',', '.'),
                (string) $r['hari'],
                $this->periode($r['mulai'], $r['akhir']),
            ];
        }

        $this->newLine();
        $this->line(sprintf('<fg=red>Nama tanpa NIM/NIP (%d)</>', count($tanpaIdentitas)));
        $this->line('  Tidak bisa dicocokkan dengan akun mana pun. Kalau salah satunya akun penting,');
        $this->line('  aktifitasnya tidak akan pernah masuk hitungan sampai format namanya diperbaiki.');

        $this->table(['Nama di log', 'Event', 'Hari', 'Periode'], $baris);
    }

    /**
     * @param  array<string, array<string, mixed>>  $belum
     * @return array<int, array<int, string>>
     */
    private function susunBaris(array $belum, int $batas, bool $denganBukti = true): array
    {
        uasort($belum, fn ($a, $b) => $b['event'] <=> $a['event']);

        $baris = [];
        foreach (array_slice($belum, 0, $batas, true) as $identitas => $r) {
            $baris[] = [
                ltrim($identitas, '_'),
                $this->pendekkan((string) $r['nama']),
                number_format($r['event'], 0, ',', '.'),
                (string) $r['hari'],
                $denganBukti
                    ? $this->pendekkan(implode(', ', $r['bukti'] ?? []))
                    : $this->periode($r['mulai'], $r['akhir']),
            ];
        }

        return $baris;
    }

    /**
     * Buang NIM/NIP yang menempel di ujung nama.
     *
     * Isi kolom "User full name" adalah nama DAN identitas sekaligus
     * ("Ririn Anisa Sihite 3312501013"). Identitasnya sudah punya kolom
     * sendiri, jadi tidak perlu diulang.
     */
    private function pendekkan(string $nama, int $maks = 36): string
    {
        $nama = trim(preg_replace('/\s+/u', ' ', $nama) ?? '');
        $nama = trim(preg_replace('/\s+\d{6,18}$/', '', $nama) ?? $nama);

        return mb_strlen($nama) <= $maks
            ? $nama
            : mb_substr($nama, 0, $maks - 1).'…';
    }

    private function periode(?string $mulai, ?string $akhir): string
    {
        if ($mulai === null || $akhir === null) {
            return '-';
        }

        $awal = substr($mulai, 0, 10);
        $selesai = substr($akhir, 0, 10);

        return $awal === $selesai ? $awal : "{$awal} s/d {$selesai}";
    }
}
