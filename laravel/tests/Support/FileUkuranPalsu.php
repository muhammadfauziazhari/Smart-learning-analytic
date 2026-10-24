<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * File unggahan yang melaporkan ukuran berbeda dari ukuran aslinya.
 *
 * Dipakai untuk menguji batas ukuran tanpa harus membuat file yang
 * benar-benar sebesar itu di disk. Laravel membaca ukuran file unggahan
 * dari {@see getSize()}, jadi cukup di-overwrite di sini: isi file tetap
 * kecil (cepat), tetapi validator melihat ukuran yang besar.
 *
 * Berguna untuk membuktikan bahwa "tanpa batas ukuran" berlaku, bukan
 * hanya tertulis di komentar.
 */
final class FileUkuranPalsu extends UploadedFile
{
    private string $path;

    private function __construct(
        string $path,
        string $originalName,
        private readonly int $ukuranPalsu,
    ) {
        parent::__construct($path, $originalName, 'text/plain', null, true);

        $this->path = $path;
    }

    /**
     * Bungkus file CSV yang sudah jadi dengan ukuran yang dilaporkan.
     */
    public static function dariCsv(UploadedFile $csv, int $ukuran, string $namaAsli = 'log-lms.csv'): self
    {
        return new self($csv->getPathname(), $namaAsli, $ukuran);
    }

    public function getSize(): int
    {
        return $this->ukuranPalsu;
    }

    /** Ukuran yang benar-benar ada di disk (untuk pembuktian di test). */
    public function ukuranSebenarnya(): int
    {
        return filesize($this->path);
    }
}
