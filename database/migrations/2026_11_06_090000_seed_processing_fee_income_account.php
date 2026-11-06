<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('accounts')->updateOrInsert(
            ['code' => '4400'],
            [
                'name' => 'Processing Fee Income',
                'type' => 'income',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '4400')->delete();
    }
};
