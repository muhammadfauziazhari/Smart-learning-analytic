<?php

namespace App\Services\Moodle;

use DateTimeImmutable;

/**
 * Mengubah satu baris log Moodle menjadi data siap simpan.
 * Format file log report Moodle (diverifikasi dari file nyata IF209):
 *
 *   Time;User full name;Affected user;Event context;Component;Event name;
 *   Description;Origin;IP address
 *   14/10/2026 14:50;Ririn Anisa Sihite 3312501013;-;Course: Jaringan Komputer/IF209;...
 *
 * Sifat penting file ini:
 * - Pemisah kolom ';' (bukan koma)
 * - Diawali BOM UTF-8
 * - Waktu berformat d/m/Y H:i, presisi MENIT, tanpa detik
 * - Tidak ada kolom identitas; NIM/NIP menempel di akhir nama
 * - Kolom 'IP address' rusak karena pernah dibuka di Excel, jadi dibuang
 */
class MoodleLogParser
{
    /** Header yang diharapkan, dipetakan ke nama internal. */
    public const HEADER = [
        'time' => 'Time',
        'user_full_name' => 'User full name',
        'affected_user' => 'Affected user',
        'event_context' => 'Event context',
        'component' => 'Component',
        'event_name' => 'Event name',
        'description' => 'Description',
        'origin' => 'Origin',
        // 'IP address' sengaja tidak dipetakan. Lihat catatan di atas.
    ];

    /** Nilai yang menandai kolom kosong pada file Moodle. */
    private const KOSONG = ['-', '', 'null', 'NULL'];

    /** Jumlah karakter terakhir yang dipangkas saat memisahkan nama dan id. */
    private const PANJANG_IDENTITAS = 32;

    /**
     * Tebak pemisah kolom dari baris header.
     *
     * Moodle memakai ';', tapi file yang pernah dibuka di Excel kadang
     * berubah jadi ','. Karena itu keduanya dihitung, bukan ditebak dari
     * ekstensi berkas.
     */
    public function tebakPemisah(string $barisHeader): string
    {
        return substr_count($barisHeader, ';') > substr_count($barisHeader, ',') ? ';' : ',';
    }

    /**
     * Petakan nama header ke nama internal.
     *
     * @param  array<int, string>  $header  Baris pertama file
     * @return array<string, int> nama internal => indeks kolom
     */
    public function petakanHeader(array $header): array
    {
        // Buang BOM yang menempel di kolom pertama
        $header[0] = $this->buangBom((string) ($header[0] ?? ''));

        $peta = [];
        foreach (self::HEADER as $internal => $asli) {
            $posisi = array_search($asli, $header, true);
            if ($posisi !== false) {
                $peta[$internal] = $posisi;
            }
        }

        return $peta;
    }

    /**
     * Daftar kolom yang wajib ada. Kalau kurang, file bukan log Moodle.
     *
     * @param  array<string, int>  $peta
     * @return array<int, string>
     */
    public function kolomWajib(array $peta): array
    {
        return array_values(array_diff(
            ['time', 'user_full_name', 'event_name'],
            array_keys($peta)
        ));
    }

    /**
     * Ubah satu baris menjadi data siap simpan.
     *
     * @param  array<int, string>  $baris
     * @param  array<string, int>  $peta
     * @return array{valid: bool, data: array<string, mixed>, errors: array<int, string>}
     */
    public function parseBaris(array $baris, array $peta): array
    {
        $errors = [];
        $waktu = $this->ambil($baris, $peta, 'time');
        $namaAktor = trim((string) $this->ambil($baris, $peta, 'user_full_name'));
        $eventName = trim((string) $this->ambil($baris, $peta, 'event_name'));

        if ($namaAktor === '') {
            $errors[] = 'Kolom "User full name" kosong';
        }

        if ($eventName === '') {
            $errors[] = 'Kolom "Event name" kosong';
        }

        $occurredAt = $this->parseWaktu($waktu);
        if ($occurredAt === null) {
            $errors[] = sprintf(
                'Waktu "%s" tidak bisa dibaca (harus format d/m/Y H:i)',
                $waktu
            );
        }

        if ($errors !== []) {
            return ['valid' => false, 'data' => [], 'errors' => $errors];
        }

        $target = $this->parseIdentitas((string) $this->ambil($baris, $peta, 'affected_user'));
        $konteks = $this->parseKonteks((string) $this->ambil($baris, $peta, 'event_context'));
        $aktor = $this->parseIdentitas($namaAktor);

        return [
            'valid' => true,
            'data' => [
                'occurred_at' => $occurredAt->format('Y-m-d H:i:00'),
                'periode' => $occurredAt->format('Y-m'),

                'actor_name' => $namaAktor,
                'actor_identifier' => $aktor['identifier'],
                'actor_role' => 'tak_diketahui',

                'target_name' => $target['nama'],
                'target_identifier' => $target['identifier'],

                'context_type' => $konteks['type'],
                'context_name' => $konteks['name'],
                'kode_mata_kuliah' => $konteks['kode_mk'],
                'nama_mata_kuliah' => $konteks['nama_mk'],

                'component' => $this->bersihkan($this->ambil($baris, $peta, 'component')),
                'event_name' => $eventName,
                'description' => $this->bersihkan($this->ambil($baris, $peta, 'description')),
                'origin' => $this->bersihkan($this->ambil($baris, $peta, 'origin')),
            ],
            'errors' => [],
        ];
    }

