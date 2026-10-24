<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_batch_id',
    'occurred_at', 'periode',
    'actor_name', 'actor_identifier', 'actor_role', 'actor_user_id',
    'target_name', 'target_identifier', 'target_user_id',
    'context_type', 'context_name',
    'component', 'event_name', 'description', 'origin',
    'kode_mata_kuliah', 'nama_mata_kuliah', 'sidik_jari',
])]
class LogAktivitas extends Model
{
    public const PERAN_MAHASISWA = 'mahasiswa';

    public const PERAN_DOSEN = 'dosen';

    public const PERAN_KAPRODI = 'kaprodi';

    public const PERAN_AKUN_LAINNYA = 'akun_lainnya';

    public const PERAN_TAK_DIKETAHUI = 'tak_diketahui';

    /**
     * Nilai actor_role yang berarti identitasnya punya akun di sistem ini.
     *
     * Peran 'mahasiswa' TIDAK termasuk di sini: mahasiswa tidak punya
     * akun, jadi peran itu hanya kesimpulan ("bukan dosen terdaftar"),
     * bukan bukti bahwa identitasnya dikenali.
     */
    public const PERAN_TERKENAL = [
        self::PERAN_DOSEN,
        self::PERAN_KAPRODI,
        self::PERAN_AKUN_LAINNYA,
    ];

    /** Event yang menandai mahasiswa benar-benar mengumpulkan tugas. */
    public const EVENT_TUGAS_DIKUMPULKAN = 'A submission has been submitted.';

    /** Event saat akses kuis ditolak Moodle. */
    public const EVENT_KUIS_DITOLAK = 'Quiz access was prevented';

    /**
     * Event saat sebuah tugas (assignment) dibuat.
     *
     * Inilah satu-satunya sumber tanggal "tugas dibuat" yang tersedia, dan itu juga yang dipakai untuk menentukan tenggat pengumpulan pada
     * tab Pengumpulan Tugas (7 hari setelah tugas dibuat).
     *
     * Catatan: event ini TIDAK memakai titik di akhir kalimatnya, berbeda
     * dari sebagian besar event lain. Salah ketik satu karakter membuat
     * seluruh tenggat kosong tanpa error yang terlihat.
     */
    public const EVENT_TUGAS_DIBUAT = 'Course module created';

    /**
     * Event saat mahasiswa membuka sebuah modul/materi.
     *
     * Dipakai kartu "Analytics Materi" di dashboard Dosen untuk menghitung
     * materi yang paling sering dan paling jarang diakses.
     */
    public const EVENT_MODUL_DILIHAT = 'Course module viewed';

    /** Konteks Moodle untuk tugas. */
    public const KONTEKS_TUGAS = 'Assignment';

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Kunci unik alami untuk mendeteksi baris duplikat.
     *
     * Waktu file sumber hanya presisi menit, jadi detik TIDAK boleh
     * ikut di sini: satu mahasiswa yang membuka halaman dua kali dalam
     * menit yang sama akan terbaca sebagai duplikat padahal dua kejadian
     * itu benar-benar terjadi.
     */
    public static function sidikJari(array $baris): string
    {
        return hash('xxh128', implode('|', [
            $baris['occurred_at'],
            $baris['actor_identifier'] ?? $baris['actor_name'],
            $baris['event_name'],
            $baris['context_name'] ?? '',
        ]));
    }
}
