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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->string('reason_category'); // breaking_missed, breaking_false_positive, feature_miscategorized, fix_miscategorized, confidence_high, confidence_low, other
            $table->text('reason_details')->nullable();
            $table->string('original_version');
            $table->string('original_type'); // MAJOR, MINOR, PATCH
            $table->string('user_version');
            $table->string('user_type'); // MAJOR, MINOR, PATCH
            $table->string('specific_commit_sha')->nullable();
            $table->timestamps();

            $table->index('reason_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
