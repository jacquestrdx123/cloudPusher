<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        if (Schema::hasColumn('users', 'is_company_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $indexes = Schema::getIndexes('users');
                $hasIndex = collect($indexes)->contains(
                    fn (array $index): bool => in_array('is_company_admin', $index['columns'], true),
                );

                if ($hasIndex) {
                    $table->dropIndex('users_is_company_admin_index');
                }
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_company_admin');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'is_company_admin')) {
                $table->boolean('is_company_admin')->default(false)->index()->after('is_admin');
            }
        });
    }
};