    /**
     * Pisahkan nama dan identifier dari nilai kolom "User full name".
     *
     * Contoh: "Ririn Anisa Sihite 3312501013" -> nama + identifier.
     * Contoh: "Ketua Program Studi Informatika 1506" -> nama + identifier.
     *
     * @return array{nama: string|null, identifier: string|null}
     */
    public function parseIdentitas(string $nilai): array
    {
        if ($this->adalahKosong($nilai)) {
            return ['nama' => null, 'identifier' => null];
        }

        $nilai = trim($nilai);

        // Ambil digit di akhir, maksimal sepanjang PANJANG_IDENTITAS
        if (preg_match('/^(?<nama>.*?)\s+(?<id>\d{1,'.self::PANJANG_IDENTITAS.'})$/u', $nilai, $m) === 1) {
            $nama = trim($m['nama']);

            return [
                'nama' => $nama === '' ? $nilai : $nama,
                'identifier' => $m['id'],
            ];
        }

        // Tidak ada digit di akhir (mis. "Admin upa-tik")
        return ['nama' => $nilai, 'identifier' => null];
    }

    /**
     * Pecah kolom "Event context" yang berbentuk "Tipe: Nama".
     *
     * Untuk course, nama dipisah lagi menjadi nama mata kuliah dan kode.
     * Contoh: "Course: Jaringan Komputer/IF209".
     *
     * @return array{type: string|null, name: string|null, kode_mk: string|null, nama_mk: string|null}
     */
    public function parseKonteks(string $nilai): array
    {
        $kosong = [
            'type' => null, 'name' => null,
            'kode_mk' => null, 'nama_mk' => null,
        ];

        if ($this->adalahKosong($nilai)) {
            return $kosong;
        }

        $nilai = trim($nilai);

        // "Course: Jaringan Komputer/IF209"
        if (preg_match('/^Course:\s*(?<nama>.+?)\s*\/\s*(?<kode>[A-Za-z0-9]+)$/u', $nilai, $m) === 1) {
            return [
                'type' => 'Course',
                'name' => trim($m['nama']),
                'kode_mk' => trim($m['kode']),
                'nama_mk' => trim($m['nama']),
            ];
        }

        // "Quiz: ATS Teori" atau "Assignment: Pengumpulan Praktikum 1"
        if (preg_match('/^(?<tipe>[A-Za-z][A-Za-z ]*?):\s*(?<nama>.+)$/u', $nilai, $m) === 1) {
            return [
                'type' => trim($m['tipe']),
                'name' => trim($m['nama']),
                'kode_mk' => null,
                'nama_mk' => null,
            ];
        }

        // Tidak berpola "Tipe: Nama", simpan apa adanya
        return ['type' => null, 'name' => $nilai, 'kode_mk' => null, 'nama_mk' => null];
    }

    /**
     * Parse waktu d/m/Y H:i. Detik sengaja tidak ada di file sumber.
     */
    public function parseWaktu(string $nilai): ?DateTimeImmutable
    {
        $nilai = trim($nilai);
        if ($nilai === '') {
            return null;
        }

        $waktu = DateTimeImmutable::createFromFormat('!d/m/Y H:i', $nilai);

        if ($waktu === false) {
            return null;
        }

        // createFromFormat bisa mengembalikan objek meski ada peringatan
        // (mis. "31/02/2026 10:00" yang digeser ke Maret).
        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $waktu;
    }

    private function ambil(array $baris, array $peta, string $kunci): ?string
    {
        if (! isset($peta[$kunci])) {
            return null;
        }

        return $baris[$peta[$kunci]] ?? null;
    }

    private function bersihkan(?string $nilai): ?string
    {
        if ($nilai === null || $this->adalahKosong($nilai)) {
            return null;
        }

        return trim($nilai);
    }

    private function adalahKosong(string $nilai): bool
    {
        return in_array(trim($nilai), self::KOSONG, true);
    }

    private function buangBom(string $nilai): string
    {
        if (str_starts_with($nilai, "\xEF\xBB\xBF")) {
            return substr($nilai, 3);
        }

        return $nilai;
    }
}
