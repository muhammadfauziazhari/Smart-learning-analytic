<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel induk untuk proses import log LMS (FR-02, FR-10).
 *
 * Setiap file CSV yang diupload dicatat sebagai satu batch supaya hasil
 * import bisa ditelusuri, diulang, atau dibandingkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();

            // Diunggah oleh Admin (FR-06)
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();

            $table->string('nama_file');
            $table->string('path')->nullable();

            // Mata kuliah hasil pembacaan kolom "Event context"
            $table->string('kode_mata_kuliah', 32)->nullable()->index();
            $table->string('nama_mata_kuliah')->nullable();

            // Periode analisis, mis. '2026-09' atau '2026-W35' (FR-11)
            $table->string('periode', 16)->nullable()->index();

            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('jumlah_berhasil')->default(0);
            $table->unsignedInteger('jumlah_duplikat')->default(0);
            $table->unsignedInteger('jumlah_gagal')->default(0);
            $table->unsignedInteger('jumlah_terkenal')->default(0);
            $table->unsignedInteger('jumlah_tak_dikenal')->default(0);

            $table->enum('status', ['menunggu', 'diproses', 'berhasil', 'gagal'])
                ->default('menunggu')->index();

            $table->text('pesan')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('log_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();

            $table->unsignedInteger('nomor_baris')->nullable();
            $table->string('kolom', 64)->nullable();
            $table->string('kode_error', 64)->nullable()->index();
            $table->text('pesan');

            $table->string('nilai_asal', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_import_errors');
        Schema::dropIfExists('import_batches');
    }
};
