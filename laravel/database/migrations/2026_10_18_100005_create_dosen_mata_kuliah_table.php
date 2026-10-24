<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akses dosen per MATA KULIAH (FR-03/FR-05).
 *
 * Sebelumnya akses dosen hanya ada di `kelas_dosen` — satu baris per
 * kelas. Itu menyulitkan Admin: saat log mata kuliah baru masuk, ia harus
 * menandai tiap kelas satu per satu, dan kelas yang baru dibuat belakangan
 * otomatis tertinggal tanpa akses.
 *
 * Tabel ini menyimpan izin di tingkat mata kuliah, jadi:
 *   - Admin cukup mencentang "Pemrograman Web" sekali.
 *   - Kelas A dan B dari mata kuliah itu otomatis ikut terlihat.
 *   - Kelas yang dibuat belakangan juga ikut, tanpa perlu ditandai ulang.
 *
 * `kelas_dosen` tetap ada karena menyimpan peran (pengampu/pembantu) untuk
 * halaman Penugasan. Pemakaian keduanya dijaga di `Kelas::scopeUntukPengguna()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dosen_mata_kuliah')) {
            return;
        }

        Schema::create('dosen_mata_kuliah', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliahs')->cascadeOnDelete();

            // Dosen yang sama tidak boleh punya izin yang sama dua kali.
            $table->unique(['user_id', 'mata_kuliah_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen_mata_kuliah');
    }
};
