<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel log aktivitas mentah hasil import CSV Moodle (FR-02, FR-03).
 *
 * Pemetaan kolom disusun dari file log nyata Mata Kuliah IF209
 * Jaringan Komputer (131.767 baris, 9 kolom, pemisah ';').
 *
 * Catatan penting dari audit file contoh:
 *
 * - Kolom "IP address" di file contoh TIDAK disimpan. File pernah dibuka
 *   di Excel sehingga 16,33% baris berubah jadi notasi ilmiah
 *   (mis. 1,62158E+11) dan 99,98% tidak lagi valid sebagai IP.
 *   Menyimpan kolom itu hanya menambah data palsu.
 *
 * - Waktu pada file hanya presisi MENIT (format DD/MM/YYYY HH:MM).
 *   Detik tidak ada, dan tidak boleh dikarang saat parsing.
 *
 * - Kolom identitas TIDADA di file Moodle. NIM/NIP menempel di akhir
 *   kolom "User full name", mis. "Ririn Anisa Sihite 3312501013".
 *   Karena itu actor_name disimpan apa adanya (untuk audit) dan
 *   actor_identifier diisi hasil parsing.
 *
 * - Peran TIDAK ditebak dari panjang digit. Pada file contoh,
 *   dosen pengampu punya id 6 digit, 10 digit, dan 18 digit sekaligus.
 *   Peran ditentukan dengan mencocokkan actor_identifier ke tabel users,
 *   bukan dari bentuk digitnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();

            // --- Asal data ---
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->timestamps();

            // --- Waktu kejadian ---
            // Presisi menit, sama seperti file sumber.
            $table->dateTime('occurred_at')->index();

            // --- Pelaku (aktor) ---
            $table->string('actor_name');
            $table->string('actor_identifier', 32)->nullable()->index();
            $table->enum('actor_role', ['mahasiswa', 'dosen', 'kaprodi', 'akun_lainnya', 'tak_diketahui'])
                ->default('tak_diketahui')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable();

            // --- Targeted: mahasiswa yang dikenai tindakan, mis. yang dinilai ---
            // 77% baris bernilai '-' pada file contoh, jadi nullable.
            $table->string('target_name')->nullable();
            $table->string('target_identifier', 32)->nullable()->index();
            $table->foreignId('target_user_id')->nullable();

            // --- Konteks kejadian ---
            $table->string('context_type', 64)->nullable()->index();
            $table->string('context_name')->nullable();

            // --- Rincian kejadian ---
            $table->string('component', 64)->nullable()->index();
            $table->string('event_name')->index();
            $table->text('description')->nullable();
            $table->string('origin', 32)->nullable();

            // --- Konteks indeks untuk analitik ---
            $table->string('kode_mata_kuliah', 32)->nullable()->index();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('periode', 16)->nullable()->index();

            // Sidik jari alami baris, untuk mencegah duplikat.
            //
            // PENTING: constraint ini UNIК, bukan indeks biasa. Kalau Admin
            // mengunggah file yang sama dua kali, atau dua file bulanan
            // saling tumpang tindih, baris yang sama tidak boleh masuk
            // dua kali ke tabel yang sama.
            //
            // Sidik jari TIDAK memakai detik, karena file sumber hanya
            // presisi menit.
            $table->char('sidik_jari', 32)->unique();
        });

        // Query analitik hampir selalu "berapa aktivitas mahasiswa X
        // pada kelas Y dalam periode Z", jadi indeks dibuat sesuai pola itu.
        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->index(['actor_identifier', 'occurred_at'], 'log_aktivitas_aktor_waktu_idx');
            $table->index(['actor_user_id', 'occurred_at'], 'log_aktivitas_user_waktu_idx');
            $table->index(['kode_mata_kuliah', 'occurred_at'], 'log_aktivitas_mk_waktu_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
