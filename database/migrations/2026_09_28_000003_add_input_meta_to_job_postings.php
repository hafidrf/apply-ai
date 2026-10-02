<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan ringkasan sumber input lowongan (teks/link/PDF mana yang dibaca)
 * supaya bisa ditampilkan ke user di halaman detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->json('input_meta')->nullable()->after('ocr_text');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('input_meta');
        });
    }
};
