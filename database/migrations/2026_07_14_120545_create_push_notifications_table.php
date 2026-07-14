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
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('target_type'); // user | group
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->json('channels'); // ['push', 'mail', 'sms']
            $table->string('status')->default('pending')->index(); // pending | processing | sent | failed
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
