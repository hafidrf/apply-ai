<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil bisa berasal dari BANYAK PDF / teks panjang / isi halaman web.
 * TEXT di MySQL hanya 64 KB — tidak cukup. Ubah ke LONGTEXT (4 GB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->longText('raw_input')->nullable()->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->longText('raw_text')->nullable()->change();
            $table->longText('ocr_text')->nullable()->change();
        });

        Schema::table('drafts', function (Blueprint $table) {
            $table->longText('message_text')->change();
            $table->longText('variant_text')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->text('raw_input')->nullable()->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->text('raw_text')->nullable()->change();
            $table->text('ocr_text')->nullable()->change();
        });

        Schema::table('drafts', function (Blueprint $table) {
            $table->text('message_text')->change();
            $table->text('variant_text')->nullable()->change();
        });
    }
};
