<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('loans')
            ->where('application_status', 'rejected')
            ->where('status', 'pending')
            ->update(['status' => 'rejected', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('loans')
            ->where('application_status', 'rejected')
            ->where('status', 'rejected')
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }
};
