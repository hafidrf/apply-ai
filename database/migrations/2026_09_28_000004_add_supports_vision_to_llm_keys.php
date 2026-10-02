<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override manual kemampuan vision per key.
 * null  = deteksi otomatis dari config llm.providers.*.vision_models
 * true  = user memastikan model ini bisa baca gambar
 * false = user memastikan tidak bisa
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_keys', function (Blueprint $table) {
            $table->boolean('supports_vision')->nullable()->after('default_model');
        });
    }

    public function down(): void
    {
        Schema::table('llm_keys', function (Blueprint $table) {
            $table->dropColumn('supports_vision');
        });
    }
};
