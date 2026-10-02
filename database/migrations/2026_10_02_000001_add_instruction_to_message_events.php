<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_events', function (Blueprint $table) {
            $table->text('instruction')->nullable()->after('message_text');
        });
    }

    public function down(): void
    {
        Schema::table('message_events', function (Blueprint $table) {
            $table->dropColumn('instruction');
        });
    }
};