<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');            // felidaeai|deepseek|openrouter|gemini|groq|ollama|custom
            $table->string('label')->nullable();
            $table->text('api_key')->nullable();   // encrypted cast
            $table->string('base_url')->nullable(); // untuk custom / override
            $table->string('default_model')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'provider']);
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_id');
            $table->string('source');              // text|pdf|link
            $table->text('raw_input')->nullable();
            $table->json('data');                  // profil terstruktur hasil parse
            $table->timestamp('confirmed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'session_id']);
        });

        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->string('raw_input_type');       // text|screenshot
            $table->text('raw_text')->nullable();
            $table->text('ocr_text')->nullable();
            $table->string('channel')->nullable();  // email|linkedin|whatsapp|portal
            $table->string('position')->nullable();
            $table->string('company')->nullable();
            $table->json('parsed')->nullable();     // hasil JobParser
            $table->json('matching')->nullable();   // hasil RequirementMatcher
            $table->json('reframes')->nullable();   // hasil GapReframer
            $table->string('status')->default('pending'); // pending|parsed|matched|composed|failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('channel');
            $table->text('message_text');
            $table->json('notes')->nullable();      // catatan reframing untuk user
            $table->text('variant_text')->nullable();
            $table->timestamps();

            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('llm_keys');
    }
};
