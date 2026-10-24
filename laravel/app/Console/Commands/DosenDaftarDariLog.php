<?php

namespace App\Console\Commands;

use App\Models\Kelas;
use App\Models\Prodi;
use App\Models\User;
use App\Services\BuktiPeran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Daftarkan akun dosen dari identitas yang terlihat di log, lalu catat
 * siapa pengampu kelas mana.
 *
 * Identitas yang dipakai hanya yang punya BUKTI perbuatan, bukan yang
 * disimpulkan dari bentuk nomor:
 *
 *   - Pengajar  : membuat course, modul, soal, atau grup.
 *   - Pengelola : membatalkan pendaftaran atau mengubah peran orang lain.
 *     Pengelola TIDAK didaftarkan sebagai dosen — biasanya akun admin LMS.
 *
 * Password dibangkitkan acak dan DITAMPILKAN SEKALI di terminal; tidak ada
 * tempat lain yang menyimpannya. Email dibangkit dari nama karena log LMS
 * sama sekali tidak memuat alamat email.
 */
class DosenDaftarDariLog extends Command
{
    protected $signature = 'dosen:daftar-dari-log
                            {--dry-run : Tampilkan kandidat tanpa membuat akun}
                            {--prodi= : Kode prodi untuk akun yang dibuat}
                            {--pengampu : Sekalian catat pengampu tiap kelas}
                            {--sertakan-pengelola : Ikut daftar identitas pengelola course}';

    protected $description = 'Daftarkan akun dosen dari identitas pengajar di log LMS';

