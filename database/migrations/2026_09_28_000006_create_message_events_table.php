<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat pesan lamaran.
 * Setiap kali user menekan **Generate** atau **Copy**, satu baris dicatat sebagai snapshot:
 * posisi, perusahaan, kanal, dan isi pesannya persis seperti saat itu.
 * Jadi nanti user masih bisa tahu pesan apa yang pernah dikirim ke lowongan mana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('job_postings')->nullOnDelete();
            $table->string('event', 20);                    // generated | variant | copied
            $table->string('kind', 20)->default('utama');   // utama | varian
            $table->string('channel', 20)->nullable();
            $table->string('position', 255)->nullable();
            $table->string('company', 255)->nullable();
            $table->longText('message_text');
            $table->unsignedInteger('word_count')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_events');
    }
};
