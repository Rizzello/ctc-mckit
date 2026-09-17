<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conference_session_speaker', function (Blueprint $table) {
            $table->foreignId('conference_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['conference_session_id', 'speaker_id'], 'conference_session_speaker_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conference_session_speaker');
    }
};