    public function handle(BuktiPeran $buktiPeran): int
    {
        $kandidat = $this->kandidat($buktiPeran, (bool) $this->option('sertakan-pengelola'));

        if ($kandidat === []) {
            $this->warn('Tidak ada identitas pengajar di log.');
            $this->line('  Impor log dulu, atau jalankan identitas:belum-terdaftar untuk melihat rinciannya.');

            return self::SUCCESS;
        }

        $ada = $this->akunSudahAda();
        $kodeProdi = $this->option('prodi');

        $prodi = is_string($kodeProdi) && $kodeProdi !== ''
            ? Prodi::where('kode', $kodeProdi)->first()
            : $this->prodiDariAkun();

        if (is_string($kodeProdi) && $kodeProdi !== '' && $prodi === null) {
            $this->error("Prodi {$kodeProdi} tidak ada.");

            return self::FAILURE;
        }

        $jumlahPengajar = count(array_filter($kandidat, fn ($k) => $k['tingkat'] === BuktiPeran::PENGAJAR));
        $jumlahPengelola = count($kandidat) - $jumlahPengajar;

        $this->info("Kandidat dari log: {$jumlahPengajar} pengajar + {$jumlahPengelola} pengelola.");
        $sudahAda = 0;

        foreach (array_keys($kandidat) as $identitas) {
            if (isset($ada['_'.$identitas])) {
                $sudahAda++;
            }
        }

        $this->line("Di antaranya sudah punya akun: {$sudahAda}");

        if ($prodi !== null) {
            $this->line("Prodi akun      : {$prodi->kode} / {$prodi->nama}");
        }

        $this->newLine();

        $dryRun = (bool) $this->option('dry-run');
        $baris = [];
        $kredensial = [];

        foreach ($kandidat as $identitas => $k) {
            // Kunci akun disimpan berawalan "_" supaya tidak pernah
            // berubah jadi integer di PHP.
            $sudah = $ada['_'.$identitas] ?? null;

            if ($sudah !== null) {
                $baris[] = [$identitas, $k['nama'], $k['tingkat'], 'akun ada: '.$sudah->email.' ('.$sudah->role.')'];

                continue;
            }

            if ($dryRun) {
                $baris[] = [$identitas, $k['nama'], $k['tingkat'], 'akan dibuat'];

                continue;
            }

            if ($prodi === null) {
                $baris[] = [$identitas, $k['nama'], $k['tingkat'], 'dilewati: prodi belum pasti'];

                continue;
            }

            $hasil = $this->buatAkun($k, $prodi);

            if ($hasil === null) {
                $baris[] = [$identitas, $k['nama'], $k['tingkat'], 'gagal: email bentrok'];

                continue;
            }

            [$akun, $password] = $hasil;
            $kredensial[] = [$akun->email, $akun->nama, $password];
            $baris[] = [$identitas, $k['nama'], $k['tingkat'], 'dibuat: '.$akun->email];
        }

        $this->table(['Nomor induk', 'Nama di log', 'Bukti', 'Akun'], $baris);

        if ($kredensial !== []) {
            $this->newLine();
            $this->comment(sprintf(
                'Semua akun di bawah memakai password bawaan "%s". Minta mereka menggantinya sendiri lewat tab Settings.',
                User::PASSWORD_BAWAAN
            ));
            $this->table(['Email', 'Nama', 'Password'], $kredensial);
        }

        if ($this->option('pengampu')) {
            $this->newLine();
            $this->catatPengampu($buktiPeran, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * Identitas di log yang punya bukti bertindak sebagai pengajar atau
     * pengelola, beserta mata kuliah yang pernah ia sentuh.
     *
     * @return array<string, array{nomor_induk: string, nama: string, tingkat: string, bukti: array<int, string>, event: int, kode: list<string>}>
     */
    private function kandidat(BuktiPeran $buktiPeran, bool $denganPengelola): array
    {
        $baris = DB::table('log_aktivitas')
            ->select('actor_identifier', 'event_name', 'kode_mata_kuliah')
            ->selectRaw('MAX(actor_name) as nama_log')
            ->selectRaw('COUNT(*) as jumlah')
            ->whereNotNull('actor_identifier')
            ->whereNotNull('kode_mata_kuliah')
            ->where('kode_mata_kuliah', '<>', '')
            ->groupBy('actor_identifier', 'event_name', 'kode_mata_kuliah')
            ->get();

        $gabung = [];

        foreach ($baris as $r) {
            $kunci = '_'.$r->actor_identifier;
            $event = (string) $r->event_name;

            if (! isset($gabung[$kunci])) {
                $gabung[$kunci] = [
                    'nama_log' => (string) $r->nama_log,
                    'jenis' => [],
                    'kode' => [],
                    'event' => 0,
                ];
            }

            $gabung[$kunci]['jenis'][$event] = ($gabung[$kunci]['jenis'][$event] ?? 0) + (int) $r->jumlah;
            $gabung[$kunci]['event'] += (int) $r->jumlah;

            if (! in_array($r->kode_mata_kuliah, $gabung[$kunci]['kode'], true)) {
                $gabung[$kunci]['kode'][] = (string) $r->kode_mata_kuliah;
            }
        }

        $hasil = [];

        foreach ($gabung as $kunci => $r) {
            $putusan = $buktiPeran->classify($r['jenis']);

            $boleh = $putusan['tingkat'] === BuktiPeran::PENGAJAR
                || ($denganPengelola && $putusan['tingkat'] === BuktiPeran::PENGELOLA);

            if (! $boleh) {
                continue;
            }

            $identitas = ltrim($kunci, '_');

            $hasil[$identitas] = [
                'nomor_induk' => $identitas,
                'nama' => $this->bersihkanNama($r['nama_log'], $identitas),
                'tingkat' => $putusan['tingkat'],
                'bukti' => $putusan['bukti'],
                'event' => $r['event'],
                'kode' => $r['kode'],
            ];
        }

        uasort($hasil, fn ($a, $b) => $b['event'] <=> $a['event']);

        return $hasil;
    }

    /** @return array<string, User> akun yang sudah ada, dikunci '_'.$nomor_induk */
    private function akunSudahAda(): array
    {
        $ada = [];

        foreach (User::whereNotNull('nomor_induk')->get() as $u) {
            $ada['_'.$u->nomor_induk] = $u;
        }

        return $ada;
    }

    /** Prodi yang sudah dipakai akun pengajar yang tercatat. */
    private function prodiDariAkun(): ?Prodi
    {
        $prodiId = User::whereNotNull('nomor_induk')
            ->whereNotNull('prodi_id')
            ->pluck('prodi_id')
            ->unique();

        return $prodiId->count() === 1 ? Prodi::find($prodiId->first()) : null;
    }

    /**
     * Buat satu akun dosen.
     *
     * @param  array{nomor_induk: string, nama: string, tingkat: string, bukti: array<int, string>, event: int, kode: list<string>}  $k
     * @return array{0: User, 1: string}|null [akun, password polos]
     */
    private function buatAkun(array $k, Prodi $prodi): ?array
    {
        $slug = Str::slug($k['nama']);
        $slug = $slug !== '' ? $slug : 'dosen';

        $email = $slug.'@edutrack.local';

        // Dua orang bisa saja bernama sama, jadi email dit uniquify.
        if (User::where('email', $email)->exists()) {
            $email = $slug.'-'.Str::lower(Str::random(5)).'@edutrack.local';
        }

        // Password bawaan yang sama dengan akun lain (keputusan pengguna,
        // 18 Okt 2026), supaya Dosen bisa langsung masuk tanpa Admin
        // perlu intermittenggal hapal password acak yang hanya tampil sekali.
        // Password bawaan yang sama dengan akun lain (keputusan pengguna,
        // 18 Okt 2026), supaya Dosen bisa langsung masuk tanpa Admin
        // perlu menghafal password acak yang hanya tampil sekali.
        $password = User::PASSWORD_BAWAAN;

        $akun = User::create([
            'nama' => $k['nama'],
            'nomor_induk' => $k['nomor_induk'],
            'email' => $email,
            'password' => $password,
            'role' => 'dosen',
            'prodi_id' => $prodi->id,
            'is_active' => true,
        ]);

        return [$akun, $password];
    }

    /**
     * Catat pengampu tiap kelas dari aktivitas pengajar di log.
     *
     * Hanya akun yang benar-benar ada di sistem yang dicatat, karena
     * `kelas_dosen.user_id` menunjuk ke tabel users. Akun dengan peran
     * kaprodi dicatat sebagai "pembantu" supaya tidak mengambil alih
     * pengampu resmi kelas.
     */
    private function catatPengampu(BuktiPeran $buktiPeran, bool $dryRun): void
    {
        $kandidat = $this->kandidat($buktiPeran, false);
        $ada = $this->akunSudahAda();

        $baris = [];

        foreach (Kelas::with('mataKuliah')->get() as $kelas) {
            $kodeMk = (string) $kelas->mataKuliah?->kode;

            foreach ($kandidat as $identitas => $k) {
                if (! in_array($kodeMk, $k['kode'], true)) {
                    continue;
                }

                $akun = $ada['_'.$identitas] ?? null;

                if ($akun === null) {
                    $baris[] = [$kodeMk, $k['nama'], $identitas, 'belum ada akun'];

                    continue;
                }

                $peran = $akun->role === 'kaprodi' ? 'pembantu' : 'pengampu';

                if ($dryRun) {
                    $baris[] = [$kodeMk, $k['nama'], $identitas, 'akan dicatat: '.$peran];

                    continue;
                }

                DB::table('kelas_dosen')->updateOrInsert(
                    ['kelas_id' => $kelas->id, 'user_id' => $akun->id],
                    ['peran' => $peran],
                );

                $baris[] = [$kodeMk, $k['nama'], $identitas, $peran.' ('.$akun->email.')'];
            }
        }

        $this->table(['Mata kuliah', 'Nama', 'Nomor induk', 'Penugasan'], $baris);

        if (! $dryRun) {
            $this->comment('Total baris kelas_dosen sekarang: '.DB::table('kelas_dosen')->count().'.');
        }
    }

    /**
     * Buang nomor induk yang menempel di ujung nama.
     *
     * Kolom "User full name" di log berisi nama DAN nomor sekaligus
     * ("SyaprillaDonataAMdKom 225364").
     */
    private function bersihkanNama(string $nama, string $identitas): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', $nama) ?? '');

        if ($nama === '') {
            return 'Dosen '.$identitas;
        }

        $tanpa = trim(preg_replace('/\s*\d{4,}\s*$/u', '', $nama) ?? '');

        return $tanpa !== '' ? $tanpa : $nama;
    }
}
