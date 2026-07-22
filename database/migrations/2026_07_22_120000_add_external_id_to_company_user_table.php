<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('is_company_admin');

            $table->unique(['company_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
