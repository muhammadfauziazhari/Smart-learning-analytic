<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_analitik', function (Blueprint $table) {
            $table->id();
            $table->string('kode_mata_kuliah', 32);
            $table->string('periode_awal', 7);
            $table->string('periode_akhir', 7);
            $table->string('nim', 32);
            $table->decimal('engagement_rate', 8, 6)->nullable();
            $table->string('cluster_label')->nullable();
            $table->decimal('risk_score', 8, 6)->nullable();
            $table->string('risk_level', 16)->nullable();
            $table->unsignedBigInteger('batch_id');
            $table->timestamps();

            $table->unique(
                ['kode_mata_kuliah', 'periode_awal', 'periode_akhir', 'nim'],
                'hasil_analitik_periode_mahasiswa_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_analitik');
    }
};
