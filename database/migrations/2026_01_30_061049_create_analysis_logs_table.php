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
        Schema::create('analysis_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->string('stage'); // fetch_commits, heuristic_analysis, ai_classification, note_generation, finalize
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('tokens_used')->nullable();
            $table->string('model')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('status')->default('success'); // success, error, skipped
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['release_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_logs');
    }
};
