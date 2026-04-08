<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add IP address tracking to posts for rate limiting
        Schema::table('posts', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('dislikes');
        });

        // Create bans table for profanity enforcement
        Schema::create('bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->index();
            $table->string('reason')->default('kata_kasar');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
        Schema::dropIfExists('bans');
    }
};
