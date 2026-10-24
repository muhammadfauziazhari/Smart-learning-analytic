<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Kembalikan password akun Dosen/Kaprodi ke password bawaan.
 *
 * KEPUTUSAN PENGGUNA, 18 Okt 2026
 *
 * Tiap akun dulu dibuat dengan password acak yang hanya ditampilkan satu
 * kali. Pada kenyataannya password itu jarang sampai ke pemiliknya, dan
 * Admin jadi sering harus mereset. Sekarang semua akun Dosen dan Kaprodi
 * memakai password bawaan yang sama, lalu mereka menggantinya sendiri
 * lewat tab Settings.
 *
 * Perintah ini hanya mengubah password; data lain (penugasan, izin mata
 * kuliah, log) tidak tersentuh.
 *
 *   php artisan akun:password-bawaan
 *   php artisan akun:password-bawaan --dry-run
 *   php artisan akun:password-bawaan --peran=dosen
 *
 * Peringatan: memakai satu password bersama berarti selama belum diganti,
 * siapa pun yang tahu password bawaan bisa masuk. Itu memang harga dari
 * keputusan "passwordnya jangan sulit" — karena itu halaman Settings
 * wajib bisa menggantinya, dan akun yang belum diganti bisa dilacak
 * lewat kolom "Password lama" pada tabel di atas.
 */
class PasswordBawaan extends Command
{
    protected $signature = 'akun:password-bawaan
                            {--dry-run : Hanya tampilkan akun yang akan diubah, jangan diubah}
                            {--peran=* : Batasi per role (dosen, kaprodi). Default: dosen dan kaprodi}';

    protected $description = 'Kembalikan password akun Dosen/Kaprodi ke password bawaan yang mudah diingat';

    /**
     * Role yang boleh memakai password bawaan.
     *
     * Admin TIDAK termasuk di sini: password admin tidak pernah
     * ditampilkan ke siapa pun kecuali lewat tombol Password di Kelola
     * Akun, dan tidak ada akun admin yang ikut perintah ini.
     *
     * @var list<string>
     */
    private const PERAN_BAWAAN = ['dosen', 'kaprodi'];

    public function handle(): int
    {
        $peran = $this->option('peran');
        $peran = $peran === []
            ? self::PERAN_BAWAAN
            : array_values(array_intersect($peran, self::PERAN_BAWAAN));

        if ($peran === []) {
            $this->error('Tidak ada role yang cocok. Pilihan: '.implode(', ', self::PERAN_BAWAAN));

            return self::FAILURE;
        }

        $akun = User::query()
            ->whereIn('role', $peran)
            ->orderBy('role')
            ->orderBy('nama')
            ->get();

        if ($akun->isEmpty()) {
            $this->warn('Tidak ada akun dengan role: '.implode(', ', $peran));

            return self::SUCCESS;
        }

        $baris = [];

        foreach ($akun as $user) {
            $sudahBawaan = Hash::check(User::PASSWORD_BAWAAN, $user->password);

            $baris[] = [
                $user->nama,
                $user->email,
                $user->role,
                $sudahBawaan ? 'sama' : 'berbeda',
                $user->is_active ? 'aktif' : 'nonaktif',
            ];
        }

        $this->table(['Nama', 'Email', 'Peran', 'Password lama', 'Status'], $baris);

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Mode dry-run: %d akun akan diset ke "%s". Jalankan tanpa --dry-run untuk menerapkan.',
                $akun->count(),
                User::PASSWORD_BAWAAN
            ));

            return self::SUCCESS;
        }

        // Hash dihitung sekali lalu dipakai semua akun: bcrypt mahal, dan
        // hasilnya memang boleh sama untuk semua orang karena ini password
        // awal yang memang diketahui bersama.
        $hash = Hash::make(User::PASSWORD_BAWAAN);

        $berubah = 0;

        foreach ($akun as $user) {
            if (Hash::check(User::PASSWORD_BAWAAN, $user->password)) {
                continue;
            }

            $user->forceFill(['password' => $hash])->save();

            $berubah++;
        }

        $this->info(sprintf(
            '%d dari %d akun (%s) diset ke "%s".',
            $berubah,
            $akun->count(),
            implode(', ', $peran),
            User::PASSWORD_BAWAAN
        ));

        $this->comment('Suruh setiap pemilik akun menggantinya sendiri lewat tab Settings.');

        return self::SUCCESS;
    }
}
