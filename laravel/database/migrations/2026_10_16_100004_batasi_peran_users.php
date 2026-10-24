<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batasi nilai kolom users.role di level database.
 *
 * Kenapa perlu migration tersendiri, padahal enum sudah ditulis di
 * migration awal:
 *
 *  - Di MySQL/MariaDB, enum memang membatasi nilai. Tapi kalau nanti
 *    Someone menambah nilai lewat ->change() di driver lain, yang
 *    hampir selalu kita pakai saat menguji adalah SQLite.
 *  - Di SQLite, kolom enum TIDAK diberi CHECK constraint saat tabel
 *    dibuat, hanya ketika definisi kolomnya diubah lewat ->change().
 *
 * Migration ini sengaja memanggil ->change() dengan nilai yang sama
 * supaya batasan itu benar-benar terbentuk di kedua driver.
 *
 * Peran yang diizinkan HANYA orang yang benar-benar masuk ke sistem:
 * Admin, Dosen, dan Kaprodi. Mahasiswa tidak punya akun, jadi tidak
 * ada peran 'mahasiswa' di tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'dosen', 'kaprodi'])->default('dosen')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'dosen', 'kaprodi', 'mahasiswa'])->default('dosen')->change();
        });
    }
};
