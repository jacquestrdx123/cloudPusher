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
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->string('image_url', 2048)->nullable()->after('body');
            $table->string('sound', 64)->nullable()->after('image_url');
            $table->string('category', 64)->nullable()->after('sound');
            $table->string('android_channel_id', 64)->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'sound', 'category', 'android_channel_id']);
        });
    }
};
