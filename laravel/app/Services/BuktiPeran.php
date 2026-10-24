<?php

namespace App\Services;

/**
 * Penentuan peran identitas dari event yang dil الدولnya di log LMS.
 *
 * Satu sumber kebenaran untuk dua kebutuhan:
 *
 * 1. Perintah `identitas:belum-terdaftar` — menampilkan kandidat dosen dan
 *    pengelola yang belum punya akun.
 * 2. Perintah `kelas:susun-dari-log` — tidak boleh memasukkan pengajar atau
 *    pengelola ke daftar mahasiswa kelas.
 *
 * Dasarnya BUKAN panjang digit. NIM dan NIP sama-sama 10 sampai 18 digit,
 * jadi bentuk angka tidak bisa membedakan keduanya. Dasarnya adalah
 * aktivitas: ada NABU yang tidak mungkin dilakukan mahasiswa.
 */
class BuktiPeran
{
    /**
     * Event yang MUSTAHIL dilakukan mahasiswa.
     *
     * @var array<int, string>
     */
    public const EVENT_PENGAJAR = [
        'Course module created',
        'Course updated',
        'Question created',
        'Group created',
        'Question category created',
        'Course section created',
    ];

    /**
     * Event pengelolaan course: pendaftaran, peran, dan pembatalannya.
     *
     * PENTING: hanya bentuk yang MEMBATALKAN ("unassigned", "unenrolled")
     * yang dipakai. Bentuk positifnya ("Role assigned", "User enrolled in
     * course") muncul pada hampir setiap mahasiswa saat mereka mendaftar
     * sendiri ke course, jadi tidak bisa dipakai sebagai bukti apa pun.
     *
     * @var array<int, string>
     */
    public const EVENT_PENGELOLA = [
        'Course created',
        'Enrolment instance created',
        'Enrolment instance updated',
        'Role unassigned',
        'User unenrolled from course',
    ];

    /**
     * Event yang hanya bisa dilakukan penilai, tapi pernah juga muncul
     * pada satu-dua mahasiswa (Moodle mencatatnya atas nama orang yang
     * membuka halaman). Karena itu BUKAN bukti, hanya penanda untuk
     * diperiksa manual.
     *
     * @var array<int, string>
     */
    public const EVENT_SAMAR = [
        'Log report viewed',
        'The submission has been graded.',
        'Grading table viewed',
        'Grade item created',
        'Quiz report viewed',
        'Course user report viewed',
    ];

    /** Identitas yang perilakunya persis seperti mahasiswa. */
    public const PESERTA = 'peserta';

    /** Ada bukti membuat atau mengubah isi course. */
    public const PENGAJAR = 'pengajar';

    /** Ada bukti mengelola peserta dan peran. */
    public const PENGELOLA = 'pengelola';

    /** Hanya bukti lemah, harus diperiksa manual. */
    public const MERAGUKAN = 'meragukan';

    /**
     * Bagi satu identitas ke empat tingkat bukti.
     *
     * Urutan pemeriksaan penting: pengajar lebih kuat daripada
     * pengelola, dan pengelola lebih kuat daripada penanda lemah.
     *
     * @param  array<string, int>  $jenisEvent  nama event => jumlah kemunculan
     * @return array{tingkat: string, bukti: array<int, string>}
     */
    public function classify(array $jenisEvent): array
    {
        $pengajar = $this->bukti($jenisEvent, self::EVENT_PENGAJAR);
        $pengelola = $this->bukti($jenisEvent, self::EVENT_PENGELOLA);
        $samar = $this->bukti($jenisEvent, self::EVENT_SAMAR);

        if ($pengajar !== []) {
            return ['tingkat' => self::PENGAJAR, 'bukti' => $pengajar];
        }

        if ($pengelola !== []) {
            return ['tingkat' => self::PENGELOLA, 'bukti' => $pengelola];
        }

        if ($samar !== []) {
            return ['tingkat' => self::MERAGUKAN, 'bukti' => $samar];
        }

        return ['tingkat' => self::PESERTA, 'bukti' => []];
    }

    /**
     * Identitas ini boleh masuk daftar mahasiswa kelas?
     *
     * Hanya "peserta" dan "meragukan" yang boleh. Identitas dengan bukti
     * pengajar atau pengelola jelas bukan mahasiswa, sehingga tidak boleh
     * dihitung sebagai mahasiswa pada analitik.
     */
    public function bolehJadiMahasiswa(string $tingkat): bool
    {
        return $tingkat === self::PESERTA || $tingkat === self::MERAGUKAN;
    }

    /**
     * Apakah nomor identitasnya berupa NIM prodi ini?
     *
     * Aturannya DARI PENGGUNA (18 Okt 2026): mahasiswa Teknik Informatika
     * bernomor berawalan 3312. Di dua file log nyata, 263 dari 269 identitas
     * 10 digit memang berawalan 3312; enam sisanya (awalan 4312/4342) adalah
     * dosen, pengelola, atau mahasiswa prodi lain.
     *
     * Ini penyaring TAMBAHAN setelah bukti perbuatan, bukan pengganti bukti:
     * nomor saja tidak pernah cukup untuk menyebut seseorang dosen.
     *
     * @param  string|list<string>  $awalan  satu atau beberapa awalan NIM yang sah
     */
    public function bentukannyaNim(string $identitas, string|array $awalan = '3312'): bool
    {
        if (! ctype_digit($identitas)) {
            return false;
        }

        foreach ((array) $awalan as $satu) {
            if ($satu !== '' && str_starts_with($identitas, $satu)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $jenis
     * @param  array<int, string>  $daftar
     * @return array<int, string>
     */
    private function bukti(array $jenis, array $daftar): array
    {
        $cocok = array_values(array_intersect(array_keys($jenis), $daftar));

        // Yang paling sering muncul lebih meyakinkan, jadi didahulukan.
        usort($cocok, fn ($a, $b) => $jenis[$b] <=> $jenis[$a]);

        return array_slice($cocok, 0, 2);
    }
}
