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
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('version')->nullable();
            $table->string('from_ref');
            $table->string('to_ref');
            $table->string('recommended_version');
            $table->string('recommended_type'); // MAJOR, MINOR, PATCH
            $table->string('final_version')->nullable();
            $table->string('final_type')->nullable();
            $table->unsignedTinyInteger('confidence'); // 0-100
            $table->string('status')->default('pending'); // pending, accepted, adjusted, rejected
            $table->text('release_notes')->nullable();
            $table->json('commits')->nullable();
            $table->json('changes')->nullable(); // breaking, features, fixes
            $table->json('heuristic_reasoning')->nullable();
            $table->json('ai_reasoning')->nullable();
            $table->string('analysis_source')->default('heuristic'); // heuristic, ai, combined
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['repository_id', 'status']);
            $table->index(['repository_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
