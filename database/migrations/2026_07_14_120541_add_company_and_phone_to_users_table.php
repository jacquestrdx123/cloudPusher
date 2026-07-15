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
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('locale')->nullable()->after('phone');
            $table->boolean('is_admin')->default(false)->index()->after('locale');
            $table->boolean('is_company_admin')->default(false)->index()->after('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'locale', 'is_admin', 'is_company_admin']);
        });
    }
};
