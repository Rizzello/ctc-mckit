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
        Schema::create('conference_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('sessionize_id')->unique();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_service_session')->default(false);
            $table->boolean('is_plenum_session')->default(false);
            $table->json('categories')->nullable();
            $table->string('sessionize_status')->default('active')->index();
            $table->text('mc_description')->nullable();
            $table->longText('mc_script')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'starts_at']);
            $table->index('starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conference_sessions');
    }
};
