<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel data master akademik (FR-03).
 *
 *Tabel ini adalah fondasi batas akses (FR-05). Tanpa relasi
 * dosen-ke-kelas dan mahasiswa-ke-kelas, tidak ada cara menentukan
 * mahasiswa mana yang boleh dilihat dosen tertentu, selain menyembunyikan
 * menu di tampilan saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fakultas', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 16)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('prodis', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 16)->unique();
            $table->string('nama');
            $table->foreignId('fakultas_id')->nullable()->constrained('fakultas')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mata_kuliahs', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama');

            // SKS dipakai untuk bobot penilaian, bukan untuk analitik aktivitas.
            $table->unsignedTinyInteger('sks')->default(3);

            // Semester rencana: 1 sampai 8 untuk prodi 4 tahun.
            $table->unsignedTinyInteger('semester')->nullable();

            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id();

            // Kode kelas di dalam satu mata kuliah, mis. "2A", "2B".
            $table->string('kode', 32);
            $table->string('nama');

            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliahs')->cascadeOnDelete();

            // Disalin dari mata kuliah agar penyaringan per prodi tidak
            // selalu perlu joins.
            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->nullOnDelete();

            $table->string('tahun_akademik', 16)->index();
            $table->unsignedTinyInteger('semester')->index();
            $table->unsignedSmallInteger('kapasitas')->nullable();

            $table->timestamps();

            $table->unique(['mata_kuliah_id', 'kode', 'tahun_akademik', 'semester'], 'kelas_unik');
        });

        /*
         * Dosen pengampu pada sebuah kelas.
         *
         * Relasi many-to-many, bukan kolom dosen_id di kelas, karena satu
         * dosen bisa mengampu beberapa kelas dan satu kelas bisa punya
         * lebih dari satu dosen (utama dan pembantu).
         */
        Schema::create('kelas_dosen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('peran', ['pengampu', 'pembantu'])->default('pengampu');

            $table->timestamps();

            $table->unique(['kelas_id', 'user_id']);
        });

        /*
         * Mahasiswa yang terdaftar pada sebuah kelas.
         *
         * PENTING: tabel ini TIDAK terhubung ke tabel users.
         *
         * Mahasiswa tidak pernah masuk ke sistem ini, jadi tidak ada
         * gunanya membuat akun, password, atau email untuk mereka. Yang
         * dibutuhkan hanya identitas: NIM dan, kalau sudah diketahui,
         * nama. Akun hanya dibuat untuk orang yang benar-benar login,
         * yaitu Admin, Dosen, dan Kaprodi.
         *
         * Kolom `nama` boleh kosong karena Admin sering hanya punya
         * daftar NIM. Nama dapat dilengkapi nanti dari data log LMS.
         */
        Schema::create('kelas_mahasiswa', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();

            $table->string('nim', 32);
            $table->string('nama')->nullable();

            $table->timestamp('terdaftar_pada')->nullable();
            $table->timestamps();

            $table->unique(['kelas_id', 'nim']);
            $table->index('nim');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_mahasiswa');
        Schema::dropIfExists('kelas_dosen');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('mata_kuliahs');
        Schema::dropIfExists('prodis');
        Schema::dropIfExists('fakultas');
    }
};
