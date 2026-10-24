<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal dan jenis kelas pada tabel `kelas`.
 *
 * DATA DARI PENGGUNA (18 Okt 2026): daftar mahasiswa dipecah berdasarkan
 * kelas asal (A/B/C/D) dan waktu pembelajaran (Pagi/Malam). Semula informasi itu
 * hanya tersimpan di dalam nama kelas, misalnya "Kelas A Pagi".
 *
 * Kenapa harus jadi kolom sendiri, bukan dibaca dari nama:
 *
 *   - Memfilter "semua kelas pagi" dengan menyaring teks nama rapuh dan
 *     tidak bisa dihitung jumlah/urutannya oleh database.
 *   - Nama kelas bisa diganti Admin kapan saja tanpa merusak filter yang
 *     sudah dipakai pengguna.
 *
 * NULL berarti kelas tidak punya pengelompokan ini, mis. kelas lama yang
 * masih dipakai untuk mata kuliah tanpa daftar Pagi/Malam. Filter di UI
 * memakai NULL sebagai pilihan "tanpa asal/jenis".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kelas', 'asal_kelas')) {
            return;
        }

        Schema::table('kelas', function (Blueprint $table) {
            $table->string('asal_kelas', 32)->nullable()->after('nama')->index();
            $table->string('jenis_kelas', 16)->nullable()->after('asal_kelas')->index();
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn(['asal_kelas', 'jenis_kelas']);
        });
    }
};
