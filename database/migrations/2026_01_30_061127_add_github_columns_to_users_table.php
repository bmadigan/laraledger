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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('github_id')->nullable()->unique()->after('email');
            $table->string('github_username')->nullable()->after('github_id');
            $table->text('github_token')->nullable()->after('github_username');
            $table->text('github_refresh_token')->nullable()->after('github_token');
            $table->timestamp('github_token_expires_at')->nullable()->after('github_refresh_token');
            $table->json('github_scopes')->nullable()->after('github_token_expires_at');
            $table->boolean('setup_completed')->default(false)->after('github_scopes');
            $table->unsignedTinyInteger('setup_step')->default(0)->after('setup_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'github_id',
                'github_username',
                'github_token',
                'github_refresh_token',
                'github_token_expires_at',
                'github_scopes',
                'setup_completed',
                'setup_step',
            ]);
        });
    }
};
