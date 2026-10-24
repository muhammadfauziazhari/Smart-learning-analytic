<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan identitas akademik ke tabel users.
 *
 * Diperlukan untuk FR-01 dan, yang lebih penting, untuk mencocokkan baris
 * log LMS dengan akun yang ada saat import (FR-02).
 *
 * Mengapa NIM/NIP harus ada di tabel users dan bukan ditebak dari log:
 * pada file log nyata IF209, dosen pengampu punya identifier 6 digit
 * (mis. 225364), 10 digit (mis. 317258), dan 18 digit/NIP sekaligus.
 * Panjang digit tidak bisa dijadikan dasar penentuan peran. Satu-satunya
 * sumber yang benar adalah pencatatan yang dilakukan sendiri oleh sistem.
 *
 * Setiap kolom ditambahkan hanya kalau belum ada. Tanpa penjagaan ini,
 * database yang migrasinya pernah berhenti di tengah (kolom sudah
 * terbentuk, pencatatannya gagal) tidak akan pernah bisa dimigrasi lagi:
 * `php artisan migrate` selalu berhenti di "Duplicate column name".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'nomor_induk')) {
                $table->string('nomor_induk', 32)->nullable()->after('nama');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }

            // Prodi bersifat opsional: Dosen dan Kaprodi umumnya punya,
            // Admin biasanya tidak. Dipakai untuk batas akses FR-05.
            if (! Schema::hasColumn('users', 'prodi_id')) {
                $table->foreignId('prodi_id')->nullable()->after('role')
                    ->constrained('prodis')->nullOnDelete();
            }
        });

        // Indeks unik pada nomor_induk dibuat terpisah supaya database
        // yang kolomnya sudah ada tapi indeksnya belum tetap ikut
        // dilengkapi.
        if (Schema::hasColumn('users', 'nomor_induk')) {
            $sudahAda = collect(Schema::getIndexes('users'))
                ->contains(fn (array $idx) => in_array('nomor_induk', $idx['columns'], true));

            if (! $sudahAda) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->unique('nomor_induk');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'prodi_id')) {
                $table->dropConstrainedForeignId('prodi_id');
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        if (Schema::hasColumn('users', 'nomor_induk')) {
            $sudahAda = collect(Schema::getIndexes('users'))
                ->contains(fn (array $idx) => in_array('nomor_induk', $idx['columns'], true));

            if ($sudahAda) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropUnique(['nomor_induk']);
                });
            }

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('nomor_induk');
            });
        }
    }
};
