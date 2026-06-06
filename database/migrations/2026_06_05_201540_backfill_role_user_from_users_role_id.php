<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('users')
            ->whereNotNull('role_id')
            ->select('id as user_id', 'role_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('role_user')->updateOrInsert(
                [
                    'role_id' => $row->role_id,
                    'user_id' => $row->user_id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Intentionally left blank to avoid deleting manually assigned role mappings.
    }
};
