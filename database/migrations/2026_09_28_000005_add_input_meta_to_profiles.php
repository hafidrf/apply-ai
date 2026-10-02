<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ringkasan sumber input profil (teks/link/PDF/gambar mana yang dibaca),
 * supaya bisa ditampilkan ke user seperti di halaman lowongan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->json('input_meta')->nullable()->after('raw_input');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('input_meta');
        });
    }
};
