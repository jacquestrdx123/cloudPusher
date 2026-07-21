<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users')
            ->whereNotNull('company_id')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($now): void {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'company_id' => $user->company_id,
                        'user_id' => $user->id,
                        'is_company_admin' => (bool) $user->is_company_admin,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('company_user')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('company_user')->delete();
    }
};
